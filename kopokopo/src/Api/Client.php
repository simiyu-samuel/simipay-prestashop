<?php
namespace SimiyuSamuel\Kopokopo\Api;

use SimiyuSamuel\Kopokopo\Domain\Values;

/** Native cURL adapter for Kopo Kopo API v2. No merchant-side Composer step. */
final class Client implements Gateway
{
    private readonly string $base;

    public function __construct(
        private readonly Transport $transport,
        string $environment,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly \Closure $readToken,
        private readonly \Closure $writeToken
    ) {
        $this->base = self::base($environment);
    }

    public static function base(string $environment): string
    {
        return match ($environment) {
            'sandbox' => 'https://sandbox.kopokopo.com',
            'production' => 'https://api.kopokopo.com',
            default => throw new \InvalidArgumentException('Invalid payment environment.'),
        };
    }

    public static function resource(string $url, string $environment): string
    {
        $prefix = self::base($environment) . '/api/v2/incoming_payments/';
        $id = substr($url, strlen($prefix));
        if (!str_starts_with($url, $prefix) || !Values::uuid($id)) {
            throw new GatewayException(true, 'The provider returned an invalid payment resource.');
        }
        return $id;
    }

    public function token(bool $fresh = false): string
    {
        $cached = ($this->readToken)();
        if (!$fresh && is_array($cached) && ($cached['expires'] ?? 0) > time() + 60
            && is_string($cached['token'] ?? null) && $cached['token'] !== '') {
            return $cached['token'];
        }
        $response = $this->transport->request('POST', $this->base . '/oauth/token', [
            'Content-Type: application/x-www-form-urlencoded', 'Accept: application/json', 'User-Agent: PrestaShop-Kopokopo/2.0.0',
        ], http_build_query(['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'grant_type' => 'client_credentials']));
        if ($response['status'] !== 200) {
            throw new GatewayException(false, 'Kopo Kopo authentication failed. Please ask the merchant to check the configuration.');
        }
        $data = $this->json($response['body']);
        if (!is_string($data['access_token'] ?? null) || !preg_match('/^[\x21-\x7e]{1,4096}$/D', $data['access_token'])
            || !is_numeric($data['expires_in'] ?? null) || (int) $data['expires_in'] <= 60) {
            throw new GatewayException(false, 'Kopo Kopo returned invalid authentication data.');
        }
        ($this->writeToken)(['token' => $data['access_token'], 'expires' => time() + (int) $data['expires_in']]);
        return $data['access_token'];
    }

    public function initiate(array $payload): array
    {
        // Obtain the token before the STK request: a token failure cannot have charged a customer.
        try {
            $token = $this->token();
        } catch (GatewayException) {
            throw new GatewayException(false, 'Kopo Kopo authentication is unavailable. No payment prompt was sent.');
        }
        $response = $this->transport->request('POST', $this->base . '/api/v2/incoming_payments',
            $this->headers($token), json_encode($payload, JSON_THROW_ON_ERROR));
        if ($response['status'] !== 201) {
            $ambiguous = $response['status'] >= 500 || in_array($response['status'], [0, 408, 429], true)
                || ($response['status'] >= 200 && $response['status'] < 400);
            throw new GatewayException($ambiguous);
        }
        $url = $response['headers']['location'] ?? '';
        $id = self::resource($url, $this->base === self::base('sandbox') ? 'sandbox' : 'production');
        return ['id' => $id, 'url' => $url];
    }

    public function query(string $url): array
    {
        self::resource($url, $this->base === self::base('sandbox') ? 'sandbox' : 'production');
        $response = $this->transport->request('GET', $url, $this->headers($this->token()));
        // Retrying a read after refresh is safe; STK POSTs are never automatically retried.
        if ($response['status'] === 401) {
            $response = $this->transport->request('GET', $url, $this->headers($this->token(true)));
        }
        if ($response['status'] !== 200) {
            throw new GatewayException(true);
        }
        return $this->json($response['body']);
    }

    private function headers(string $token): array
    {
        return ['Content-Type: application/json', 'Accept: application/json',
            'Authorization: Bearer ' . $token, 'User-Agent: PrestaShop-Kopokopo/2.0.0'];
    }

    private function json(string $body): array
    {
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \JsonException();
            }
            return $data;
        } catch (\JsonException) {
            throw new GatewayException(true, 'The payment provider returned an unreadable response.');
        }
    }
}
