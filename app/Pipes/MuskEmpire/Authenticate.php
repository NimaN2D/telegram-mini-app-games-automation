<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;

class Authenticate
{
    public function handle(MuskEmpireService $empireService, Closure $next)
    {
        $empireService->postAndLogResponse('/telegram/auth', [
            'data' => [
                'initData' => config('muskempire.init_data'),
                'platform' => 'android',
            ],
        ]);

        parse_str(config('muskempire.init_data'), $params);
        $empireService->setApiKey($params['hash'] ?? null);

        return $next($empireService);
    }
}
