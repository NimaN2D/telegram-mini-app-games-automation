<?php

namespace App\Pipes\Hamster;

use App\Services\HamsterService;
use Closure;

class HandleBoosts
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $boosts = $hamsterService->getResponseData('/clicker/boosts-for-buy', 'boostsForBuy');
        foreach ($boosts as $boost) {
            if ($boost['id'] === 'BoostFullAvailableTaps' && $boost['cooldownSeconds'] === 0) {
                $hamsterService->postAndLogResponse('/clicker/buy-boost', [
                    'boostId' => $boost['id'],
                    'timestamp' => time(),
                ]);
            }
        }

        return $next($hamsterService);
    }
}
