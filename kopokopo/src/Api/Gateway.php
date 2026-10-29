<?php
namespace SimiyuSamuel\Kopokopo\Api;

interface Gateway
{
    /** @return array{id:string,url:string} */
    public function initiate(array $payload): array;
    public function query(string $url): array;
}
