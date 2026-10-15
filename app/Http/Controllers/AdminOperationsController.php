<?php

namespace App\Http\Controllers;

use App\Domain\Admin\Services\AuditService;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Models\Shipment;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Invoicing\Services\InvoicePdfService;
use App\Domain\Payments\Enums\PaymentAttemptStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Services\PaymentSyncCoordinator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class AdminOperationsController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): Response
    {
        $payments = Payment::query()->with('order:id,order_number,payment_status')->where(function ($q) {
            $q->where('status', PaymentAttemptStatus::ReviewRequired)
                ->orWhere(fn ($x) => $x->where('status',PaymentAttemptStatus::Creating)->where('updated_at','<',now()->subMinutes(15)))
                ->orWhere(fn ($x) => $x->where('status',PaymentAttemptStatus::Paid)->whereHas('order',fn ($o) => $o->whereNotIn('payment_status',[PaymentStatus::Paid->value,PaymentStatus::PartiallyRefunded->value,PaymentStatus::Refunded->value])));
        })->latest()->limit(100)->get()->map(fn ($p) => ['id'=>$p->id,'order'=>$p->order?->order_number,'status'=>$p->status->value,'updated_at'=>$p->updated_at?->toIso8601String(),'retryable'=>(bool)$p->provider_payment_id]);

        $invoices = Invoice::query()->with('order:id,order_number')->latest('issued_at')->get()->filter(function ($invoice) {
            if (! $invoice->pdf_path) return true;
            $disk=Storage::disk((string)config('invoicing.disk','local'));
            if (! $disk->exists($invoice->pdf_path)) return true;
            return hash('sha256',(string)$disk->get($invoice->pdf_path)) !== $invoice->pdf_sha256;
        })->take(100)->map(fn ($i) => ['id'=>$i->id,'number'=>$i->document_number,'order'=>$i->order?->order_number,'issued_at'=>$i->issued_at?->toIso8601String()])->values();

        $failed = DB::table('failed_jobs')->latest('failed_at')->limit(100)->get()->map(function ($job) {
            $payload=json_decode($job->payload,true); $class=class_basename((string)($payload['displayName']??'Queued job'));
            return ['uuid'=>$job->uuid,'queue'=>$job->queue,'job'=>$class,'error'=>str((string)$job->exception)->before(':')->limit(120)->toString(),'failed_at'=>$job->failed_at];
        });
        $shipments = Shipment::query()->with('order:id,order_number')->where(fn ($q) => $q->where('status',ShipmentStatus::Exception)->orWhere(fn($x)=>$x->where('status',ShipmentStatus::Pending)->where('created_at','<',now()->subDay())))->latest()->limit(100)->get()->map(fn($s)=>['id'=>$s->id,'order'=>$s->order?->order_number,'carrier'=>$s->carrier_name,'status'=>$s->status->value,'updated_at'=>$s->updated_at?->toIso8601String()]);
        return Inertia::render('Admin/Operations/Index', compact('payments','invoices','failed','shipments'));
    }

    public function retryPayment(Request $request, Payment $payment, PaymentSyncCoordinator $sync): RedirectResponse
    {
        abort_unless($payment->provider_payment_id, 422);
        $before=['status'=>$payment->status->value];
        try{$result=$sync->sync($payment,'operator_retry');}catch(RuntimeException $e){return back()->withErrors(['payment'=>$e->getMessage()]);}
        $this->audit->record($request,'payment.operator_retried',$payment,$before,['status'=>$result->payment->status->value]);
        return back()->with('success','Payment state synchronized through the configured provider.');
    }

    public function regeneratePdf(Request $request, Invoice $invoice, InvoicePdfService $pdf): RedirectResponse
    {
        $before=['pdf_sha256'=>$invoice->pdf_sha256,'pdf_generated_at'=>$invoice->pdf_generated_at?->toIso8601String()];
        $invoice=$pdf->regenerateStored($invoice);
        $this->audit->record($request,'invoice.pdf_regenerated',$invoice,$before,['pdf_sha256'=>$invoice->pdf_sha256,'pdf_generated_at'=>$invoice->pdf_generated_at?->toIso8601String()]);
        return back()->with('success','The private PDF file was regenerated from the immutable invoice snapshot.');
    }
}
