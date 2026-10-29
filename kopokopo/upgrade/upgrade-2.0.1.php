<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_1($module)
{
    // Register scoped branding assets without resetting credentials or payment records.
    return !$module->requirements() && $module->installV2();
}
