<?php
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once __DIR__ . '/bootstrap.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use SimiyuSamuel\Kopokopo\Api\Client;
use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Domain\Payments;
use SimiyuSamuel\Kopokopo\Domain\Values;
use SimiyuSamuel\Kopokopo\Domain\Webhook;
use SimiyuSamuel\Kopokopo\Infrastructure\AttemptRepository;
use SimiyuSamuel\Kopokopo\Infrastructure\Settings;
use SimiyuSamuel\Kopokopo\Infrastructure\ShopOrders;

class Kopokopo extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'kopokopo';
        $this->tab = 'payments_gateways';
        $this->version = '2.0.0';
        $this->author = 'Samuel Simiyu and contributors';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '9.2.0', 'max' => '9.2.99'];
        $this->controllers = ['payment', 'process', 'paymentstatus', 'callback', 'reconcile'];
        parent::__construct();
        $this->displayName = $this->l('Kopo Kopo M-PESA');
        $this->description = $this->l('Accept Kenyan shilling payments with Kopo Kopo API v2 STK Push.');
        $this->confirmUninstall = $this->l('Payment records and settings will be retained for reconciliation. Uninstall?');
    }

    public function requirements(): array
    {
        $errors = [];
        if (PHP_VERSION_ID < 80200 || PHP_VERSION_ID >= 80600) {
            $errors[] = $this->l('PHP 8.2 through 8.5 is required.');
        }
        foreach (['curl', 'json', 'openssl'] as $extension) {
            if (!extension_loaded($extension)) {
                $errors[] = sprintf($this->l('Required PHP extension: %s.'), $extension);
            }
        }
        if (Shop::isFeatureActive()) {
            $errors[] = $this->l('This release supports single-store installations. Multistore is not supported.');
        }
        if (!Configuration::get('PS_SSL_ENABLED') || !Configuration::get('PS_SSL_ENABLED_EVERYWHERE')) {
            $errors[] = $this->l('Enable HTTPS and SSL on all pages in Shop Parameters > General.');
        }
        return $errors;
    }

    public function install()
    {
        if ($errors = $this->requirements()) {
            $this->_errors = array_merge($this->_errors, $errors);
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        try {
            return $this->installV2();
        } catch (Throwable) {
            $this->_errors[] = $this->l('Could not initialize Kopo Kopo. Check the database and server requirements.');
            return false;
        }
    }

    public function installV2(): bool
    {
        if (!(new AttemptRepository())->install()) {
            return false;
        }
        $this->settings()->initialize();
        return $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }

    public function uninstall()
    {
        // Financial records and encrypted settings intentionally survive uninstall/reinstall.
        return parent::uninstall();
    }

    public function settings(?int $shop = null): Settings
    {
        return new Settings($shop ?? (int) $this->context->shop->id);
    }

    public function payments(?string $environment = null, ?int $shop = null): Payments
    {
        $settings = $this->settings($shop);
        $environment ??= $settings->environment();
        return new Payments(new AttemptRepository(), $settings->client($environment),
            new ShopOrders($this, $settings->shop), $settings->forEnvironment($environment));
    }

    public function available(): bool
    {
        try {
            return $this->active && !$this->requirements() && $this->settings()->ready()
                && $this->context->currency->iso_code === 'KES';
        } catch (Throwable) {
            return false;
        }
    }

    public function assertCheckout(): void
    {
        if (!$this->available() || !Validate::isLoadedObject($this->context->cart)
            || !Validate::isLoadedObject($this->context->customer)
            || (int) $this->context->cart->id_customer !== (int) $this->context->customer->id
            || (int) $this->context->cart->id_shop !== (int) $this->context->shop->id) {
            throw new PaymentException('This payment method is unavailable. Please return to checkout.', 403);
        }
        foreach (Module::getPaymentModules() as $module) {
            if ((int) $module['id_module'] === (int) $this->id) {
                return;
            }
        }
        throw new PaymentException('This payment method is not enabled for your checkout.', 403);
    }

    public function hookPaymentOptions($params = [])
    {
        if (!$this->available()) {
            return [];
        }
        $option = new PaymentOption();
        $option->setCallToActionText($this->l('Pay with M-PESA'))
            ->setModuleName($this->name)
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true));
        return [$option];
    }

    public function hookPaymentReturn($params)
    {
        if (!isset($params['order']) || $params['order']->module !== $this->name) {
            return '';
        }
        $this->context->smarty->assign('kopokopo_paid', $params['order']->hasBeenPaid());
        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    public function csrf(): string
    {
        return hash_hmac('sha256', 'checkout:' . (int) $this->context->cart->id . ':' . (int) $this->context->customer->id
            . ':' . $this->context->customer->secure_key, _COOKIE_KEY_);
    }

    public function assertCsrf(): void
    {
        $token = Tools::getValue('form_token');
        if (!is_string($token) || !hash_equals($this->csrf(), $token)) {
            throw new PaymentException('Your checkout form expired. Please reload and try again.', 403);
        }
    }

    public function ownedAttempt(string $id): array
    {
        $attempt = Values::attemptId($id) ? (new AttemptRepository())->find($id) : null;
        if (!$attempt || !Validate::isLoadedObject($this->context->customer)
            || (int) $attempt['customer_id'] !== (int) $this->context->customer->id
            || (int) $attempt['shop_id'] !== (int) $this->context->shop->id) {
            throw new PaymentException('Payment attempt not found. Please sign in or contact the merchant.', 404);
        }
        return $attempt;
    }

    public function statusData(array $attempt): array
    {
        $messages = [
            'initiating' => $this->l('Your payment request is being sent. Please wait.'),
            'pending' => $this->l('Check your phone and enter your M-PESA PIN. We are waiting for confirmation.'),
            'unknown' => $this->l('We are checking whether your payment was received. Do not pay again. Contact the merchant if this remains unresolved.'),
            'confirmed' => $this->l('Your payment was received. Your order is being confirmed.'),
            'completing' => $this->l('Your payment was received. Your order is being confirmed.'),
            'completed' => $this->l('Payment received. Your order is confirmed.'),
            'failed' => $this->l('This payment request was not completed. You can return to checkout to try again.'),
            'review' => $this->l('This payment needs the merchant to review it. Do not pay again. Please contact the merchant with your payment reference.'),
        ];
        $url = null;
        if ($attempt['state'] === 'completed' && $attempt['order_id']) {
            $customer = new Customer((int) $attempt['customer_id']);
            $url = $this->context->link->getPageLink('order-confirmation', true, null, [
                'id_cart' => (int) $attempt['cart_id'], 'id_module' => (int) $this->id,
                'id_order' => (int) $attempt['order_id'], 'key' => $customer->secure_key]);
        }
        return ['state' => $attempt['state'], 'message' => $messages[$attempt['state']] ?? $messages['unknown'],
            'reference' => $attempt['id'], 'receipt' => $attempt['receipt'], 'redirect' => $url];
    }

    public function getContent()
    {
        $settings = $this->settings();
        $repository = new AttemptRepository();
        $notice = '';
        try {
            $settings->initialize();
            if (Tools::isSubmit('kopokopo_action')) {
                $token = Tools::getValue('form_token');
                if (!is_string($token) || !hash_equals(Tools::getAdminTokenLite('AdminModules'), $token)) {
                    throw new PaymentException('Your settings form expired. Reload the page.', 403);
                }
                if ($this->requirements()) {
                    throw new PaymentException('Please meet the server requirements shown below first.');
                }
                $action = Tools::getValue('kopokopo_action');
                if ($action === 'save') {
                    $this->saveSettings($settings, $repository);
                    $notice = $this->displayConfirmation($this->l('Settings saved. Test the connection next.'));
                } elseif ($action === 'test') {
                    $settings->client($settings->environment())->token(true);
                    $notice = $this->displayConfirmation($this->l('Authentication succeeded. Complete a sandbox payment to verify your till and callbacks.'));
                } elseif ($action === 'reconcile') {
                    $id = (string) Tools::getValue('attempt_id');
                    $attempt = Values::attemptId($id) ? $repository->find($id) : null;
                    if (!$attempt || (int) $attempt['shop_id'] !== $settings->shop) {
                        throw new PaymentException('Payment attempt not found.');
                    }
                    $this->payments($attempt['environment'])->reconcile($id);
                    $notice = $this->displayConfirmation($this->l('Payment status checked. See the payment list below.'));
                } elseif ($action === 'recover') {
                    $this->recover($settings, $repository);
                    $notice = $this->displayConfirmation($this->l('Provider request linked and checked. See the payment list below.'));
                } else {
                    throw new PaymentException('Unknown configuration action.');
                }
            }
        } catch (PaymentException $exception) {
            $notice = $this->displayError($this->l($exception->getMessage()));
        } catch (Throwable) {
            $notice = $this->displayError($this->l('The operation could not complete. Check credentials, connectivity, and the payment list before trying again.'));
        }
        $environment = $settings->environment();
        $prefix = strtoupper($environment) . '_';
        $this->context->smarty->assign([
            'form_action' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]),
            'form_token' => Tools::getAdminTokenLite('AdminModules'), 'environment' => $environment,
            'till' => $settings->get($prefix . 'TILL'), 'client_id' => $settings->get($prefix . 'CLIENT_ID'),
            'requirements' => $this->requirements(), 'attempts' => $repository->recent($settings->shop),
            'cron_url' => $this->context->link->getModuleLink($this->name, 'reconcile', [], true),
            'cron_token' => $settings->secret('CRON_TOKEN'),
        ]);
        return $notice . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    private function saveSettings(Settings $settings, AttemptRepository $repository): void
    {
        $environment = Tools::getValue('environment');
        if (!in_array($environment, ['sandbox', 'production'], true)) {
            throw new PaymentException('Choose sandbox or production.');
        }
        // Switching environment loads its existing settings; it never copies credentials across environments.
        if ($environment !== $settings->environment()) {
            $settings->set('ENVIRONMENT', $environment);
            return;
        }
        $data = [];
        foreach (['till', 'client_id', 'client_secret', 'api_key'] as $key) {
            $value = Tools::getValue($key, '');
            if (!is_string($value) || strlen($value) > 512 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new PaymentException('Invalid credentials.');
            }
            $data[$key] = trim($value);
        }
        if (!preg_match('/^[A-Za-z0-9]{1,32}$/D', $data['till']) || $data['client_id'] === '') {
            throw new PaymentException('Enter your till number and OAuth client ID.');
        }
        $current = $settings->forEnvironment($environment);
        foreach (['client_secret', 'api_key'] as $key) {
            if ($data[$key] === '') {
                $data[$key] = $current[$key];
            }
            if ($data[$key] === '') {
                throw new PaymentException('Enter the client secret and API signing key.');
            }
        }
        $changed = false;
        foreach ($data as $key => $value) {
            $changed = $changed || $value !== $current[$key];
        }
        if ($changed && $repository->hasOpen($settings->shop, $environment)) {
            throw new PaymentException('Resolve pending payments before changing credentials or the till for this environment.');
        }
        $prefix = strtoupper($environment) . '_';
        $settings->set($prefix . 'TILL', $data['till']);
        $settings->set($prefix . 'CLIENT_ID', $data['client_id']);
        $settings->setSecret($prefix . 'CLIENT_SECRET', $data['client_secret']);
        $settings->setSecret($prefix . 'API_KEY', $data['api_key']);
        if ($changed) {
            $settings->set($prefix . 'TOKEN', '');
        }
    }

    private function recover(Settings $settings, AttemptRepository $repository): void
    {
        $id = (string) Tools::getValue('attempt_id');
        $providerId = (string) Tools::getValue('provider_id');
        $attempt = Values::attemptId($id) ? $repository->find($id) : null;
        if (!$attempt || (int) $attempt['shop_id'] !== $settings->shop || !Values::uuid($providerId)
            || !in_array($attempt['state'], ['unknown', 'initiating'], true) || $attempt['provider_id']) {
            throw new PaymentException('Select an unresolved attempt without a provider request ID, and enter its provider UUID.');
        }
        $url = Client::base($attempt['environment']) . '/api/v2/incoming_payments/' . $providerId;
        $body = $settings->client($attempt['environment'])->query($url);
        Webhook::payment($body, $attempt);
        $repository->locked($settings->shop, (int) $attempt['cart_id'], function () use ($repository, $attempt, $providerId, $url): void {
            $latest = $repository->find($attempt['id']);
            if ($latest['provider_id'] && $latest['provider_id'] !== $providerId) {
                throw new PaymentException('A callback already linked a different provider request.');
            }
            $repository->update($attempt['id'], ['provider_id' => $providerId, 'provider_url' => $url]);
        });
        $this->payments($attempt['environment'])->receive($id, $body);
    }
}
