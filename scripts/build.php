<?php
/** Produce a deterministic, dependency-free Module Manager ZIP. */
$root = dirname(__DIR__);
$manifest = simplexml_load_file($root . '/kopokopo/config.xml');
$version = (string) $manifest->version;
if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) throw new RuntimeException('Invalid module version.');
$destination = $argv[1] ?? $root . '/dist';
if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new RuntimeException('Cannot create build directory.');
$target = $destination . '/kopokopo-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create ZIP.');
$files = [];
$allowedRoot = ['kopokopo.php', 'bootstrap.php', 'index.php', 'config.xml', '.htaccess', 'callbackText.php', 'logo.svg', 'logo.png'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/kopokopo', FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || $file->isLink()) continue;
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/kopokopo/')));
    if (in_array($path, $allowedRoot, true) || preg_match('#^(src|controllers|upgrade|views)/[A-Za-z0-9_/.-]+\.(php|tpl|js|css|svg|png)$#D', $path)) {
        $files['kopokopo/' . $path] = $file->getPathname();
    }
}
$files += [
    'kopokopo/LICENSE' => $root . '/LICENSE',
    'kopokopo/NOTICE' => $root . '/NOTICE',
    'kopokopo/AUTHORS.md' => $root . '/AUTHORS.md',
    'kopokopo/README.md' => $root . '/README.md',
    'kopokopo/INSTALL.md' => $root . '/docs/installation.md',
];
ksort($files);
foreach ($files as $name => $source) {
    if (!$zip->addFromString($name, file_get_contents($source))) throw new RuntimeException('Cannot add ' . $name);
    $zip->setMtimeName($name, 946684800);
    $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
}
if (!$zip->close()) throw new RuntimeException('Cannot finalize ZIP.');
$digest = hash_file('sha256', $target);
file_put_contents($target . '.sha256', $digest . '  ' . basename($target) . "\n");
echo $target . "\nSHA256 " . $digest . "\n";
