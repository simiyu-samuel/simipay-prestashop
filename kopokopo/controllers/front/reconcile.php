<?php
use SimiyuSamuel\Kopokopo\Infrastructure\AttemptRepository;

class KopokopoReconcileModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function postProcess()
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit('{"status":"post_required"}');
        }
        try {
            $token = (string) ($_SERVER['HTTP_X_KOPOKOPO_CRON_TOKEN'] ?? '');
            $expected = $this->module->settings()->secret('CRON_TOKEN');
            if ($expected === '' || !hash_equals($expected, $token)) {
                http_response_code(401);
                exit('{"status":"unauthorized"}');
            }
            $checked = $errors = 0;
            $start = time();
            foreach ((new AttemptRepository())->due((int) $this->context->shop->id) as $attempt) {
                if (time() - $start > 25) {
                    break;
                }
                try {
                    $this->module->payments($attempt['environment'])->reconcile($attempt['id']);
                    ++$checked;
                } catch (Throwable) {
                    ++$errors;
                }
            }
            http_response_code($errors ? 503 : 200);
            exit(json_encode(['checked' => $checked, 'errors' => $errors]));
        } catch (Throwable) {
            http_response_code(503);
            exit('{"status":"retry_later"}');
        }
    }
}
