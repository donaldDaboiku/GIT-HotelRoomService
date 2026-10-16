<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): \Illuminate\Http\JsonResponse
    {
        $database = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'unavailable';
        }

        return response()->json([
            'data' => [
                'status' => $database === 'ok' ? 'ok' : 'degraded',
                'database' => $database,
            ],
        ], $database === 'ok' ? 200 : 503);
    }
}
