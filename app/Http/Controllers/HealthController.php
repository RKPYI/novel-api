<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Public health check endpoint
     * Used by external monitoring systems
     * Does not require authentication and works even when database is down
     *
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'up',
            'timestamp' => now()->toIso8601String(),
            'checks' => []
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $health['checks']['database'] = 'healthy';
        } catch (\Exception $e) {
            $health['checks']['database'] = 'unhealthy';
            $health['status'] = 'degraded';
        }

        // Cache check
        try {
            $testKey = 'health_check_' . time();
            cache()->put($testKey, 'test', 5);
            $retrieved = cache()->get($testKey);
            cache()->forget($testKey);
            $health['checks']['cache'] = ($retrieved === 'test') ? 'healthy' : 'unhealthy';

            if ($health['checks']['cache'] === 'unhealthy') {
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['checks']['cache'] = 'unhealthy';
            $health['status'] = 'degraded';
        }

        // Storage check
        try {
            $storagePath = storage_path('app');
            $health['checks']['storage'] = is_writable($storagePath) ? 'healthy' : 'unhealthy';

            if ($health['checks']['storage'] === 'unhealthy') {
                $health['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $health['checks']['storage'] = 'unhealthy';
            $health['status'] = 'degraded';
        }

        // Return appropriate HTTP status code
        $statusCode = match($health['status']) {
            'up' => 200,
            'degraded' => 200, // Still return 200 but with degraded status
            default => 503
        };

        return response()->json($health, $statusCode);
    }
}
