<?php

namespace App\Pipes\Hamster;

use App\Services\HamsterService;
use Closure;
use Illuminate\Support\Facades\Log;

class Sync
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        try {
            $syncData = $hamsterService->postAndLogResponse('/clicker/sync');
            $hamsterService->setSyncData($syncData);
        } catch (\Exception $e) {
            Log::error('Hamster | Sync failed', ['exception' => $e]);
            throw new \Exception('Sync failed');
        }

        return $next($hamsterService);
    }
}
