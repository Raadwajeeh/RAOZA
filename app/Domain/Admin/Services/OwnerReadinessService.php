<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\LaunchApproval;
use App\Domain\Content\Models\ContentPage;
use App\Domain\Content\Models\SiteContent;
use App\Domain\Fulfillment\Models\ShippingCarrier;
use App\Domain\Fulfillment\Models\ShippingMethod;
use App\Domain\Invoicing\Services\InvoicingReadiness;
use Illuminate\Support\Arr;

final class OwnerReadinessService
{
    public const POLICY_KEYS = ['terms', 'privacy', 'cookies', 'shipping', 'returns', 'contact', 'faq', 'size-guide'];

    public function __construct(private InvoicingReadiness $invoicing) {}

    public function hash(mixed $value): string
    {
        return hash('sha256', json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function dashboard(): array
    {
        $definitions = $this->definitions();
        $latest = LaunchApproval::query()->with(['submitter:id,name', 'approver:id,name'])->latest('id')->get()->unique('decision_key')->keyBy('decision_key');

        $decisions = collect($definitions)->map(function (array $definition, string $key) use ($latest) {
            $version = $this->versionFor($key);
            $record = $latest->get($key);
            $status = $record && hash_equals($record->version_hash, $version) ? $record->status : 'pending';
            if ($record && ! hash_equals($record->version_hash, $version) && $record->status !== 'superseded') {
                $status = 'pending';
            }

            return $definition + [
                'decision_key' => $key,
                'status' => $status,
                'version_hash' => $version,
                'last_update' => $record?->updated_at?->toIso8601String(),
                'approval' => $record,
            ];
        })->values();

        return [
            'groups' => $decisions->groupBy('category')->map->values(),
            'technicalChecks' => $this->technicalChecks(),
            'providerChecks' => $this->providerChecks(),
            'invoicing' => [
                'ready' => $this->invoicing->ready(),
                'issuance_enabled' => (bool) config('invoicing.issuance_enabled'),
                'blockers' => $this->invoicing->blockers(),
            ],
        ];
    }

    public function definition(string $key): array
    {
        return $this->definitions()[$key] ?? throw new \InvalidArgumentException('Unknown readiness decision.');
    }

    public function versionFor(string $key): string
    {
        if ($key === 'business.identity') {
            return $this->hash(SiteContent::where('key', 'store_information_draft')->value('value') ?? []);
        }
        if ($key === 'commercial.shipping_methods') {
            return $this->hash(ShippingMethod::orderBy('id')->get()->map(fn ($m) => $m->pending_configuration ?: Arr::only($m->toArray(), ['name', 'code', 'provider', 'price', 'currency', 'active', 'position', 'configuration']))->all());
        }
        if (str_starts_with($key, 'policy.')) {
            $pageKey = substr($key, 7);
            $page = ContentPage::where('key', $pageKey)->first();

            return $this->hash($page?->draft_payload ?: ($page ? Arr::only($page->toArray(), ['title', 'slug', 'status', 'content', 'seo_title', 'seo_description', 'canonical_url', 'og_image', 'indexable']) : []));
        }

        return $this->hash(match ($key) {
            'commercial.delivery_scope' => ['country_code' => 'NL', 'shipping_origin' => (SiteContent::where('key', 'store_information_draft')->value('value') ?? [])['shipping_origin'] ?? null],
            'commercial.returns_commitment' => ContentPage::where('key', 'returns')->value('draft_payload') ?? ContentPage::where('key', 'returns')->value('content') ?? [],
            'accounting.vat_policy' => Arr::only(config('invoicing'), ['accounting_policy_approved', 'seller_profile_approved', 'numbering', 'credit_notes']),
            'provider.mollie' => ['provider' => config('payments.default'), 'configured' => filled(config('payments.mollie.api_key'))],
            'provider.smtp' => ['mailer' => config('mail.default'), 'configured' => config('mail.default') === 'smtp'],
            'operations.runtime' => ['queue' => config('queue.default'), 'retention' => config('retention.pruning_enabled')],
            default => [],
        });
    }

    public function supersede(string $decisionKey): void
    {
        LaunchApproval::where('decision_key', $decisionKey)->whereIn('status', ['submitted', 'approved'])->update(['status' => 'superseded', 'decided_at' => now()]);
    }

    private function definitions(): array
    {
        $definitions = [
            'business.identity' => $this->item('Owner decisions pending', 'Business identity and public contact details', 'StoreInformation draft', 'legal', 'KvK/VAT/address/telephone evidence and legal review.'),
            'commercial.delivery_scope' => $this->item('Owner decisions pending', 'Netherlands-only delivery scope', 'Store and checkout configuration', 'owner', 'Approved address-country restriction and delivery scope.'),
            'commercial.shipping_methods' => $this->item('Owner decisions pending', 'Shipping methods, prices and delivery statements', 'ShippingMethod pending configuration', 'owner', 'Owner-approved customer prices and realistic promises.'),
            'commercial.returns_commitment' => $this->item('Legal review pending', 'Return method, costs and support commitment', 'Returns content draft', 'legal', 'Legal review of statutory and commercial commitments.'),
            'accounting.vat_policy' => $this->item('Accountant decisions pending', 'VAT, numbering and accounting policy', 'Fail-closed invoicing configuration', 'accountant', 'External accountant evidence; application flags remain environment-managed.'),
            'provider.mollie' => $this->item('External provider setup pending', 'Mollie production readiness', 'Payment configuration (credentials hidden)', 'external_provider', 'Provider onboarding and production credential verification.'),
            'provider.smtp' => $this->item('External provider setup pending', 'SMTP delivery readiness', 'Mail configuration (credentials hidden)', 'external_provider', 'Provider account, DNS and deliverability verification.'),
            'operations.runtime' => $this->item('Operational verification pending', 'Queue, scheduler, carriers and backups', 'Runtime and operations configuration', 'external_provider', 'Worker/scheduler supervision, carrier setup and tested restore evidence.'),
        ];
        foreach (self::POLICY_KEYS as $key) {
            $role = in_array($key, ['contact', 'faq', 'size-guide'], true) ? 'owner' : 'legal';
            $definitions['policy.'.$key] = $this->item($role === 'legal' ? 'Legal review pending' : 'Owner decisions pending', ucfirst(str_replace('-', ' ', $key)).' content', 'Content administration', $role, ($role === 'legal' ? 'Legal review and evidence.' : 'Owner content review.').' Publishing remains a separate action.');
        }

        return $definitions;
    }

    private function item(string $category, string $label, string $source, string $requiredRole, string $dependencies): array
    {
        return compact('category', 'label', 'source', 'requiredRole', 'dependencies');
    }

    private function technicalChecks(): array
    {
        return [
            ['label' => 'Invoice issuance defaults fail-closed', 'status' => config('invoicing.issuance_enabled') ? 'attention' : 'passed', 'source' => 'config/invoicing.php'],
            ['label' => 'Retention pruning defaults fail-closed', 'status' => config('retention.pruning_enabled') ? 'attention' : 'passed', 'source' => 'config/retention.php'],
            ['label' => 'Approval mutations are Owner-only', 'status' => 'passed', 'source' => 'readiness.manage authorization'],
        ];
    }

    private function providerChecks(): array
    {
        return [
            ['label' => 'Mollie', 'ready' => config('payments.default') === 'mollie' && filled(config('payments.mollie.api_key')), 'detail' => config('payments.default') === 'demo' ? 'Demo mode; production payment mode is not enabled.' : 'Configuration presence only; credential value is never shown.'],
            ['label' => 'SMTP', 'ready' => config('mail.default') === 'smtp', 'detail' => 'Transport mode only; credentials are never shown.'],
            ['label' => 'Queue', 'ready' => config('queue.default') !== 'sync', 'detail' => 'Configuration presence only; worker supervision still requires operational verification.'],
            ['label' => 'Scheduler', 'ready' => false, 'detail' => 'Runtime execution cannot be proven from application configuration.'],
            ['label' => 'Carriers', 'ready' => ShippingCarrier::where('active', true)->exists(), 'detail' => ShippingCarrier::where('active', true)->count().' active carrier configuration(s); external connectivity is not tested.'],
            ['label' => 'Backups', 'ready' => false, 'detail' => 'A successful restore drill requires external evidence.'],
        ];
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
