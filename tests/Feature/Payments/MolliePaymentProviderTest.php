<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Providers\MolliePaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\BuildsCommerceFixtures;
use Tests\TestCase;

class MolliePaymentProviderTest extends TestCase
{
    use BuildsCommerceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.mollie.api_key' => 'test_not_a_real_secret', 'payments.mollie.base_url' => 'https://api.mollie.test/v2']);
    }

    public function test_create_sends_exact_server_order_money_without_floats(): void
    {
        $fixture = $this->checkoutOrder([['price' => 1001]]);
        Http::fake(['api.mollie.test/*' => Http::response($this->response('10.01'), 201)]);

        $remote = app(MolliePaymentProvider::class)->create($fixture['order'], 'https://store.test/return', 'https://store.test/webhook');

        $this->assertSame(1001, $remote->amount);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.mollie.test/v2/payments'
                && $request['amount'] === ['currency' => 'EUR', 'value' => '10.01']
                && $request['redirectUrl'] === 'https://store.test/return'
                && $request['webhookUrl'] === 'https://store.test/webhook';
        });
    }

    public function test_malformed_provider_amount_is_rejected(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response($this->response('10.001'), 200)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid payment amount');
        app(MolliePaymentProvider::class)->fetch('tr_test');
    }

    private function response(string $amount): array
    {
        return [
            'id' => 'tr_test',
            'status' => 'open',
            'amount' => ['currency' => 'EUR', 'value' => $amount],
            '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/test']],
            'metadata' => ['order_number' => 'test'],
        ];
    }
}
