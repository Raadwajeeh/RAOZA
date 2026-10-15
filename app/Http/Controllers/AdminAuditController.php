<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdminAuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable','string','max:100'], 'action' => ['nullable','string','max:80'], 'from' => ['nullable','date'], 'to' => ['nullable','date','after_or_equal:from']]);
        $logs = AuditLog::query()->with('actor:id,name,email')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($x) => $x->where('action','like','%'.$term.'%')->orWhere('subject_type','like','%'.$term.'%')->orWhere('subject_id','like','%'.$term.'%')->orWhereHas('actor', fn ($a) => $a->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%'))))
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at','>=',$from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at','<=',$to))
            ->latest('id')->paginate(30)->withQueryString()->through(fn ($log) => [
                'id'=>$log->id,'action'=>$log->action,'actor'=>$log->actor?->only('name','email'),
                'subject'=>class_basename($log->subject_type).($log->subject_id ? ' #'.$log->subject_id : ''),
                'before'=>$log->before,'after'=>$log->after,'ip'=>$log->ip_address,'created_at'=>$log->created_at?->toIso8601String(),
            ]);
        return Inertia::render('Admin/Audit/Index', ['logs'=>$logs,'filters'=>$filters,'actions'=>AuditLog::query()->distinct()->orderBy('action')->pluck('action')]);
    }
}
