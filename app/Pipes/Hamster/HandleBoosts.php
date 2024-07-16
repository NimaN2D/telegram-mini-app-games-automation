<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class HandleBoosts
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $boosts = $hamsterService->getBoostsForBuy();
        $hamsterService->handleBoosts($boosts);
        return $next($hamsterService);
    }
}
