<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Services\GuestOrderAccessService;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Invoicing\Services\InvoicePdfService;
use Symfony\Component\HttpFoundation\Response;

class GuestDocumentController extends Controller
{
    public function download(string $token, string $documentNumber, GuestOrderAccessService $access, InvoicePdfService $pdf): Response
    {
        $order = $access->resolve($token)['order'];
        $document = Invoice::query()->where('order_id', $order->id)->where('document_number', $documentNumber)->firstOrFail();
        return response($pdf->contents($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->document_number.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
