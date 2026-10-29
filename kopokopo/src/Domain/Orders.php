<?php
namespace SimiyuSamuel\Kopokopo\Domain;

interface Orders
{
    /** Validates ownership and returns a stable snapshot plus subscriber details. */
    public function snapshot(int $cartId, int $customerId): array;
    /** A partially completed order must never be retried blindly. */
    public function complete(array $attempt): int;
    public function reference(): string;
}
