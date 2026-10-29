<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_2($module)
{
    // No schema/settings changes; finish the native version upgrade with the corrected guard.
    return !$module->requirements();
}
