<?php

namespace App\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class FifteenMinuteLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('fifteen_minute');
        $interval = (int) (date('i') / 15) * 15;
        $logPath = storage_path('logs/fifteen/laravel-' . date('Y-m-d-H') . '-' . str_pad($interval, 2, '0', STR_PAD_LEFT) . '.log');
        $logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
        return $logger;
    }
}
