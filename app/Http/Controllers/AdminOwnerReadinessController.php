<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\LaunchApproval;
use App\Domain\Admin\Services\AuditService;
use App\Domain\Admin\Services\OwnerReadinessService;
use App\Domain\Content\Models\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminOwnerReadinessController extends Controller
{
    public function __construct(private OwnerReadinessService $readiness, private AuditService $audit) {}

    public function index()
    {
        abort_unless(request()->user()?->role === AdminRole::Owner, 403);

        return Inertia::render('Admin/Readiness/Index', $this->readiness->dashboard() + [
            'businessDraft' => SiteContent::where('key', 'store_information_draft')->value('value') ?? [],
            'businessPublic' => SiteContent::where('key', 'store_information')->value('value') ?? [],
        ]);
    }

    public function decide(Request $request)
    {
        abort_unless($request->user()?->role === AdminRole::Owner, 403);
        $data = $request->validate([
            'decision_key' => 'required|string|max:160',
            'action' => 'required|in:submitted,approved,rejected',
            'version_hash' => 'required|size:64',
            'notes' => 'nullable|string|max:2000',
            'evidence_reference' => 'nullable|string|max:500',
        ]);
        if (preg_match('/(?:api[_ -]?key|password\s*[=:]|bearer\s+[a-z0-9._-]{12,}|(?:live|test)_[a-z0-9]{12,})/i', ($data['notes'] ?? '').' '.($data['evidence_reference'] ?? ''))) {
            throw ValidationException::withMessages(['evidence_reference' => 'Do not enter credentials or secrets. Store only a non-sensitive reference.']);
        }
        $definition = $this->readiness->definition($data['decision_key']);
        $currentHash = $this->readiness->versionFor($data['decision_key']);
        if (! hash_equals($currentHash, $data['version_hash'])) {
            throw ValidationException::withMessages(['version_hash' => 'This configuration changed. Reload before recording a decision.']);
        }
        if ($data['action'] === 'approved' && $definition['requiredRole'] !== 'owner') {
            throw ValidationException::withMessages(['action' => 'This decision requires verified '.$definition['requiredRole'].' sign-off. The Owner can submit evidence, but cannot create that approval.']);
        }
        if ($data['action'] === 'approved' && blank($data['evidence_reference'] ?? null)) {
            throw ValidationException::withMessages(['evidence_reference' => 'An evidence or reference is required for approval.']);
        }

        $record = DB::transaction(function () use ($request, $data, $definition, $currentHash) {
            LaunchApproval::where('decision_key', $data['decision_key'])
                ->where('version_hash', '!=', $currentHash)
                ->whereIn('status', ['submitted', 'approved'])
                ->update(['status' => 'superseded', 'decided_at' => now()]);

            $record = LaunchApproval::firstOrNew(['decision_key' => $data['decision_key'], 'version_hash' => $currentHash]);
            $before = $record->exists ? $record->toArray() : null;
            $record->fill([
                'category' => $definition['category'],
                'required_role' => $definition['requiredRole'],
                'status' => $data['action'],
                'notes' => $data['notes'] ?? null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
                'submitted_by' => $request->user()->id,
                'submitted_at' => $record->submitted_at ?: now(),
                'approved_by' => $data['action'] === 'approved' ? $request->user()->id : null,
                'approver_identity' => $data['action'] === 'approved' ? $request->user()->name : null,
                'approver_role' => $data['action'] === 'approved' ? 'owner' : null,
                'decided_at' => in_array($data['action'], ['approved', 'rejected'], true) ? now() : null,
            ])->save();
            $this->audit->record($request, 'launch_approval.'.$data['action'], $record, $before, $record->fresh()->toArray());

            return $record;
        });

        return back()->with('success', ucfirst($record->status).' recorded for the current version. No content or integration was activated.');
    }
}
