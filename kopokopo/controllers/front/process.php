<?php
use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Infrastructure\AttemptRepository;

class KopokopoProcessModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new PaymentException('Please submit the payment form.', 405);
            }
            $this->module->assertCheckout();
            $this->module->assertCsrf();
            $phone = Tools::getValue('phone_number', '');
            if (!is_string($phone)) {
                throw new PaymentException('Enter a valid phone number.');
            }
            // Cart identity, customer, amount, currency, and callback are never taken from POST.
            $attempt = $this->module->payments()->initiate((int) $this->context->cart->id,
                (int) $this->context->customer->id, $phone,
                fn (string $id): string => $this->context->link->getModuleLink('kopokopo', 'callback', ['attempt' => $id], true));
            Tools::redirect($this->context->link->getModuleLink('kopokopo', 'paymentstatus', ['attempt' => $attempt['id']], true));
        } catch (PaymentException $exception) {
            $this->errors[] = $this->module->l($exception->getMessage());
        } catch (Throwable) {
            // A persisted attempt may already have been sent; return to its status instead of inviting another payment.
            $attempt = (new AttemptRepository())->openForCart((int) $this->context->shop->id, (int) $this->context->cart->id);
            if ($attempt && (int) $attempt['customer_id'] === (int) $this->context->customer->id) {
                Tools::redirect($this->context->link->getModuleLink('kopokopo', 'paymentstatus', ['attempt' => $attempt['id']], true));
            }
            $this->errors[] = $this->module->l('Payment could not start. Please contact the merchant.');
        }
        $this->redirectWithNotifications($this->context->link->getModuleLink('kopokopo', 'payment', [], true));
    }
}
