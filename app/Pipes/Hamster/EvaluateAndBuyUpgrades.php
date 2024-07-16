<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class EvaluateAndBuyUpgrades
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $upgrades = $hamsterService->getUpgradesForBuy();
        $hamsterService->evaluateAndBuyUpgrades($upgrades);
        return $next($hamsterService);
    }
}
