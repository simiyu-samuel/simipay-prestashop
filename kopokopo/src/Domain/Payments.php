<?php
namespace SimiyuSamuel\Kopokopo\Domain;

use SimiyuSamuel\Kopokopo\Api\Client;
use SimiyuSamuel\Kopokopo\Api\Gateway;
use SimiyuSamuel\Kopokopo\Api\GatewayException;

final class Payments
{
    public function __construct(private readonly Repository $repository, private readonly Gateway $gateway,
        private readonly Orders $orders, private readonly array $settings)
    {
    }

    public function initiate(int $cart, int $customer, string $phone, \Closure $callbackUrl): array
    {
        $phone = Values::phone($phone);
        $shop = (int) $this->settings['shop'];
        return $this->repository->locked($shop, $cart, function () use ($shop, $cart, $customer, $phone, $callbackUrl) {
            $existing = $this->repository->openForCart($shop, $cart);
            if ($existing) {
                if ((int) $existing['customer_id'] !== $customer) {
                    throw new PaymentException('This payment belongs to another customer.', 403);
                }
                return $existing;
            }
            if ($this->repository->countRecent($shop, $customer) >= 10) {
                throw new PaymentException('Too many payment requests. Please contact the merchant.', 429);
            }
            $snapshot = $this->orders->snapshot($cart, $customer);
            $id = bin2hex(random_bytes(16));
            $attempt = [
                'id' => $id, 'shop_id' => $shop, 'cart_id' => $cart, 'customer_id' => $customer,
                'currency_id' => $snapshot['currency_id'], 'currency' => 'KES',
                'amount_minor' => $snapshot['amount_minor'], 'snapshot' => $snapshot['hash'],
                'environment' => $this->settings['environment'], 'till' => $this->settings['till'],
                'provider_id' => null, 'provider_url' => null, 'receipt' => null,
                'state' => 'initiating', 'order_id' => null, 'order_reference' => $this->orders->reference(),
                'reason' => '', 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
                'checked_at' => null,
            ];
            $this->repository->create($attempt);
            try {
                $metadata = ['attempt_id' => $id, 'cart_id' => (string) $cart,
                    'order_reference' => $attempt['order_reference']];
                // Checkout identity is merchant context, not the provider-verified payer identity.
                $customerName = trim(trim($snapshot['subscriber']['first_name'] ?? '') . ' '
                    . trim($snapshot['subscriber']['last_name'] ?? ''));
                if ($customerName !== '') {
                    $metadata['customer_name'] = $customerName;
                }
                $result = $this->gateway->initiate([
                    'payment_channel' => 'M-PESA STK Push', 'till_number' => $attempt['till'],
                    'subscriber' => ['phone_number' => $phone] + $snapshot['subscriber'],
                    'amount' => ['currency' => 'KES', 'value' => (float) Values::decimal($attempt['amount_minor'])],
                    'metadata' => $metadata,
                    '_links' => ['callback_url' => $callbackUrl($id)],
                ]);
            } catch (GatewayException $exception) {
                $this->repository->update($id, ['state' => $exception->ambiguous ? 'unknown' : 'failed',
                    'reason' => $exception->ambiguous ? 'initiation_unresolved' : 'initiation_rejected']);
                return $this->required($id);
            } catch (\Throwable $exception) {
                $this->repository->update($id, ['state' => 'unknown', 'reason' => 'initiation_unresolved']);
                throw $exception;
            }
            $latest = $this->required($id);
            if (!empty($latest['provider_id']) && $latest['provider_id'] !== $result['id']) {
                $this->repository->update($id, ['state' => 'review', 'reason' => 'request_id_conflict']);
            } else {
                $changes = ['provider_id' => $result['id'], 'provider_url' => $result['url']];
                if ($latest['state'] === 'initiating') {
                    $changes['state'] = 'pending';
                }
                $this->repository->update($id, $changes);
            }
            return $this->required($id);
        });
    }

    /** Callers authenticate callbacks; queries use authenticated provider GETs. */
    public function receive(string $id, array $body): array
    {
        $attempt = $this->required($id);
        return $this->repository->locked((int) $attempt['shop_id'], (int) $attempt['cart_id'], function () use ($id, $body) {
            $attempt = $this->required($id);
            $payment = Webhook::payment($body, $attempt);
            if (in_array($attempt['state'], ['completed', 'review'], true)) {
                return $attempt;
            }
            $changes = ['provider_id' => $payment['provider_id'],
                'provider_url' => Client::base($attempt['environment']) . '/api/v2/incoming_payments/' . $payment['provider_id']];
            if ($attempt['state'] === 'resolved') {
                if ($payment['status'] === 'Success') {
                    $this->repository->update($id, $changes + ['state' => 'review', 'receipt' => $payment['receipt'], 'reason' => 'late_payment_after_resolution']);
                }
                return $this->required($id);
            }
            if ($payment['status'] === 'Success') {
                $changes += ['state' => 'confirmed', 'receipt' => $payment['receipt'], 'reason' => ''];
                if ($attempt['state'] === 'completing') {
                    $changes['state'] = 'review';
                    $changes['reason'] = 'completion_interrupted';
                    $this->repository->update($id, $changes);
                    return $this->required($id);
                }
                $this->repository->update($id, $changes);
                $attempt = $this->required($id);
                $this->repository->update($id, ['state' => 'completing']);
                try {
                    $orderId = $this->orders->complete($attempt);
                    $this->repository->update($id, ['state' => 'completed', 'order_id' => $orderId, 'reason' => '']);
                } catch (\Throwable) {
                    $this->repository->update($id, ['state' => 'review', 'reason' => 'order_requires_review']);
                }
            } elseif (!in_array($attempt['state'], ['confirmed', 'completing'], true)) {
                $changes += ['state' => $payment['status'] === 'Failed' ? 'failed' : ($attempt['state'] === 'failed' ? 'failed' : 'pending'),
                    'reason' => $payment['status'] === 'Failed' ? 'provider_failed' : $attempt['reason']];
                $this->repository->update($id, $changes);
            }
            return $this->required($id);
        });
    }

    public function reconcile(string $id): array
    {
        $attempt = $this->required($id);
        if (in_array($attempt['state'], ['completed', 'failed', 'review', 'resolved'], true)) {
            return $attempt;
        }
        $this->repository->update($id, ['checked_at' => gmdate('Y-m-d H:i:s')]);
        if (empty($attempt['provider_url'])) {
            return $this->repository->locked((int) $attempt['shop_id'], (int) $attempt['cart_id'], function () use ($id): array {
                $latest = $this->required($id);
                if (empty($latest['provider_url']) && in_array($latest['state'], ['initiating', 'pending', 'unknown'], true)
                    && strtotime($latest['created_at'] . ' UTC') < time() - 120) {
                    $this->repository->update($id, ['state' => 'unknown', 'reason' => 'initiation_unresolved']);
                }
                return $this->required($id);
            });
        }
        return $this->receive($id, $this->gateway->query($attempt['provider_url']));
    }

    private function required(string $id): array
    {
        $attempt = Values::attemptId($id) ? $this->repository->find($id) : null;
        if (!$attempt || (int) $attempt['shop_id'] !== (int) $this->settings['shop']
            || $attempt['environment'] !== $this->settings['environment']) {
            throw new PaymentException('Payment attempt not found.', 404);
        }
        return $attempt;
    }
}
