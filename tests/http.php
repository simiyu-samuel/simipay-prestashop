<?php
/** Loopback-only synthetic callback checks. Requires the disposable smoke-test fixture. */
$base = getenv('KOPOKOPO_TEST_HTTP');
if (!$base || !in_array(parse_url($base, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('KOPOKOPO_TEST_HTTP must be a disposable loopback test server.');
}
$fixture = json_decode(file_get_contents(dirname(__DIR__) . '/.work/http-fixture.json'), true, 32, JSON_THROW_ON_ERROR);
function request(string $controller, string $method, string $body = '', string $signature = '', array $params = []): array {
    global $base, $fixture;
    $url = rtrim($base, '/') . '/index.php?' . http_build_query(['fc' => 'module', 'module' => 'kopokopo', 'controller' => $controller, 'attempt' => $fixture['id']] + $params);
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-KopoKopo-Signature: ' . $signature],
        CURLOPT_POSTFIELDS => $method === 'POST' ? $body : null]);
    $result = curl_exec($curl);
    if ($result === false) throw new RuntimeException('Local HTTP request failed: ' . curl_error($curl));
    return [(int) curl_getinfo($curl, CURLINFO_HTTP_CODE), $result];
}
function expectStatus(int $status, array $response, string $name): void {
    if ($response[0] !== $status) throw new RuntimeException($name . ': expected ' . $status . ', got ' . $response[0] . ' ' . substr($response[1], 0, 300));
    json_decode($response[1], true, 32, JSON_THROW_ON_ERROR);
    echo 'PASS HTTP ' . $name . "\n";
}
$body = json_encode($fixture['payload'], JSON_THROW_ON_ERROR);
$sig = hash_hmac('sha256', $body, $fixture['key']);
expectStatus(405, request('callback', 'GET'), 'callback requires POST');
expectStatus(401, request('callback', 'POST', $body, str_repeat('0', 64)), 'invalid callback signature rejected');
expectStatus(400, request('callback', 'POST', '{', hash_hmac('sha256', '{', $fixture['key'])), 'signed invalid JSON rejected');
expectStatus(413, request('callback', 'POST', str_repeat('a', 65537), $sig), 'oversized callback rejected');
expectStatus(200, request('callback', 'POST', $body, $sig), 'signed duplicate callback accepted without browser cookies');
expectStatus(200, request('callback', 'POST', $body, $sig), 'repeated signed duplicate remains idempotent');
$wrong = $fixture['payload']; $wrong['data']['attributes']['event']['resource']['amount'] = '0.01';
$wrongBody = json_encode($wrong, JSON_THROW_ON_ERROR);
expectStatus(422, request('callback', 'POST', $wrongBody, hash_hmac('sha256', $wrongBody, $fixture['key'])), 'signed amount mismatch rejected');
expectStatus(404, request('paymentstatus', 'GET', '', '', ['format' => 'json']), 'customer status cannot be read anonymously');
expectStatus(405, request('reconcile', 'GET'), 'scheduler requires POST');
expectStatus(401, request('reconcile', 'POST', '{}'), 'scheduler requires its private header token');
echo "10 HTTP checks passed. No live provider request was made.\n";
