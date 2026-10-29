<?php
use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Domain\Values;
use SimiyuSamuel\Kopokopo\Domain\Webhook;
use SimiyuSamuel\Kopokopo\Infrastructure\AttemptRepository;

class KopokopoCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function postProcess()
    {
        $status = 200;
        $reply = ['status' => 'received'];
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                header('Allow: POST');
                throw new PaymentException('POST required.', 405);
            }
            $id = Tools::getValue('attempt', '');
            $attempt = is_string($id) && Values::attemptId($id) ? (new AttemptRepository())->find($id) : null;
            if (!$attempt || (int) $attempt['shop_id'] !== (int) $this->context->shop->id) {
                throw new PaymentException('Unknown payment attempt.', 404);
            }
            $raw = file_get_contents('php://input', false, null, 0, 65537);
            if ($raw === false) {
                throw new PaymentException('Unreadable callback.', 400);
            }
            $settings = $this->module->settings((int) $attempt['shop_id'])->forEnvironment($attempt['environment']);
            $payload = Webhook::decode($raw, (string) ($_SERVER['HTTP_X_KOPOKOPO_SIGNATURE'] ?? ''), $settings['api_key']);
            $this->module->payments($attempt['environment'], (int) $attempt['shop_id'])->receive($id, $payload);
        } catch (PaymentException $exception) {
            $status = $exception->httpStatus;
            $reply = ['status' => 'rejected'];
        } catch (Throwable) {
            $status = 503;
            $reply = ['status' => 'retry_later'];
        }
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        exit(json_encode($reply));
    }
}
