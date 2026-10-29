<?php
/** Destructive smoke test for a disposable shop with sample products. Never use a client database. */
$shopRoot = getenv('KOPOKOPO_TEST_SHOP');
if (!$shopRoot || getenv('KOPOKOPO_TEST_ALLOW_MUTATION') !== '1') {
    fwrite(STDERR, "Set KOPOKOPO_TEST_SHOP and KOPOKOPO_TEST_ALLOW_MUTATION=1 for a disposable shop.\n");
    exit(1);
}
chdir($shopRoot);
$_SERVER['HTTP_HOST'] = 'localhost:8099';
define('_PS_ADMIN_DIR_', $shopRoot . '/admin-dev');
define('_PS_MODE_DEV_', false);
require $shopRoot . '/config/config.inc.php';
if (!preg_match('/test|ci/i', _DB_NAME_) || version_compare(_PS_VERSION_, '9.2.0', '<')) {
    throw new RuntimeException('Use a disposable PrestaShop 9.2 database with test or ci in its name.');
}
$kernel = new AdminKernel('prod', false);
$kernel->boot();
function verify(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
Configuration::updateValue('PS_SSL_ENABLED', 1);
Configuration::updateValue('PS_SSL_ENABLED_EVERYWHERE', 1);
Configuration::updateValue('PS_SHOP_ENABLE', 1);
Configuration::updateValue('PS_MAIL_METHOD', 3); // Never send mail from test fixtures.
$context = Context::getContext();
$context->container = $kernel->getContainer();
$context->employee = new Employee(1);
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$context->shop = new Shop(1);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$context->link = new Link('https://', 'https://');
$module = Module::getInstanceByName('kopokopo');
verify($module instanceof Kopokopo, 'Module package is missing under modules/kopokopo.');
if (Module::isInstalled('kopokopo')) {
    verify($module->uninstall(), 'Could not reset disposable module installation.');
}
verify($module->install(), 'Module install failed: ' . implode('; ', $module->getErrors()));
// Simulate a new web request after install; core keeps the old instance's active flag cached.
Module::resetStaticCache();
$module = Module::getInstanceByName('kopokopo');
$settings = $module->settings();
$settings->set('SANDBOX_TILL', 'K000001');
$settings->set('SANDBOX_CLIENT_ID', 'test-client');
$settings->setSecret('SANDBOX_CLIENT_SECRET', 'test-secret');
$settings->setSecret('SANDBOX_API_KEY', 'test-key');
verify($settings->ready(), 'Saved settings not ready.');

use SimiyuSamuel\Kopokopo\Api\Client;
use SimiyuSamuel\Kopokopo\Api\Gateway;
use SimiyuSamuel\Kopokopo\Domain\Payments;
use SimiyuSamuel\Kopokopo\Domain\Values;
use SimiyuSamuel\Kopokopo\Infrastructure\AttemptRepository;
use SimiyuSamuel\Kopokopo\Infrastructure\ShopOrders;

$currencyId = (int) Currency::getIdByIsoCode('KES');
verify($currencyId > 0, 'Install KES currency in the disposable shop.');
$context->currency = new Currency($currencyId);
$customer = new Customer();
$customer->id_shop = 1; $customer->id_shop_group = 1; $customer->id_lang = $context->language->id;
$customer->firstname = 'Module'; $customer->lastname = 'SmokeTest';
$customer->email = 'kopokopo-test-' . bin2hex(random_bytes(5)) . '@example.invalid';
$customer->passwd = password_hash('DisposableTest2026', PASSWORD_BCRYPT);
$customer->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP'); $customer->active = 1;
verify($customer->add(), 'Could not create fixture customer.');
$customer->addGroups([$customer->id_default_group]);
$context->customer = $customer;
$address = new Address();
$address->id_customer = $customer->id; $address->id_country = (int) Country::getByIso('KE');
$address->alias = 'Test address'; $address->firstname = $customer->firstname; $address->lastname = $customer->lastname;
$address->address1 = 'Test Road 1'; $address->city = 'Nairobi'; $address->postcode = '00100'; $address->phone_mobile = '0712345678';
verify($address->add(), 'Could not create fixture address.');
$context->country = new Country($address->id_country);
$carrier = new Carrier();
$carrier->name = 'Kopo Kopo test shipping'; $carrier->active = 1; $carrier->is_free = 1;
$carrier->shipping_handling = 0; $carrier->shipping_method = Carrier::SHIPPING_METHOD_PRICE;
$carrier->range_behavior = 0; $carrier->need_range = 0;
foreach (Language::getLanguages(false) as $language) $carrier->delay[$language['id_lang']] = 'Test delivery';
verify($carrier->add(), 'Could not create fixture carrier.');
$carrier = new Carrier((int) $carrier->id); // Core assigns id_reference in SQL during add().
$carrier->addZone($context->country->id_zone);
$carrier->setGroups(array_column(Group::getGroups($context->language->id), 'id_group'));
$cart = new Cart();
$cart->id_shop = 1; $cart->id_shop_group = 1; $cart->id_customer = $customer->id;
$cart->id_currency = $currencyId; $cart->id_lang = $context->language->id;
$cart->id_address_delivery = $address->id; $cart->id_address_invoice = $address->id;
$cart->id_carrier = $carrier->id; $cart->secure_key = $customer->secure_key;
verify($cart->add(), 'Could not create fixture cart.');
$context->cart = $cart;
$productId = (int) Db::getInstance()->getValue('SELECT p.id_product FROM `' . _DB_PREFIX_ . 'product` p
    INNER JOIN `' . _DB_PREFIX_ . 'stock_available` s ON s.id_product = p.id_product AND s.id_product_attribute = 0
    WHERE p.active = 1 AND p.available_for_order = 1 AND s.quantity > 3
    AND NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'product_attribute` pa WHERE pa.id_product = p.id_product)');
verify($productId > 0, 'Install sample products without combinations for this test.');
verify($cart->updateQty(1, $productId) === true, 'Could not populate fixture cart.');
$cart->setDeliveryOption([$address->id => $carrier->id . ',']); $cart->update();
Db::getInstance()->insert('module_carrier', ['id_module' => (int) $module->id, 'id_shop' => 1,
    'id_reference' => (int) $carrier->id_reference], false, true, Db::INSERT_IGNORE);
$admin = $context->employee;
$context->employee = null;
$module->assertCheckout();
Db::getInstance()->delete('module_carrier', 'id_module = ' . (int) $module->id . ' AND id_reference = ' . (int) $carrier->id_reference);
$rejectedPreference = false;
try { $module->assertCheckout(); } catch (\SimiyuSamuel\Kopokopo\Domain\PaymentException $error) { $rejectedPreference = $error->httpStatus === 403; }
verify($rejectedPreference, 'Direct checkout bypassed the disabled carrier preference.');
Db::getInstance()->insert('module_carrier', ['id_module' => (int) $module->id, 'id_shop' => 1, 'id_reference' => (int) $carrier->id_reference]);
$context->employee = $admin;
$repository = new AttemptRepository();
$orders = new class(new ShopOrders($module, 1)) implements \SimiyuSamuel\Kopokopo\Domain\Orders {
    public ?Throwable $lastError = null;
    public function __construct(private readonly ShopOrders $inner) {}
    public function snapshot(int $cartId, int $customerId): array { return $this->inner->snapshot($cartId, $customerId); }
    public function reference(): string { return $this->inner->reference(); }
    public function complete(array $attempt): int {
        try { return $this->inner->complete($attempt); }
        catch (Throwable $error) { $this->lastError = $error; throw $error; }
    }
};
$gateway = new class implements Gateway {
    public string $id;
    public function __construct() { $this->id = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6))); }
    public function initiate(array $payload): array { return ['id' => $this->id, 'url' => Client::base('sandbox') . '/api/v2/incoming_payments/' . $this->id]; }
    public function query(string $url): array { throw new RuntimeException('No live provider I/O in this smoke test.'); }
};
$service = new Payments($repository, $gateway, $orders, $settings->forEnvironment('sandbox'));
$attempt = $service->initiate($cart->id, $customer->id, '0712345678', fn ($id) => 'https://example.invalid/callback?attempt=' . $id);
verify($attempt['state'] === 'pending', 'Real DB attempt insert failed.');
verify($repository->find($attempt['id'])['reason'] === '', 'Empty reason must be preserved, not converted to SQL NULL.');
verify($attempt['provider_id'] === $gateway->id, 'Provider identity not persisted.');
$serverParts = explode(':', _DB_SERVER_);
$probe = new PDO('mysql:host=' . $serverParts[0] . ';port=' . ($serverParts[1] ?? 3306) . ';dbname=' . _DB_NAME_, _DB_USER_, _DB_PASSWD_);
$lockName = 'k2_' . substr(hash('sha256', _DB_NAME_ . _DB_PREFIX_ . 'kopokopo_attempt:1:' . $cart->id), 0, 50);
$repository->locked(1, $cart->id, function () use ($probe, $lockName): void {
    verify((int) $probe->query("SELECT GET_LOCK('" . $lockName . "', 0)")->fetchColumn() === 0, 'Another DB connection acquired the cart lock.');
});
try {
    $repository->locked(1, $cart->id, function (): never { throw new RuntimeException('Expected lock-release probe.'); });
} catch (RuntimeException $error) {
    verify($error->getMessage() === 'Expected lock-release probe.', 'Unexpected lock exception.');
}
verify((int) $probe->query("SELECT GET_LOCK('" . $lockName . "', 0)")->fetchColumn() === 1, 'Cart lock leaked after an exception.');
$probe->query("SELECT RELEASE_LOCK('" . $lockName . "')");
$collision = $attempt;
$collision['id'] = bin2hex(random_bytes(16));
try { $repository->create($collision); } catch (Throwable) {}
verify($repository->find($collision['id']) === null, 'Database accepted a duplicate provider request.');
verify($service->initiate($cart->id, $customer->id, '0712345678', fn () => '')['id'] === $attempt['id'], 'Duplicate initiation was not suppressed.');
$payload = ['data' => ['id' => $gateway->id, 'type' => 'incoming_payment', 'attributes' => [
    'status' => 'Success', 'metadata' => ['attempt_id' => $attempt['id']], 'event' => ['resource' => [
        'reference' => 'T' . strtoupper(bin2hex(random_bytes(5))), 'amount' => Values::decimal((int) $attempt['amount_minor']),
        'currency' => 'KES', 'till_number' => 'K000001', 'status' => 'Received',
    ]],
]]];
$result = $service->receive($attempt['id'], $payload);
verify($result['state'] === 'completed', 'Real core order completion failed; state: ' . $result['state'] . ', reason: ' . $result['reason'] . '; ' . $orders->lastError?->getMessage());
$before = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . $cart->id);
verify($before >= 1, 'No order persisted.');
$service->receive($attempt['id'], $payload);
$after = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE id_cart = ' . $cart->id);
verify($before === $after, 'Duplicate callback created an extra order.');
$receipt = (string) Db::getInstance()->getValue("SELECT transaction_id FROM `" . _DB_PREFIX_ . "order_payment` WHERE order_reference = '" . pSQL($attempt['order_reference']) . "'");
verify($receipt === $payload['data']['attributes']['event']['resource']['reference'], 'Receipt not attached to native order payment.');
verify(count($module->hookPaymentOptions()) === 1, 'Payment option not returned.');
$content = $module->getContent();
verify(str_contains($content, 'Payment recovery') && str_contains($content, 'Recent payment attempts'), 'Admin configuration failed to render.');
foreach (['front/payment_execution.tpl', 'front/paymentstatus.tpl', 'hook/payment_return.tpl'] as $template) {
    $context->smarty->createTemplate('module:kopokopo/views/templates/' . $template)->compileTemplateSource();
}
Configuration::updateValue('KOPOKOPO_CLIENT_SECRET', 'legacy-test-secret');
$module->registerHook('footer');
$legacyLog = $shopRoot . '/modules/kopokopo/logs/callback_log.txt';
if (!is_dir(dirname($legacyLog))) mkdir(dirname($legacyLog), 0755);
file_put_contents($legacyLog, 'Synthetic legacy debug log.');
$module->installed = true;
$module->database_version = '1.0.0';
Module::upgradeModuleVersion('kopokopo', '1.0.0');
verify(Module::initUpgradeModule($module), 'Core did not discover the upgrade.');
$upgrade = $module->runUpgradeModule();
verify($upgrade['success'] && (int) $upgrade['number_upgraded'] === 1, 'Core module upgrade failed: ' . json_encode($upgrade));
verify(!is_file($legacyLog) && !Configuration::get('KOPOKOPO_CLIENT_SECRET'), 'Upgrade retained legacy logs/secrets.');
verify(!$module->isRegisteredInHook('footer'), 'Upgrade retained the obsolete hook.');
$fixtureDirectory = dirname(__DIR__) . '/.work';
if (!is_dir($fixtureDirectory)) mkdir($fixtureDirectory, 0755);
file_put_contents($fixtureDirectory . '/http-fixture.json', json_encode([
    'id' => $attempt['id'], 'payload' => $payload, 'key' => 'test-key',
    'cron_token' => $settings->secret('CRON_TOKEN'),
], JSON_THROW_ON_ERROR));
verify($module->uninstall(), 'Uninstall failed.');
verify($repository->find($attempt['id']) !== null, 'Uninstall deleted financial data.');
verify($module->install(), 'Reinstall failed.');
verify($repository->find($attempt['id'])['state'] === 'completed', 'Reinstall lost financial state.');
verify($module->settings()->ready(), 'Reinstall lost saved credentials.');
echo 'PrestaShop integration OK: ' . _PS_VERSION_ . ', PHP ' . PHP_VERSION . ", real install/admin/templates/DB locks/constraints/order/receipt/deduplication/upgrade/data retention.\n";
