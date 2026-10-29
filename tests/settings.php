<?php
require dirname(__DIR__) . '/kopokopo/bootstrap.php';
use SimiyuSamuel\Kopokopo\Infrastructure\Settings;

// Exercise the real encryption code with a fake PrestaShop configuration store.
define('_COOKIE_KEY_', 'test-only-application-key-do-not-use-for-real-shops');
class Configuration {
    public static array $data = [];
    public static function get($name, $language = null, $group = null, $shop = null) { return self::$data[$shop][$name] ?? false; }
    public static function updateValue($name, $value, $html = false, $group = null, $shop = null) { self::$data[$shop][$name] = $value; return true; }
}
function ensure(bool $condition): void { if (!$condition) throw new RuntimeException('Settings assertion failed.'); }
$settings = new Settings(1); $settings->initialize();
ensure($settings->environment() === 'sandbox'); ensure(strlen($settings->secret('CRON_TOKEN')) === 64);
$cron = $settings->secret('CRON_TOKEN'); $settings->initialize(); ensure($cron === $settings->secret('CRON_TOKEN'));
$settings->setSecret('SANDBOX_API_KEY', 'private-signing-key');
ensure($settings->secret('SANDBOX_API_KEY') === 'private-signing-key');
ensure(!str_contains(json_encode(Configuration::$data), 'private-signing-key'));
$settings->setSecret('SANDBOX_CLIENT_SECRET', 'private-oauth-secret');
ensure($settings->secret('SANDBOX_CLIENT_SECRET') === 'private-oauth-secret');
ensure($settings->secret('PRODUCTION_API_KEY') === '');
$original = Configuration::$data[1]['KOPOKOPO_SANDBOX_API_KEY'];
foreach ([['shop' => 2, 'field' => 'SANDBOX_API_KEY'], ['shop' => 1, 'field' => 'PRODUCTION_API_KEY']] as $case) {
    Configuration::$data[$case['shop']]['KOPOKOPO_' . $case['field']] = $original;
    try { (new Settings($case['shop']))->secret($case['field']); throw new LogicException('Ciphertext crossed scope.'); }
    catch (LogicException $error) { throw $error; } catch (RuntimeException) {}
}
$bytes = base64_decode($original); $bytes[30] = chr(ord($bytes[30]) ^ 1);
Configuration::$data[1]['KOPOKOPO_SANDBOX_API_KEY'] = base64_encode($bytes);
try { $settings->secret('SANDBOX_API_KEY'); throw new LogicException('Tampering was accepted.'); }
catch (LogicException $error) { throw $error; } catch (RuntimeException) {}
echo "Settings OK: encrypted round-trip, idempotent initialization, tamper rejection, environment/shop separation.\n";
