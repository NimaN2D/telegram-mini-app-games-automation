<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class HandleTaps
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $hamsterService->handleTaps();
        return $next($hamsterService);
    }
}
