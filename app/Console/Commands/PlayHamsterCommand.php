<?php

namespace App\Console\Commands;

use App\Pipes\Hamster\Authenticate;
use App\Pipes\Hamster\HandleBoosts;
use App\Pipes\Hamster\HandleStreakDaysTask;
use App\Pipes\Hamster\HandleTaps;
use App\Pipes\Hamster\HandleUpgrades;
use App\Pipes\Hamster\Sync;
use Illuminate\Console\Command;
use App\Services\HamsterService;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;

class PlayHamsterCommand extends Command
{
    protected $signature = 'play:hamster';
    protected $description = 'Automate the Hamster Kombat game process';
    protected HamsterService $hamsterService;
    protected Pipeline $pipeline;

    public function __construct(HamsterService $hamsterService, Pipeline $pipeline)
    {
        parent::__construct();
        $this->hamsterService = $hamsterService;
        $this->pipeline = $pipeline;
    }

    public function handle(): void
    {
        $this->info('Starting Hamster Kombat automation...');
        Log::info('Starting Hamster Kombat automation...');

        try {
            $this->pipeline
                ->send($this->hamsterService)
                ->through([
                    Authenticate::class,
                    HandleStreakDaysTask::class,
                    Sync::class,
                    HandleBoosts::class,
                    HandleTaps::class,
                    HandleUpgrades::class,
                ])
                ->then(function ($hamsterService) {
                    $this->info('Hamster Kombat automation completed.');
                });
        } catch (\Exception $e) {
            $this->error('Error during automation: ' . $e->getMessage());
            Log::error('Error during automation', ['exception' => $e]);
        }
    }
}
