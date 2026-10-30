<?php
/** Exercise the real module guard with PrestaShop 9's configuration contract. */
define('_PS_VERSION_', '9.2.0');

class PaymentModule
{
    protected array $_errors = [];
    public function l(string $text): string { return $text; }
    public function install() { throw new LogicException('Rejected requirements reached core installation.'); }
}

class Configuration
{
    public static array $data = [];
    public static function get(string $name)
    {
        // Removed in PrestaShop 9; callers must not rely on this legacy setting.
        if ($name === 'PS_SSL_ENABLED_EVERYWHERE') {
            throw new LogicException('Requirements read the removed SSL-on-all-pages setting.');
        }
        return self::$data[$name] ?? false;
    }
}

class Shop
{
    public static bool $multistore = false;
    public static function isFeatureActive(): bool { return self::$multistore; }
}

require dirname(__DIR__) . '/kopokopo/kopokopo.php';
require dirname(__DIR__) . '/kopokopo/upgrade/upgrade-2.0.2.php';
require dirname(__DIR__) . '/kopokopo/upgrade/upgrade-2.0.3.php';
$module = (new ReflectionClass(Kopokopo::class))->newInstanceWithoutConstructor();
function checkRequirements(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

// A normal fresh PrestaShop 9 shop has only PS_SSL_ENABLED. A stale legacy key
// from an older shop must have no effect either, including when set to zero.
foreach ([[], ['PS_SSL_ENABLED_EVERYWHERE' => 0], ['PS_SSL_ENABLED_EVERYWHERE' => 1]] as $legacy) {
    foreach ([1, '1', true] as $enabled) {
        Configuration::$data = ['PS_SSL_ENABLED' => $enabled] + $legacy;
        checkRequirements($module->requirements() === [], 'HTTPS-enabled PrestaShop 9 shop was rejected.');
        checkRequirements(upgrade_module_2_0_2($module), 'HTTPS-enabled shop could not upgrade.');
        checkRequirements(upgrade_module_2_0_3($module), 'HTTPS-enabled shop could not upgrade to 2.0.3.');
    }
}

foreach ([[], ['PS_SSL_ENABLED' => 0], ['PS_SSL_ENABLED' => '0'], ['PS_SSL_ENABLED' => false]] as $disabled) {
    Configuration::$data = $disabled + ['PS_SSL_ENABLED_EVERYWHERE' => 1];
    $errors = $module->requirements();
    checkRequirements(count($errors) === 1 && str_contains($errors[0], 'Enable SSL'), 'Disabled/missing SSL must be rejected.');
    checkRequirements($module->install() === false, 'Installation bypassed the SSL requirement.');
    checkRequirements(!upgrade_module_2_0_2($module), 'Upgrade bypassed the SSL requirement.');
    checkRequirements(!upgrade_module_2_0_3($module), '2.0.3 upgrade bypassed the SSL requirement.');
}

Configuration::$data = ['PS_SSL_ENABLED' => 1];
Shop::$multistore = true;
checkRequirements(count($module->requirements()) === 1, 'Multistore restriction was lost.');
echo "Requirements OK: native PrestaShop 9 SSL setting accepted, legacy setting ignored, disabled SSL blocks installation, multistore rejected.\n";
