<?php

namespace Tests\Feature;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\LaunchApproval;
use App\Domain\Admin\Services\OwnerReadinessService;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_access_and_mutate_readiness_register(): void
    {
        $owner = User::factory()->create(['role' => AdminRole::Owner]);
        $admin = User::factory()->create(['role' => AdminRole::Admin]);
        $customer = User::factory()->create(['role' => AdminRole::Customer]);

        $this->actingAs($owner)->get('/admin/readiness')->assertOk();
        $this->actingAs($admin)->get('/admin/readiness')->assertForbidden();
        $this->actingAs($customer)->get('/admin/readiness')->assertForbidden();
        $this->actingAs($admin)->post('/admin/readiness/decisions', [])->assertForbidden();
    }

    public function test_owner_cannot_forge_accountant_or_legal_approval(): void
    {
        $owner = User::factory()->create(['role' => AdminRole::Owner]);
        $service = app(OwnerReadinessService::class);

        $this->actingAs($owner)->post('/admin/readiness/decisions', [
            'decision_key' => 'accounting.vat_policy',
            'action' => 'approved',
            'version_hash' => $service->versionFor('accounting.vat_policy'),
            'evidence_reference' => 'internal-note-1',
        ])->assertSessionHasErrors('action');

        $this->assertDatabaseCount('launch_approvals', 0);
    }

    public function test_changed_configuration_supersedes_an_owner_approval(): void
    {
        $owner = User::factory()->create(['role' => AdminRole::Owner]);
        $service = app(OwnerReadinessService::class);
        $hash = $service->versionFor('commercial.delivery_scope');

        $this->actingAs($owner)->post('/admin/readiness/decisions', [
            'decision_key' => 'commercial.delivery_scope', 'action' => 'approved',
            'version_hash' => $hash, 'evidence_reference' => 'owner/minutes/2026-10-15',
        ])->assertSessionHasNoErrors();

        $this->patch('/admin/store', $this->businessDraft())->assertSessionHasNoErrors();
        $this->assertSame('superseded', LaunchApproval::where('decision_key', 'commercial.delivery_scope')->firstOrFail()->status);
        $this->assertNotSame($hash, $service->versionFor('commercial.delivery_scope'));
    }

    public function test_policy_edit_is_staged_without_publication_and_invalidates_approval(): void
    {
        $owner = User::factory()->create(['role' => AdminRole::Owner]);
        $page = ContentPage::create(['key' => 'contact', 'title' => 'Contact', 'slug' => 'contact', 'status' => ContentStatus::Published, 'content' => ['intro' => 'Live copy'], 'indexable' => true, 'published_at' => now()]);
        $service = app(OwnerReadinessService::class);
        LaunchApproval::create(['category' => 'Owner decisions pending', 'decision_key' => 'policy.contact', 'required_role' => 'owner', 'status' => 'approved', 'version_hash' => $service->versionFor('policy.contact'), 'approved_by' => $owner->id, 'approver_identity' => $owner->name, 'approver_role' => 'owner', 'decided_at' => now()]);

        $this->actingAs($owner)->patch("/admin/content/pages/{$page->id}", [
            'title' => 'Contact revised', 'slug' => 'contact', 'status' => 'published',
            'seo_title' => null, 'seo_description' => null, 'canonical_url' => null,
            'og_image' => null, 'indexable' => true, 'content' => ['intro' => 'Unapproved copy'],
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame('Contact', $page->title);
        $this->assertSame('Live copy', $page->content['intro']);
        $this->assertSame(ContentStatus::Published, $page->status);
        $this->assertSame('Unapproved copy', $page->draft_payload['content']['intro']);
        $this->assertSame('draft', $page->draft_payload['status']);
        $this->assertSame('superseded', LaunchApproval::where('decision_key', 'policy.contact')->firstOrFail()->status);
    }

    public function test_readiness_decisions_do_not_enable_invoicing_or_providers(): void
    {
        config()->set('invoicing.issuance_enabled', false);
        config()->set('payments.default', 'demo');
        $owner = User::factory()->create(['role' => AdminRole::Owner]);
        $service = app(OwnerReadinessService::class);

        $this->actingAs($owner)->post('/admin/readiness/decisions', [
            'decision_key' => 'provider.mollie', 'action' => 'submitted',
            'version_hash' => $service->versionFor('provider.mollie'), 'evidence_reference' => 'provider-case-123',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(config('invoicing.issuance_enabled'));
        $this->assertSame('demo', config('payments.default'));
        $this->assertSame('submitted', LaunchApproval::firstOrFail()->status);
        $this->assertNull(LaunchApproval::firstOrFail()->approved_by);
    }

    private function businessDraft(): array
    {
        return ['brand_name' => 'RAOZA', 'company_name' => 'Owner supplied company', 'contact_email' => 'owner@example.test', 'support_email' => 'support@example.test', 'country' => 'Netherlands', 'country_code' => 'NL', 'currency' => 'EUR', 'locale' => 'en-NL', 'shipping_origin' => 'Netherlands', 'customer_service' => 'Email support.'];
    }
}
