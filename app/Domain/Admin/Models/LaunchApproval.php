<?php

namespace App\Domain\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaunchApproval extends Model
{
    protected $fillable = [
        'category', 'decision_key', 'required_role', 'status', 'version_hash', 'notes',
        'evidence_reference', 'approver_identity', 'approver_role', 'submitted_by',
        'approved_by', 'submitted_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
