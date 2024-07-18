<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class HandleStreakDaysTask
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $hamsterService->handleStreakDaysTask();
        return $next($hamsterService);
    }
}
