<?php
$root = dirname(__DIR__);
$version = (string) simplexml_load_file($root . '/kopokopo/config.xml')->version;
$path = $root . '/dist/kopokopo-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($path) !== true) throw new RuntimeException('Run php scripts/build.php first.');
foreach (['kopokopo/kopokopo.php', 'kopokopo/bootstrap.php', 'kopokopo/config.xml', 'kopokopo/.htaccess',
    'kopokopo/upgrade/upgrade-2.0.0.php', 'kopokopo/src/Domain/Payments.php', 'kopokopo/views/js/status.js',
    'kopokopo/upgrade/upgrade-2.0.1.php', 'kopokopo/upgrade/upgrade-2.0.2.php', 'kopokopo/views/templates/_partials/attribution.tpl',
    'kopokopo/views/templates/hook/payment_information.tpl', 'kopokopo/views/css/branding.css',
    'kopokopo/logo.png', 'kopokopo/logo.svg', 'kopokopo/AUTHORS.md', 'kopokopo/NOTICE',
    'kopokopo/INSTALL.md', 'kopokopo/LICENSE'] as $name) {
    if ($zip->locateName($name) === false) throw new RuntimeException('Missing package entry: ' . $name);
}
$logo = getimagesizefromstring($zip->getFromName('kopokopo/logo.png'));
if (!$logo || $logo[0] !== 128 || $logo[1] !== 128 || $logo[2] !== IMAGETYPE_PNG) {
    throw new RuntimeException('Module Manager logo must be a valid 128 × 128 PNG.');
}
for ($i = 0; $i < $zip->numFiles; ++$i) {
    $name = $zip->getNameIndex($i);
    if (!str_starts_with($name, 'kopokopo/') || str_contains($name, '..')
        || preg_match('#/(vendor|logs|tests|\.git)/|\.env|composer\.(json|lock)$#', $name)) {
        throw new RuntimeException('Unexpected package entry: ' . $name);
    }
}
$checksum = explode(' ', trim(file_get_contents($path . '.sha256')))[0];
if (!hash_equals($checksum, hash_file('sha256', $path))) throw new RuntimeException('Checksum mismatch.');
echo 'Package OK: ' . $zip->numFiles . " entries, correct root folder, no runtime data, checksum verified.\n";
