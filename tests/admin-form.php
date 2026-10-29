<?php
/** Read-only admin CSRF regression against an already installed disposable shop. */
$shop = getenv('KOPOKOPO_TEST_SHOP');
if (!$shop || !is_file($shop . '/config/config.inc.php')) {
    throw new RuntimeException('Set KOPOKOPO_TEST_SHOP to an installed disposable PrestaShop shop.');
}
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
require $shop . '/config/config.inc.php';
if (!preg_match('/demo|test|ci/i', _DB_NAME_)) {
    throw new RuntimeException('Use a disposable demo/test/ci database.');
}
$kernel = new AdminKernel('prod', false);
$kernel->boot();
$context = Context::getContext();
$context->container = $kernel->getContainer();
$context->employee = new Employee(1);
Shop::setContext(Shop::CONTEXT_SHOP, 1);
$context->shop = new Shop(1);
$context->language = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
$context->link = new Link('https://', 'https://');
if (!Module::isInstalled('kopokopo') || !Configuration::get('KOPOKOPO_CRON_TOKEN')) {
    throw new RuntimeException('Install and initialize the module before running this read-only check.');
}
Module::getInstanceByName('kopokopo');

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

$storage = new class implements TokenStorageInterface {
    private array $tokens = [];
    public function getToken(string $id): string { return $this->tokens[$id]; }
    public function setToken(string $id, string $token): void { $this->tokens[$id] = $token; }
    public function removeToken(string $id): ?string {
        $token = $this->tokens[$id] ?? null;
        unset($this->tokens[$id]);
        return $token;
    }
    public function hasToken(string $id): bool { return isset($this->tokens[$id]); }
};
$manager = new CsrfTokenManager(null, $storage);
$module = new class($manager) extends Kopokopo {
    public function __construct(private CsrfTokenManagerInterface $testManager) { parent::__construct(); }
    public function get(string $name): ?object {
        return $name === CsrfTokenManagerInterface::class ? $this->testManager : parent::get($name);
    }
};
$email = $context->employee->email;
$first = $manager->getToken($email)->getValue();
$second = $manager->getToken($email)->getValue();
if ($first === $second) throw new RuntimeException('Expected differently masked Symfony tokens.');
$originalPost = $_POST;
try {
    foreach ([
        [$first, true], [$second, true],
        [$manager->getToken('another-employee@example.invalid')->getValue(), false],
        ['tampered-token', false], ['', false], [['invalid-type'], false],
    ] as [$token, $valid]) {
        // An unknown action reaches validation but never saves settings or calls the provider.
        $_POST = ['kopokopo_action' => 'csrf_regression_probe', 'form_token' => $token];
        $html = $module->getContent();
        $expected = $valid ? 'Unknown configuration action.' : 'Your settings form expired.';
        if (!str_contains($html, $expected)) throw new RuntimeException('Admin CSRF validation regression.');
    }
} finally {
    $_POST = $originalPost;
}
echo "Admin form OK: both masked tokens accepted; foreign, malformed, empty and non-string tokens rejected. No settings or payments changed.\n";
