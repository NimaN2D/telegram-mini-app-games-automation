<?php

namespace App\Console\Commands;

use App\Pipes\MuskEmpire\ClaimQuest;
use App\Services\MuskEmpireService;
use Illuminate\Console\Command;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;

class PlayMuskEmpireCommand extends Command
{
    protected $signature = 'play:musk-empire';
    protected $description = 'Automate the Musk Empire game.';
    protected MuskEmpireService $hamsterService;
    protected Pipeline $pipeline;

    public function __construct(MuskEmpireService $muskEmpireService, Pipeline $pipeline)
    {
        parent::__construct();
        $this->hamsterService = $muskEmpireService;
        $this->pipeline = $pipeline;
    }

    public function handle(): void
    {
        $this->info('Starting Musk Empire automation...');
        Log::info('MuskEmpire | Starting Musk Empire automation...');

        try {
            $this->pipeline
                ->send($this->hamsterService)
                ->through([
                    \App\Pipes\MuskEmpire\Authenticate::class,
                    \App\Pipes\MuskEmpire\Sync::class,
                    \App\Pipes\MuskEmpire\ClaimQuest::class,
                    \App\Pipes\MuskEmpire\HandleTaps::class,
                    \App\Pipes\MuskEmpire\Sync::class,
                    \App\Pipes\MuskEmpire\HandleUpgrades::class
                ])
                ->then(function ($muskEmpireService) {
                    $this->info('Musk Empire automation completed.');
                });
        } catch (\Exception $e) {
            $this->error('Error during automation: ' . $e->getMessage());
            Log::error('MuskEmpire | Error during automation', ['exception' => $e]);
        }
    }
}
