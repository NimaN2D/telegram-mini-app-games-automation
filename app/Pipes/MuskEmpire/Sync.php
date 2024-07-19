<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;
use Illuminate\Support\Facades\Log;

class Sync
{
    public function handle(MuskEmpireService $muskEmpireService, Closure $next)
    {
        try {
            $syncData = $muskEmpireService->postAndLogResponse('/user/data/all');
            $muskEmpireService->setSyncData($syncData['data']);
        } catch (\Exception $e) {
            Log::error('MuskEmpire | Sync failed', ['exception' => $e]);
            throw new \Exception('Sync failed');
        }

        return $next($muskEmpireService);
    }
}
