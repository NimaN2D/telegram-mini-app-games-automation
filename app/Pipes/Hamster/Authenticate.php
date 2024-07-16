<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;

class Authenticate
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $hamsterService->authenticate();
        return $next($hamsterService);
    }
}
