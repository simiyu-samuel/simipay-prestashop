<?php
namespace SimiyuSamuel\Kopokopo\Infrastructure;

use SimiyuSamuel\Kopokopo\Domain\Orders;
use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Domain\Values;

final class ShopOrders implements Orders
{
    public function __construct(private readonly \Kopokopo $module, private readonly int $shop)
    {
    }

    public function reference(): string
    {
        return \Order::generateReference();
    }

    public function snapshot(int $cartId, int $customerId): array
    {
        $cart = new \Cart($cartId);
        $customer = new \Customer($customerId);
        if (!\Validate::isLoadedObject($cart) || !\Validate::isLoadedObject($customer)
            || (int) $cart->id_shop !== $this->shop || (int) $cart->id_customer !== $customerId
            || !$cart->id_address_delivery || !$cart->id_address_invoice || $cart->nbProducts() < 1
            || $cart->orderExists()) {
            throw new PaymentException('This cart is no longer available for payment.', 409);
        }
        foreach ([$cart->id_address_delivery, $cart->id_address_invoice] as $addressId) {
            $address = new \Address((int) $addressId);
            if (!\Validate::isLoadedObject($address) || (int) $address->id_customer !== $customerId || $address->deleted) {
                throw new PaymentException('Please complete your checkout address first.', 409);
            }
        }
        $currency = new \Currency((int) $cart->id_currency);
        if ($currency->iso_code !== 'KES' || (int) $currency->precision !== 2 || $currency->deleted || !$currency->active) {
            throw new PaymentException('M-PESA is available for Kenyan shilling (KES) orders only.', 422);
        }
        if ($cart->checkQuantities() !== true || $cart->checkProductsAccess()) {
            throw new PaymentException('An item is no longer available. Please review your cart.', 409);
        }
        if (!$cart->isVirtualCart() && !$cart->id_carrier) {
            throw new PaymentException('Please select a delivery method first.', 409);
        }
        $minor = Values::minor(number_format((float) $cart->getOrderTotal(true, \Cart::BOTH), 2, '.', ''));
        $products = [];
        foreach ($cart->getProducts(true) as $product) {
            $products[] = [(int) $product['id_product'], (int) $product['id_product_attribute'],
                (int) ($product['id_customization'] ?? 0), (int) $product['cart_quantity'],
                (string) $product['price_wt']];
        }
        sort($products);
        $fingerprint = [$cart->id_customer, $cart->id_currency, $cart->id_address_delivery,
            $cart->id_address_invoice, $cart->id_carrier, $minor, $products,
            $cart->gift, $cart->recyclable, $cart->gift_message, $cart->getDeliveryOption()];
        return ['amount_minor' => $minor, 'currency_id' => (int) $currency->id,
            'hash' => hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR)),
            'subscriber' => ['first_name' => $customer->firstname, 'last_name' => $customer->lastname, 'email' => $customer->email]];
    }

    public function complete(array $attempt): int
    {
        $cart = new \Cart((int) $attempt['cart_id']);
        $context = \Context::getContext();
        \Shop::setContext(\Shop::CONTEXT_SHOP, $this->shop);
        $context->shop = new \Shop($this->shop);
        $context->cart = $cart;
        $context->customer = new \Customer((int) $attempt['customer_id']);
        $context->currency = new \Currency((int) $attempt['currency_id']);
        $context->language = new \Language((int) $cart->id_lang);
        $context->country = new \Country((int) (new \Address((int) $cart->id_address_delivery))->id_country);
        $snapshot = $this->snapshot((int) $cart->id, (int) $context->customer->id);
        if (!hash_equals($attempt['snapshot'], $snapshot['hash'])
            || (int) $snapshot['amount_minor'] !== (int) $attempt['amount_minor']) {
            throw new PaymentException('Cart changed after the payment request.', 409);
        }
        $result = $this->module->validateOrder((int) $cart->id, (int) \Configuration::get('PS_OS_PAYMENT'),
            (float) Values::decimal((int) $attempt['amount_minor']), $this->module->displayName,
            'Kopo Kopo attempt: ' . $attempt['id'], ['transaction_id' => $attempt['receipt']],
            (int) $attempt['currency_id'], false, $context->customer->secure_key, $context->shop, $attempt['order_reference']);
        $orders = \Db::getInstance()->executeS('SELECT id_order, total_paid_tax_incl, current_state FROM `' . _DB_PREFIX_ . 'orders`
            WHERE id_cart = ' . (int) $cart->id . " AND module = 'kopokopo' AND reference = '" . pSQL($attempt['order_reference']) . "'", true, false);
        $total = 0;
        foreach ($orders ?: [] as $order) {
            $total += Values::minor(number_format((float) $order['total_paid_tax_incl'], 2, '.', ''));
            if ((int) $order['current_state'] !== (int) \Configuration::get('PS_OS_PAYMENT')) {
                throw new \RuntimeException('Order needs manual review.');
            }
        }
        if (!$result || !$orders || $total !== (int) $attempt['amount_minor']) {
            throw new \RuntimeException('Order completion was not confirmed.');
        }
        return (int) $orders[0]['id_order'];
    }
}
