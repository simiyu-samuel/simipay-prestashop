<?php
namespace SimiyuSamuel\Kopokopo\Domain;

final class Values
{
    public static function phone(string $input): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($input));
        if (preg_match('/^0([17]\d{8})$/D', $phone, $match)
            || preg_match('/^\+?254([17]\d{8})$/D', $phone, $match)) {
            return '+254' . $match[1];
        }
        throw new PaymentException('Enter a valid Kenyan mobile number, for example 0712345678.');
    }

    /** Parse provider decimal amounts without floating-point rounding or exponent notation. */
    public static function minor(string|int|float $input): int
    {
        $value = (string) $input;
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2})0*)?$/D', $value, $match)) {
            throw new PaymentException('Invalid payment amount.', 422);
        }
        $amount = ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '', 2, '0');
        if ($amount <= 0) {
            throw new PaymentException('Payment amount must be positive.', 422);
        }
        return $amount;
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100) . '.' . str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function uuid(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/Di', $id);
    }

    public static function attemptId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/D', $id);
    }
}
