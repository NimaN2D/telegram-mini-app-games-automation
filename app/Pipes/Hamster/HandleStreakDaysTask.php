<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;
use Illuminate\Support\Facades\Log;

class HandleStreakDaysTask
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $tasks = $hamsterService->getResponseData('/clicker/list-tasks', 'tasks');
        foreach ($tasks as $task) {
            if ($task['id'] === 'streak_days' && !$task['isCompleted']) {
                $hamsterService->postAndLogResponse('/clicker/check-task', [
                    'taskId' => $task['id']
                ]);
                Log::info("Completed streak_days task", ['taskId' => $task['id']]);
            }
        }

        return $next($hamsterService);
    }
}
