<?php
namespace SimiyuSamuel\Kopokopo\Domain;

final class Webhook
{
    public static function decode(string $body, string $signature, string $key): array
    {
        if (strlen($body) > 65536) {
            throw new PaymentException('Callback is too large.', 413);
        }
        if ($key === '' || !preg_match('/^[a-f0-9]{64}$/Di', $signature)
            || !hash_equals(hash_hmac('sha256', $body, $key), strtolower($signature))) {
            throw new PaymentException('Invalid callback signature.', 401);
        }
        try {
            $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \JsonException();
            }
            return $data;
        } catch (\JsonException) {
            throw new PaymentException('Invalid callback JSON.', 400);
        }
    }

    public static function payment(array $body, array $attempt): array
    {
        $data = $body['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;
        if (!is_array($attributes) || ($data['type'] ?? '') !== 'incoming_payment'
            || !is_string($data['id'] ?? null) || !Values::uuid($data['id'])
            || !in_array($attributes['status'] ?? null, ['Success', 'Failed', 'Pending'], true)
            || ($attributes['metadata']['attempt_id'] ?? '') !== $attempt['id']) {
            throw new PaymentException('Callback does not identify this payment attempt.', 422);
        }
        if (!empty($attempt['provider_id']) && $attempt['provider_id'] !== $data['id']) {
            throw new PaymentException('Callback request ID does not match.', 422);
        }
        $resource = $attributes['event']['resource'] ?? null;
        if ($attributes['status'] === 'Success') {
            if (!is_array($resource) || !is_string($resource['reference'] ?? null)
                || !preg_match('/^[A-Za-z0-9-]{1,64}$/D', $resource['reference'])
                || (!is_string($resource['amount'] ?? null) && !is_int($resource['amount'] ?? null) && !is_float($resource['amount'] ?? null))
                || Values::minor($resource['amount']) !== (int) $attempt['amount_minor']
                || ($resource['currency'] ?? '') !== $attempt['currency']
                || (string) ($resource['till_number'] ?? '') !== $attempt['till']
                || ($resource['status'] ?? '') !== 'Received') {
                throw new PaymentException('Payment details do not match the expected amount, currency, or till.', 422);
            }
        }
        return ['provider_id' => $data['id'], 'status' => $attributes['status'],
            'receipt' => $attributes['status'] === 'Success' ? $resource['reference'] : null];
    }
}
