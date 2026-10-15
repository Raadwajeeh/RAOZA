<?php

namespace App\Domain\Invoicing\Services;

use App\Domain\Invoicing\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class InvoicePdfService
{
    public function __construct(private MinorMoneyFormatter $money) {}

    public function ensureStored(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['lines', 'order', 'originalInvoice']);
        $disk = (string) config('invoicing.disk', 'local');
        if ($invoice->pdf_path && Storage::disk($disk)->exists($invoice->pdf_path)) return $invoice;

        $contents = $this->render($invoice);
        if (! str_starts_with($contents, '%PDF-')) throw new RuntimeException('The generated document is not a valid PDF stream.');
        $path = 'accounting/'.$invoice->document_type->value.'/'.$invoice->issued_at->format('Y').'/'.$invoice->document_number.'.pdf';
        if (! Storage::disk($disk)->put($path, $contents)) throw new RuntimeException('The accounting PDF could not be written to private storage.');
        $invoice->update(['pdf_path' => $path, 'pdf_sha256' => hash('sha256', $contents), 'pdf_generated_at' => now()]);
        return $invoice->refresh();
    }

    public function contents(Invoice $invoice): string
    {
        $invoice = $this->ensureStored($invoice);
        $contents = Storage::disk((string) config('invoicing.disk', 'local'))->get($invoice->pdf_path);
        if (! is_string($contents) || hash('sha256', $contents) !== $invoice->pdf_sha256) {
            throw new RuntimeException('The stored accounting PDF failed its integrity check.');
        }
        return $contents;
    }

    public function regenerateStored(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['lines', 'order', 'originalInvoice']);
        $contents = $this->render($invoice);
        if (! str_starts_with($contents, '%PDF-')) throw new RuntimeException('The generated document is not a valid PDF stream.');
        $path = 'accounting/'.$invoice->document_type->value.'/'.$invoice->issued_at->format('Y').'/'.$invoice->document_number.'.pdf';
        if (! Storage::disk((string) config('invoicing.disk', 'local'))->put($path, $contents)) throw new RuntimeException('The accounting PDF could not be written to private storage.');
        $invoice->update(['pdf_path'=>$path,'pdf_sha256'=>hash('sha256',$contents),'pdf_generated_at'=>now()]);
        return $invoice->refresh();
    }

    private function render(Invoice $invoice): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [resource_path('views')]);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('documents.accounting', [
            'invoice' => $invoice,
            'money' => $this->money,
            'qaWatermark' => app()->environment('testing'),
        ])->render(), 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
