<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class HourlyLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('hourly');
        $logPath = storage_path('logs/hour/laravel-'.date('Y-m-d-H').'.log');
        $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));

        return $logger;
    }
}
