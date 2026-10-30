<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_3($module)
{
    // New requests carry reconciliation metadata; existing records/settings need no migration.
    return !$module->requirements();
}
