<?php

namespace App\Domain\Payments\Providers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Payments\Contracts\PaymentProvider;
use App\Domain\Payments\Data\ProviderPayment;
use App\Domain\Payments\Data\ProviderRefund;
use App\Domain\Payments\Data\ProviderRefundRequest;
use App\Domain\Payments\Exceptions\ProviderOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MolliePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'mollie';
    }

    public function create(Order $order, string $redirectUrl, string $webhookUrl, string $idempotencyKey): ProviderPayment
    {
        try {
            $json = $this->client()->withHeader('Idempotency-Key',$idempotencyKey)->post('/payments', [
                'amount' => ['currency' => $order->currency, 'value' => $this->decimal($order->total_amount)],
                'description' => 'RAOZA '.$order->order_number,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => $webhookUrl,
                'metadata' => ['order_number' => $order->order_number],
            ])->throw()->json();
            if (! is_array($json)) throw new RuntimeException('Mollie returned an invalid payment response.');
            return $this->mapPayment($json);
        } catch (ConnectionException $exception) {
            throw new ProviderOperationException('Payment provider communication failed; retry the same payment attempt.',true,$exception);
        } catch (RequestException $exception) {
            $status=$exception->response->status();
            throw new ProviderOperationException('Payment provider creation failed.', $status>=500||in_array($status,[409,429],true), $exception);
        } catch (RuntimeException $exception) {
            throw new ProviderOperationException('Payment provider returned an invalid response; retry the same payment attempt.',true,$exception);
        }
    }

    public function fetch(string $id): ProviderPayment
    {
        return $this->mapPayment($this->client()->get('/payments/'.rawurlencode($id))->throw()->json());
    }

    public function createRefund(ProviderRefundRequest $request): ProviderRefund
    {
        try {
            $json = $this->client()
                ->withHeader('Idempotency-Key', $request->idempotencyKey)
                ->post('/payments/'.rawurlencode($request->paymentId).'/refunds', [
                    'amount' => ['currency' => $request->currency, 'value' => $this->decimal($request->amount)],
                    'description' => $request->description,
                    'metadata' => [
                        'raoza_refund_id' => (string) $request->internalRefundId,
                        'raoza_refund_reference' => $request->idempotencyKey,
                    ],
                ])->throw()->json();
            if (! is_array($json)) {
                throw new RuntimeException('Mollie returned an invalid refund response.');
            }

            return $this->mapRefund($json);
        } catch (ConnectionException $exception) {
            throw new ProviderOperationException('Refund provider communication failed; reconciliation is required.', true, $exception);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $uncertain = $status >= 500 || in_array($status, [409, 429], true);
            throw new ProviderOperationException(
                $uncertain ? 'Refund provider outcome is uncertain; reconciliation is required.' : 'The refund provider rejected this refund.',
                $uncertain,
                $exception,
            );
        } catch (RuntimeException $exception) {
            throw new ProviderOperationException('Refund provider returned an invalid response; reconciliation is required.', true, $exception);
        }
    }

    public function fetchRefund(string $providerPaymentId, string $providerRefundId): ProviderRefund
    {
        try {
            $json = $this->client()
                ->get('/payments/'.rawurlencode($providerPaymentId).'/refunds/'.rawurlencode($providerRefundId))
                ->throw()
                ->json();
            if (! is_array($json)) {
                throw new RuntimeException('Mollie returned an invalid refund response.');
            }

            return $this->mapRefund($json);
        } catch (ConnectionException|RequestException $exception) {
            throw new ProviderOperationException('Refund provider synchronization failed.', true, $exception);
        } catch (RuntimeException $exception) {
            throw new ProviderOperationException('Refund provider returned an invalid response.', true, $exception);
        }
    }

    public function findRefund(string $providerPaymentId, string $reference): ?ProviderRefund
    {
        try {
            $json = $this->client()
                ->get('/payments/'.rawurlencode($providerPaymentId).'/refunds', ['limit' => 250])
                ->throw()
                ->json();
            if (! is_array($json)) {
                throw new RuntimeException('Mollie returned an invalid refund list.');
            }
            $refunds = $json['_embedded']['refunds'] ?? null;
            if (! is_array($refunds)) {
                throw new RuntimeException('Mollie returned an invalid refund list.');
            }

            foreach ($refunds as $refund) {
                if (is_array($refund) && ($refund['metadata']['raoza_refund_reference'] ?? null) === $reference) {
                    return $this->mapRefund($refund);
                }
            }

            return null;
        } catch (ConnectionException|RequestException $exception) {
            throw new ProviderOperationException('Refund provider recovery lookup failed.', true, $exception);
        } catch (RuntimeException $exception) {
            throw new ProviderOperationException('Refund provider returned an invalid recovery response.', true, $exception);
        }
    }

    private function client(): PendingRequest
    {
        $key = (string) config('payments.mollie.api_key');
        if ($key === '') {
            throw new RuntimeException('MOLLIE_API_KEY is not configured.');
        }

        return Http::baseUrl((string) config('payments.mollie.base_url'))
            ->withToken($key)
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->retry(2, 200);
    }

    private function mapPayment(array $json): ProviderPayment
    {
        $id = $json['id'] ?? null;
        $status = $json['status'] ?? null;
        $currency = $json['amount']['currency'] ?? null;
        $amount = $json['amount']['value'] ?? null;
        if (! is_string($id) || $id === '' || ! is_string($status) || $status === '' || ! is_string($currency) || strlen($currency) !== 3 || ! is_string($amount)) {
            throw new RuntimeException('Mollie returned an invalid payment response.');
        }

        return new ProviderPayment($id, $status, $this->minor($amount), strtoupper($currency), $json['_links']['checkout']['href'] ?? null, $json['method'] ?? null, $json['metadata']['order_number'] ?? null, $json);
    }

    private function mapRefund(array $json): ProviderRefund
    {
        $id = $json['id'] ?? null;
        $paymentId = $json['paymentId'] ?? null;
        $status = $json['status'] ?? null;
        $currency = $json['amount']['currency'] ?? null;
        $amount = $json['amount']['value'] ?? null;
        $createdAt = $json['createdAt'] ?? null;
        $reference = $json['metadata']['raoza_refund_reference'] ?? null;

        if (! is_string($id) || $id === ''
            || ! is_string($paymentId) || $paymentId === ''
            || ! is_string($status) || $status === ''
            || ! is_string($currency) || strlen($currency) !== 3
            || ! is_string($amount)
            || ($createdAt !== null && ! is_string($createdAt))
            || ($reference !== null && ! is_string($reference))) {
            throw new RuntimeException('Mollie returned an invalid refund response.');
        }

        return new ProviderRefund($id, $paymentId, $status, $this->minor($amount), strtoupper($currency), $createdAt, $reference);
    }

    private function decimal(int $minor): string
    {
        if ($minor < 0) {
            throw new RuntimeException('Payment amount cannot be negative.');
        }

        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function minor(string $decimal): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D', $decimal, $matches)) {
            throw new RuntimeException('Mollie returned an invalid payment amount.');
        }

        return ((int) $matches[1] * 100) + (int) substr($decimal, -2);
    }
}
