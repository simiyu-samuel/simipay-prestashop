<?php
namespace SimiyuSamuel\Kopokopo\Api;

interface Transport
{
    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function request(string $method, string $url, array $headers, ?string $body = null): array;
}
