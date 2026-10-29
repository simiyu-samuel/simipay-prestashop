<?php
namespace SimiyuSamuel\Kopokopo\Infrastructure;

use SimiyuSamuel\Kopokopo\Api\Client;
use SimiyuSamuel\Kopokopo\Api\CurlTransport;
use SimiyuSamuel\Kopokopo\Domain\PaymentException;

final class Settings
{
    public function __construct(public readonly int $shop)
    {
    }

    public function get(string $name): string
    {
        return (string) \Configuration::get('KOPOKOPO_' . $name, null, null, $this->shop);
    }

    public function set(string $name, string $value): void
    {
        if (!\Configuration::updateValue('KOPOKOPO_' . $name, $value, false, null, $this->shop)) {
            throw new \RuntimeException('Could not save module configuration.');
        }
    }

    public function environment(): string
    {
        return $this->get('ENVIRONMENT') === 'production' ? 'production' : 'sandbox';
    }

    public function forEnvironment(string $environment): array
    {
        Client::base($environment);
        $prefix = strtoupper($environment) . '_';
        return ['shop' => $this->shop, 'environment' => $environment,
            'till' => $this->get($prefix . 'TILL'), 'client_id' => $this->get($prefix . 'CLIENT_ID'),
            'client_secret' => $this->secret($prefix . 'CLIENT_SECRET'), 'api_key' => $this->secret($prefix . 'API_KEY')];
    }

    public function ready(?string $environment = null): bool
    {
        $data = $this->forEnvironment($environment ?? $this->environment());
        foreach (['till', 'client_id', 'client_secret', 'api_key'] as $key) {
            if ($data[$key] === '') {
                return false;
            }
        }
        return true;
    }

    public function client(string $environment): Client
    {
        $data = $this->forEnvironment($environment);
        if (!$this->ready($environment)) {
            throw new PaymentException('The payment method has not been configured. Please contact the merchant.', 503);
        }
        $cacheKey = strtoupper($environment) . '_TOKEN';
        return new Client(new CurlTransport(), $environment, $data['client_id'], $data['client_secret'],
            function () use ($cacheKey): ?array {
                $json = $this->secret($cacheKey);
                $value = $json === '' ? null : json_decode($json, true);
                return is_array($value) ? $value : null;
            }, function (array $token) use ($cacheKey): void {
                $this->setSecret($cacheKey, json_encode($token, JSON_THROW_ON_ERROR));
            });
    }

    public function secret(string $name): string
    {
        $encoded = $this->get($name);
        if ($encoded === '') {
            return '';
        }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 29 || ord($bytes[0]) !== 1) {
            throw new \RuntimeException('Invalid encrypted configuration. Re-enter credentials.');
        }
        $value = openssl_decrypt(substr($bytes, 29), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA,
            substr($bytes, 1, 12), substr($bytes, 13, 16), $this->aad($name));
        if ($value === false) {
            throw new \RuntimeException('Could not decrypt configuration. Restore the shop key or re-enter credentials.');
        }
        return $value;
    }

    public function setSecret(string $name, string $value): void
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag, $this->aad($name));
        if ($cipher === false) {
            throw new \RuntimeException('Could not encrypt configuration.');
        }
        $this->set($name, base64_encode(chr(1) . $iv . $tag . $cipher));
    }

    public function initialize(): void
    {
        if ($this->get('ENVIRONMENT') === '') {
            $this->set('ENVIRONMENT', 'sandbox');
        }
        if ($this->get('CRON_TOKEN') === '') {
            $this->setSecret('CRON_TOKEN', bin2hex(random_bytes(32)));
        }
    }

    private function key(): string
    {
        return hash_hkdf('sha256', _COOKIE_KEY_, 32, 'kopokopo-config-v2');
    }

    private function aad(string $name): string
    {
        return 'kopokopo:' . $this->shop . ':' . $name;
    }
}
