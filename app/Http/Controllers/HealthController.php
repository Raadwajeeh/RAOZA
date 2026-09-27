<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'raoza',
            'release' => config('production.release'),
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = ['database' => false];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            // Do not expose connection details or exception messages.
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'release' => config('production.release'),
        ], $ready ? 200 : 503);
    }
}
