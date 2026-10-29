<?php
$root = dirname(__DIR__);
$count = 0;
foreach (['kopokopo', 'scripts', 'tests'] as $folder) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') continue;
        $process = proc_open([PHP_BINARY, '-l', $file->getPathname()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException($output);
        ++$count;
    }
}
$manifest = simplexml_load_file($root . '/kopokopo/config.xml');
$module = file_get_contents($root . '/kopokopo/kopokopo.php');
if (!str_contains($module, "\$this->version = '" . $manifest->version . "'")) throw new RuntimeException('Module and manifest versions differ.');
json_decode(file_get_contents($root . '/kopokopo/composer.json'), true, 16, JSON_THROW_ON_ERROR);
json_decode(file_get_contents($root . '/kopokopo/composer.lock'), true, 32, JSON_THROW_ON_ERROR);
if (is_dir($root . '/kopokopo/vendor') || is_dir($root . '/kopokopo/logs')) throw new RuntimeException('Remove runtime/vendor files before release.');
echo "Syntax OK: $count PHP files. Manifest and dependency metadata OK.\n";
