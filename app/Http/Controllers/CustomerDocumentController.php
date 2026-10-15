<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Models\Order;
use App\Domain\Invoicing\Models\Invoice;
use App\Domain\Invoicing\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerDocumentController extends Controller
{
    public function download(Request $request, string $orderNumber, string $documentNumber, InvoicePdfService $pdf): Response
    {
        $order = Order::query()->where('user_id', $request->user()->id)->where('order_number', $orderNumber)->firstOrFail();
        $document = Invoice::query()->where('order_id', $order->id)->where('document_number', $documentNumber)->firstOrFail();
        return response($pdf->contents($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->document_number.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
