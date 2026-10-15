<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function __construct(private AuditSanitizer $sanitizer) {}

    public function record(Request $request, string $action, Model $subject, ?array $before = null, ?array $after = null): void
    {
        AuditLog::query()->create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (string) $subject->getKey(),
            'before' => $this->sanitizer->clean($before),
            'after' => $this->sanitizer->clean($after),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
