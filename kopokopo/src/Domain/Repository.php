<?php
namespace SimiyuSamuel\Kopokopo\Domain;

interface Repository
{
    public function locked(int $shop, int $cart, \Closure $operation): mixed;
    public function create(array $attempt): void;
    public function find(string $id): ?array;
    public function update(string $id, array $changes): void;
    public function openForCart(int $shop, int $cart): ?array;
    public function countRecent(int $shop, int $customer): int;
}
