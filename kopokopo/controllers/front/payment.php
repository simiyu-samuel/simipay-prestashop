<?php
use SimiyuSamuel\Kopokopo\Domain\PaymentException;

class KopokopoPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();
        try {
            $this->module->assertCheckout();
            $cart = $this->context->cart;
            $this->context->smarty->assign([
                'payment_action' => $this->context->link->getModuleLink('kopokopo', 'process', [], true),
                'form_token' => $this->module->csrf(),
                'payment_total' => $this->context->getCurrentLocale()->formatPrice($cart->getOrderTotal(true, Cart::BOTH), 'KES'),
                'sandbox' => $this->module->settings()->environment() === 'sandbox',
            ]);
            $this->setTemplate('module:kopokopo/views/templates/front/payment_execution.tpl');
        } catch (PaymentException $exception) {
            $this->errors[] = $this->module->l($exception->getMessage());
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
        }
    }
}
