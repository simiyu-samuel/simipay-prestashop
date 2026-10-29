<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_0($module)
{
    if ($module->requirements() || !$module->installV2()) {
        return false;
    }
    $module->unregisterHook('footer');
    // Old credentials are intentionally not guessed to be sandbox or production credentials.
    foreach (['TILL_NUMBER', 'CLIENT_ID', 'CLIENT_SECRET', 'API_KEY', 'ACCESS_TOKEN', 'TOKEN_EXPIRY'] as $key) {
        Configuration::deleteByName('KOPOKOPO_' . $key);
    }
    $legacyLog = dirname(__DIR__) . '/logs/callback_log.txt';
    if (is_file($legacyLog) && !unlink($legacyLog)) {
        return false;
    }
    return true;
}
