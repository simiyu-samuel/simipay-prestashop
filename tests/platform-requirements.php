<?php
/** Read-only regression using the native PrestaShop configuration reader. */
$shop = getenv('KOPOKOPO_TEST_SHOP');
if (!$shop || !is_file($shop . '/config/config.inc.php')) {
    throw new RuntimeException('Set KOPOKOPO_TEST_SHOP to an installed disposable PrestaShop shop.');
}
$_SERVER['HTTP_HOST'] = 'localhost';
require $shop . '/config/config.inc.php';
if (!preg_match('/demo|test|ci/i', _DB_NAME_) || !str_starts_with(_PS_VERSION_, '9.2.')) {
    throw new RuntimeException('Use a disposable PrestaShop 9.2 demo/test/ci database.');
}
// Load the current repository code without replacing the installed module files.
require dirname(__DIR__) . '/kopokopo/kopokopo.php';
$module = new Kopokopo();
Configuration::loadConfiguration();
$saved = [];
foreach (['_cache', '_new_cache_shop', '_new_cache_group', '_new_cache_global'] as $name) {
    $property = new ReflectionProperty(ConfigurationCore::class, $name);
    $saved[] = [$property, $property->getValue()];
}
try {
    foreach (['_new_cache_shop', '_new_cache_group', '_new_cache_global'] as $name) {
        $property = new ReflectionProperty(ConfigurationCore::class, $name);
        $values = $property->getValue();
        unset($values['PS_SSL_ENABLED_EVERYWHERE']);
        $property->setValue(null, $values);
    }
    // Configuration::set changes only this process's cache, never the database.
    Configuration::set('PS_SSL_ENABLED', 1);
    if (Configuration::get('PS_SSL_ENABLED_EVERYWHERE') !== false || $module->requirements() !== []) {
        throw new RuntimeException('Native PrestaShop 9 SSL configuration rejected.');
    }
    Configuration::set('PS_SSL_ENABLED_EVERYWHERE', 0);
    if ($module->requirements() !== []) {
        throw new RuntimeException('Stale legacy SSL setting affected requirements.');
    }
    Configuration::set('PS_SSL_ENABLED', 0);
    $errors = $module->requirements();
    if (count($errors) !== 1 || !str_contains($errors[0], 'Enable SSL')) {
        throw new RuntimeException('Disabled SSL was not rejected.');
    }
} finally {
    foreach ($saved as [$property, $values]) $property->setValue(null, $values);
}
echo 'Native requirements OK on PrestaShop ' . _PS_VERSION_ . ': missing/stale legacy key accepted with SSL enabled; disabled SSL rejected. No database settings or payments changed.' . "\n";
