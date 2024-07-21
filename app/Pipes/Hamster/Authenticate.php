<?php

namespace App\Pipes\Hamster;

use App\Services\HamsterService;
use Closure;
use Illuminate\Support\Facades\Cache;

class Authenticate
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $authToken = Cache::remember('hamsterAuthKey', now()->addHour(), function () use ($hamsterService) {
            $response = $hamsterService->postAndLogResponse('/auth/auth-by-telegram-webapp', [
                'initDataRaw' => config('hamster.init_data_raw'),
                'fingerprint' => json_decode(config('hamster.fingerprint'), true),
            ]);

            return $response['authToken'];
        });

        $hamsterService->setAuthToken($authToken);

        return $next($hamsterService);
    }
}
