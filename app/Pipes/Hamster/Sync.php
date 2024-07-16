<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class Sync
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $hamsterService->sync();
        return $next($hamsterService);
    }
}
