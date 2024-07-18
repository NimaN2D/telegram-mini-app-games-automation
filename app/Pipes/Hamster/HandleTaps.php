<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;
use Illuminate\Support\Facades\Log;

class HandleTaps
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $availableTaps = $hamsterService->syncData['clickerUser']['availableTaps'];
        $earnPerTap = $hamsterService->syncData['clickerUser']['earnPerTap'];
        $totalTaps = 0;

        while ($availableTaps > 0) {
            $count = min($availableTaps, rand(30, 100));
            $tapResult = $hamsterService->postAndLogResponse('/clicker/tap', [
                'count' => $count,
                'availableTaps' => $availableTaps,
                'timestamp' => time()
            ]);
            $availableTaps -= ($count * $earnPerTap);
            $totalTaps += ($count * $earnPerTap);
        }

        Log::info("Total Taps Made: {$totalTaps}");

        return $next($hamsterService);
    }
}
