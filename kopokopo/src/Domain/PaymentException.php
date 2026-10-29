<?php
namespace SimiyuSamuel\Kopokopo\Domain;

class PaymentException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 400)
    {
        parent::__construct($message);
    }
}
