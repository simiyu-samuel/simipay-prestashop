<?php
use SimiyuSamuel\Kopokopo\Domain\PaymentException;

class KopokopoPaymentstatusModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        $json = Tools::getValue('format') === 'json';
        try {
            $id = Tools::getValue('attempt', '');
            if (!is_string($id)) {
                throw new PaymentException('Payment attempt not found.', 404);
            }
            $attempt = $this->module->ownedAttempt($id);
            $data = $this->module->statusData($attempt);
            if ($json) {
                $this->json($data);
            }
            parent::initContent();
            $this->registerJavascript('kopokopo-status', 'modules/kopokopo/views/js/status.js', ['position' => 'bottom', 'priority' => 200]);
            $this->context->smarty->assign([
                'payment_status' => $data,
                'status_url' => $this->context->link->getModuleLink('kopokopo', 'paymentstatus', ['attempt' => $id, 'format' => 'json'], true),
                'checkout_url' => $this->context->link->getPageLink('order', true),
            ]);
            header('Cache-Control: no-store');
            $this->setTemplate('module:kopokopo/views/templates/front/paymentstatus.tpl');
        } catch (PaymentException $exception) {
            if ($json) {
                http_response_code($exception->httpStatus);
                $this->json(['message' => $this->module->l($exception->getMessage())]);
            }
            $this->errors[] = $this->module->l($exception->getMessage());
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true));
        }
    }

    private function json(array $data): never
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        exit(json_encode($data));
    }
}
