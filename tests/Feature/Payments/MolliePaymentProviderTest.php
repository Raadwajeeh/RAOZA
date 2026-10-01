<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Providers\MolliePaymentProvider;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Exceptions\ProviderOperationException;
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

        $remote = app(MolliePaymentProvider::class)->create($fixture['order'], 'https://store.test/return', 'https://store.test/webhook', 'stable-payment-attempt');

        $this->assertSame(1001, $remote->amount);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.mollie.test/v2/payments'
                && $request['amount'] === ['currency' => 'EUR', 'value' => '10.01']
                && $request->hasHeader('Idempotency-Key', 'stable-payment-attempt')
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

    public function test_partial_refund_uses_exact_amount_idempotency_and_safe_metadata(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response($this->refundResponse('12.34'), 201)]);
        $request = new ProviderRefundRequest('tr_paid', 1234, 'EUR', 'stable-reference', 'RAOZA refund RZ-1', 42);

        $remote = app(MolliePaymentProvider::class)->createRefund($request);

        $this->assertSame('re_test', $remote->id);
        $this->assertSame(1234, $remote->amount);
        Http::assertSent(function (Request $sent): bool {
            return $sent->url() === 'https://api.mollie.test/v2/payments/tr_paid/refunds'
                && $sent->hasHeader('Idempotency-Key', 'stable-reference')
                && $sent['amount'] === ['currency' => 'EUR', 'value' => '12.34']
                && $sent['metadata'] === ['raoza_refund_id' => '42', 'raoza_refund_reference' => 'stable-reference'];
        });
    }

    public function test_refund_lookup_recovers_by_metadata_reference(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response(['_embedded' => ['refunds' => [
            $this->refundResponse('5.00', 'other-reference'),
            $this->refundResponse('12.34', 'wanted-reference'),
        ]]], 200)]);

        $remote = app(MolliePaymentProvider::class)->findRefund('tr_paid', 'wanted-reference');

        $this->assertNotNull($remote);
        $this->assertSame('wanted-reference', $remote->reference);
        $this->assertSame(1234, $remote->amount);
    }

    public function test_malformed_refund_response_fails_as_uncertain(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response(['status' => 'pending'], 201)]);

        try {
            app(MolliePaymentProvider::class)->createRefund(new ProviderRefundRequest('tr_paid', 1234, 'EUR', 'stable-reference', 'RAOZA refund RZ-1', 42));
            $this->fail('Malformed refund response was accepted.');
        } catch (ProviderOperationException $exception) {
            $this->assertTrue($exception->outcomeUncertain);
        }
    }

    public function test_refund_connection_failure_never_returns_success(): void
    {
        Http::fake(['api.mollie.test/*' => Http::failedConnection()]);

        try {
            app(MolliePaymentProvider::class)->createRefund(new ProviderRefundRequest('tr_paid', 1234, 'EUR', 'stable-reference', 'RAOZA refund RZ-1', 42));
            $this->fail('Connection failure was accepted.');
        } catch (ProviderOperationException $exception) {
            $this->assertTrue($exception->outcomeUncertain);
        }
    }

    public function test_refund_provider_4xx_is_a_deterministic_rejection(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response(['status' => 422], 422)]);

        try {
            app(MolliePaymentProvider::class)->createRefund(new ProviderRefundRequest('tr_paid', 1234, 'EUR', 'stable-reference', 'RAOZA refund RZ-1', 42));
            $this->fail('Provider rejection was accepted.');
        } catch (ProviderOperationException $exception) {
            $this->assertFalse($exception->outcomeUncertain);
            $this->assertSame('The refund provider rejected this refund.', $exception->getMessage());
        }
    }

    public function test_refund_provider_5xx_remains_uncertain(): void
    {
        Http::fake(['api.mollie.test/*' => Http::response([], 503)]);

        try {
            app(MolliePaymentProvider::class)->createRefund(new ProviderRefundRequest('tr_paid', 1234, 'EUR', 'stable-reference', 'RAOZA refund RZ-1', 42));
            $this->fail('Provider server failure was accepted.');
        } catch (ProviderOperationException $exception) {
            $this->assertTrue($exception->outcomeUncertain);
            $this->assertStringContainsString('reconciliation', $exception->getMessage());
        }
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

    private function refundResponse(string $amount, string $reference = 'stable-reference'): array
    {
        return [
            'id' => 're_test',
            'paymentId' => 'tr_paid',
            'status' => 'pending',
            'amount' => ['currency' => 'EUR', 'value' => $amount],
            'createdAt' => '2026-09-30T10:00:00+00:00',
            'metadata' => ['raoza_refund_reference' => $reference],
        ];
    }
}
