<?php

namespace App\Domain\Invoicing\Services;

use App\Domain\Commerce\Models\Order;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;

final class OperationalDocumentService
{
    public const TYPES = ['packing-slip', 'picking-list', 'order-sheet'];

    public function render(Order $order, string $type): string
    {
        if (! in_array($type, self::TYPES, true)) throw new InvalidArgumentException('Unsupported operational document type.');
        $order->loadMissing(['items', 'addresses', 'shipments']);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml(view('documents.operational', ['order' => $order, 'type' => $type])->render(), 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
