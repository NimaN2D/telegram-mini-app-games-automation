<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;
use Illuminate\Support\Facades\Log;

class HandleTaps
{
    public function handle(MuskEmpireService $muskEmpireService, Closure $next)
    {
        $availableTaps = $muskEmpireService->syncData['hero']['earns']['task']['energy'];
        $earnPerTap = $muskEmpireService->syncData['hero']['earns']['task']['moneyPerTap'];
        $totalTaps = 0;

        while ($availableTaps > 0) {
            sleep(mt_rand(1,3));
            $count = min($availableTaps, rand(45, 160));
            $availableTaps -= ($count * $earnPerTap);
            $tapResult = $muskEmpireService->postAndLogResponse('/hero/action/tap', [
                "data" => [
                    "data" => [
                        "task" => [
                            "amount" => $count,
                            "currentEnergy" => $availableTaps
                        ]
                    ],
                    "seconds" => mt_rand(3, 13)
                ]
            ]);
            $totalTaps += ($count * $earnPerTap);
        }

        Log::info("MustEmpire | Total Taps Made: {$totalTaps}");

        return $next($muskEmpireService);
    }
}
