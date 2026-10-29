<?php
require dirname(__DIR__) . '/kopokopo/bootstrap.php';

use SimiyuSamuel\Kopokopo\Api\Client;
use SimiyuSamuel\Kopokopo\Api\Gateway;
use SimiyuSamuel\Kopokopo\Api\GatewayException;
use SimiyuSamuel\Kopokopo\Api\Transport;
use SimiyuSamuel\Kopokopo\Domain\Orders;
use SimiyuSamuel\Kopokopo\Domain\PaymentException;
use SimiyuSamuel\Kopokopo\Domain\Payments;
use SimiyuSamuel\Kopokopo\Domain\Repository;
use SimiyuSamuel\Kopokopo\Domain\Values;
use SimiyuSamuel\Kopokopo\Domain\Webhook;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}
function rejects(Closure $operation, string $class = PaymentException::class, ?int $status = null): void {
    try { $operation(); } catch (Throwable $error) {
        if (!$error instanceof $class) throw $error;
        if ($status !== null) same($status, $error->httpStatus);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $class);
}
final class MemoryRepository implements Repository {
    public array $rows = [];
    public function locked(int $shop, int $cart, Closure $operation): mixed { return $operation(); }
    public function create(array $attempt): void { $this->rows[$attempt['id']] = $attempt; }
    public function find(string $id): ?array { return $this->rows[$id] ?? null; }
    public function update(string $id, array $changes): void { $this->rows[$id] = array_replace($this->rows[$id], $changes); }
    public function openForCart(int $shop, int $cart): ?array {
        foreach (array_reverse($this->rows) as $row) if ($row['shop_id'] === $shop && $row['cart_id'] === $cart && $row['state'] !== 'failed') return $row;
        return null;
    }
    public function countRecent(int $shop, int $customer): int {
        return count(array_filter($this->rows, fn ($row) => $row['shop_id'] === $shop && $row['customer_id'] === $customer));
    }
}
final class FakeGateway implements Gateway {
    public const ID = '12345678-1234-1234-1234-123456789abc';
    public int $calls = 0;
    public array $payload = [];
    public ?GatewayException $error = null;
    public array $response = [];
    public ?Closure $during = null;
    public function initiate(array $payload): array {
        ++$this->calls;
        $this->payload = $payload;
        if ($this->during) ($this->during)($payload);
        if ($this->error) throw $this->error;
        return ['id' => self::ID, 'url' => Client::base('sandbox') . '/api/v2/incoming_payments/' . self::ID];
    }
    public function query(string $url): array { return $this->response; }
}
final class FakeOrders implements Orders {
    public int $calls = 0;
    public bool $changed = false;
    public function snapshot(int $cartId, int $customerId): array {
        if ($cartId !== 100 || $customerId !== 7) throw new PaymentException('Wrong cart owner.', 403);
        return ['currency_id' => 1, 'amount_minor' => 12550, 'hash' => str_repeat('a', 64), 'subscriber' => ['email' => 'test@example.invalid']];
    }
    public function reference(): string { return 'TESTORDER'; }
    public function complete(array $attempt): int {
        ++$this->calls;
        if ($this->changed) throw new RuntimeException('Changed cart or partial core failure');
        return 42;
    }
}
final class QueueTransport implements Transport {
    public array $requests = [];
    public function __construct(public array $responses) {}
    public function request(string $method, string $url, array $headers, ?string $body = null): array {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        if (!$this->responses) throw new RuntimeException('Unexpected extra provider request.');
        $response = array_shift($this->responses);
        if ($response instanceof Throwable) throw $response;
        return $response;
    }
}
function fixture(): array {
    $repo = new MemoryRepository(); $gateway = new FakeGateway(); $orders = new FakeOrders();
    $service = new Payments($repo, $gateway, $orders, ['shop' => 1, 'environment' => 'sandbox', 'till' => 'K000001']);
    return [$service, $repo, $gateway, $orders];
}
function start(Payments $service): array {
    return $service->initiate(100, 7, '0712345678', fn ($id) => 'https://shop.example/module/kopokopo/callback?attempt=' . $id);
}
function payload(array $attempt, string $status = 'Success'): array {
    return ['data' => ['id' => FakeGateway::ID, 'type' => 'incoming_payment', 'attributes' => [
        'status' => $status, 'metadata' => ['attempt_id' => $attempt['id']],
        'event' => ['resource' => $status === 'Success' ? ['reference' => 'TESTRECEIPT', 'amount' => '125.50', 'currency' => 'KES', 'till_number' => 'K000001', 'status' => 'Received'] : null],
    ]]];
}
function apiFixture(array $responses, string $environment = 'sandbox'): array {
    $transport = new QueueTransport($responses); $cache = null;
    $client = new Client($transport, $environment, 'client', 'secret', static fn () => null, static function ($token) use (&$cache) { $cache = $token; });
    return [$client, $transport];
}
function tokenResponse(): array { return ['status' => 200, 'headers' => [], 'body' => '{"access_token":"token","expires_in":3600}']; }

$tests = [];
$tests['Kenyan phone formats normalize consistently'] = function () {
    foreach (['0712345678', '+254712345678', '254712345678', '0712 345 678', '(0712)345-678'] as $phone) same('+254712345678', Values::phone($phone));
    same('+254112345678', Values::phone('0112345678'));
    foreach (['', '123', '+255712345678', '2540712345678', '+254+254712345678', "0712345678\nBAD"] as $bad) rejects(fn () => Values::phone($bad));
};
$tests['Decimal money is exact and rejects invalid precision'] = function () {
    same(12550, Values::minor('125.50')); same(12550, Values::minor('125.5000')); same(105, Values::minor('1.05'));
    foreach (['0', '-10', '1e3', 'NaN', '125.501', '1.009', '99999999999'] as $bad) rejects(fn () => Values::minor($bad));
};
$tests['Callbacks require the exact raw-body signature'] = function () {
    $raw = '{"data":{}}'; $signature = hash_hmac('sha256', $raw, 'api-key');
    same(['data' => []], Webhook::decode($raw, $signature, 'api-key'));
    rejects(fn () => Webhook::decode($raw . ' ', $signature, 'api-key'), PaymentException::class, 401);
    rejects(fn () => Webhook::decode($raw, '', 'api-key'), PaymentException::class, 401);
    rejects(fn () => Webhook::decode($raw, $signature, 'client-secret'), PaymentException::class, 401);
    rejects(fn () => Webhook::decode(str_repeat('a', 65537), $signature, 'api-key'), PaymentException::class, 413);
    rejects(fn () => Webhook::decode('{', hash_hmac('sha256', '{', 'api-key'), 'api-key'), PaymentException::class, 400);
};
$tests['Provider resources cannot redirect credentials to another host'] = function () {
    $prefix = Client::base('sandbox') . '/api/v2/incoming_payments/';
    same(FakeGateway::ID, Client::resource($prefix . FakeGateway::ID, 'sandbox'));
    foreach (['http://sandbox.kopokopo.com/api/v2/incoming_payments/' . FakeGateway::ID,
        $prefix . FakeGateway::ID . '?x=1', $prefix . '../oauth/token',
        'https://sandbox.kopokopo.com.evil.test/api/v2/incoming_payments/' . FakeGateway::ID,
        Client::base('production') . '/api/v2/incoming_payments/' . FakeGateway::ID] as $bad) rejects(fn () => Client::resource($bad, 'sandbox'), GatewayException::class);
};
$tests['Initiation persists the server cart and does not repeat on double click'] = function () {
    [$service, $repo, $gateway] = fixture();
    $gateway->during = function ($body) use ($repo) { same('initiating', $repo->find($body['metadata']['attempt_id'])['state']); };
    $attempt = start($service); same('pending', $attempt['state']); same(12550, $attempt['amount_minor']);
    same('125.50', $gateway->payload['amount']['value']); same('100', $gateway->payload['metadata']['cart_id']);
    same($attempt['id'], start($service)['id']); same(1, $gateway->calls);
};
$tests['Foreign cart ownership is rejected before provider I/O'] = function () {
    [$service, , $gateway] = fixture();
    rejects(fn () => $service->initiate(9999, 7, '0712345678', fn () => ''), PaymentException::class, 403); same(0, $gateway->calls);
};
$tests['Definitive errors become failed, not successful initiation'] = function () {
    [$service, , $gateway] = fixture(); $gateway->error = new GatewayException(false);
    same('failed', start($service)['state']);
};
$tests['Ambiguous initiation blocks another charge and can accept a later callback'] = function () {
    [$service, , $gateway, $orders] = fixture(); $gateway->error = new GatewayException(true);
    $attempt = start($service); same('unknown', $attempt['state']); same($attempt['id'], start($service)['id']); same(1, $gateway->calls);
    same('completed', $service->receive($attempt['id'], payload($attempt))['state']); same(1, $orders->calls);
};
$tests['Valid payment creates one order despite duplicate and late failure callbacks'] = function () {
    [$service, , , $orders] = fixture(); $attempt = start($service);
    same('completed', $service->receive($attempt['id'], payload($attempt))['state']);
    same('completed', $service->receive($attempt['id'], payload($attempt))['state']);
    same('completed', $service->receive($attempt['id'], payload($attempt, 'Failed'))['state']); same(1, $orders->calls);
};
$tests['Mismatched payment details never create an order'] = function () {
    foreach (['amount' => '1.00', 'currency' => 'USD', 'till_number' => 'OTHER', 'status' => 'Unknown'] as $key => $value) {
        [$service, , , $orders] = fixture(); $attempt = start($service); $body = payload($attempt);
        $body['data']['attributes']['event']['resource'][$key] = $value;
        rejects(fn () => $service->receive($attempt['id'], $body), PaymentException::class, 422); same(0, $orders->calls);
    }
};
$tests['Foreign request IDs and metadata are rejected'] = function () {
    [$service, , , $orders] = fixture(); $attempt = start($service); $body = payload($attempt);
    $body['data']['id'] = 'ffffffff-1234-1234-1234-123456789abc'; rejects(fn () => $service->receive($attempt['id'], $body));
    $body = payload($attempt); $body['data']['attributes']['metadata']['attempt_id'] = 'foreign'; rejects(fn () => $service->receive($attempt['id'], $body)); same(0, $orders->calls);
};
$tests['Failed callback with a null resource is valid'] = function () {
    [$service, , , $orders] = fixture(); $attempt = start($service);
    same('failed', $service->receive($attempt['id'], payload($attempt, 'Failed'))['state']); same(0, $orders->calls);
};
$tests['Late success after a failed outcome is still recorded'] = function () {
    [$service] = fixture(); $attempt = start($service); $service->receive($attempt['id'], payload($attempt, 'Failed'));
    same('completed', $service->receive($attempt['id'], payload($attempt))['state']);
};
$tests['Changed carts or partial order failure retain money for merchant review'] = function () {
    [$service, , , $orders] = fixture(); $orders->changed = true; $attempt = start($service);
    $result = $service->receive($attempt['id'], payload($attempt)); same('review', $result['state']); same('TESTRECEIPT', $result['receipt']);
    $service->receive($attempt['id'], payload($attempt)); same(1, $orders->calls); same($attempt['id'], start($service)['id']);
};
$tests['Interrupted core order completion is never repeated blindly'] = function () {
    [$service, $repo, , $orders] = fixture(); $attempt = start($service); $repo->update($attempt['id'], ['state' => 'completing']);
    same('review', $service->receive($attempt['id'], payload($attempt))['state']); same(0, $orders->calls);
};
$tests['Reconciliation handles a shopper closing the browser'] = function () {
    [$service, , $gateway, $orders] = fixture(); $attempt = start($service); $gateway->response = payload($attempt);
    same('completed', $service->reconcile($attempt['id'])['state']); same(1, $orders->calls);
};
$tests['Environment and shop boundaries are enforced'] = function () {
    [$service, $repo, $gateway, $orders] = fixture(); $attempt = start($service);
    $other = new Payments($repo, $gateway, $orders, ['shop' => 1, 'environment' => 'production', 'till' => 'K000001']);
    rejects(fn () => $other->receive($attempt['id'], payload($attempt)), PaymentException::class, 404);
};
$tests['Customer request rate is bounded'] = function () {
    [$service, , $gateway] = fixture(); $gateway->error = new GatewayException(false);
    for ($i = 0; $i < 10; ++$i) start($service);
    rejects(fn () => start($service), PaymentException::class, 429); same(10, $gateway->calls);
};
$tests['Token and STK calls share the selected environment and read Location headers'] = function () {
    foreach (['sandbox', 'production'] as $environment) {
        $url = Client::base($environment) . '/api/v2/incoming_payments/' . FakeGateway::ID;
        [$client, $transport] = apiFixture([tokenResponse(), ['status' => 201, 'headers' => ['location' => $url], 'body' => '']], $environment);
        same(FakeGateway::ID, $client->initiate([])['id']); same(Client::base($environment) . '/oauth/token', $transport->requests[0]['url']);
        same(Client::base($environment) . '/api/v2/incoming_payments', $transport->requests[1]['url']); same(2, count($transport->requests));
    }
};
$tests['STK request errors are structured and never retried'] = function () {
    foreach ([401 => false, 422 => false, 500 => true, 429 => true, 408 => true] as $status => $ambiguous) {
        [$client, $transport] = apiFixture([tokenResponse(), ['status' => $status, 'headers' => [], 'body' => 'sensitive-error']]);
        try { $client->initiate([]); throw new RuntimeException('Expected provider error'); }
        catch (GatewayException $error) { same($ambiguous, $error->ambiguous); same(false, str_contains($error->getMessage(), 'sensitive-error')); }
        same(2, count($transport->requests));
    }
};
$tests['Accepted initiation without Location remains unresolved'] = function () {
    [$client] = apiFixture([tokenResponse(), ['status' => 201, 'headers' => [], 'body' => '']]);
    try { $client->initiate([]); throw new RuntimeException('Expected unresolved response'); }
    catch (GatewayException $error) { same(true, $error->ambiguous); }
};

$passed = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo "PASS $name\n"; ++$passed; }
    catch (Throwable $error) { fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n{$error->getTraceAsString()}\n"); exit(1); }
}
echo "$passed tests passed.\n";
