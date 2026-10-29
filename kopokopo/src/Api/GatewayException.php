<?php
namespace SimiyuSamuel\Kopokopo\Api;

final class GatewayException extends \RuntimeException
{
    public function __construct(public readonly bool $ambiguous, string $message = 'The payment provider could not complete the request.')
    {
        parent::__construct($message);
    }
}
