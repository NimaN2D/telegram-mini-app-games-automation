<?php

namespace App\Console\Commands;

use App\Services\MuskEmpireService;
use Illuminate\Console\Command;
use Illuminate\Pipeline\Pipeline;

class PlayMuskEmpireCommand extends Command
{
    protected $signature = 'play:musk-empire';

    protected $description = 'Automate the Musk Empire game.';

    protected MuskEmpireService $muskEmpireService;

    protected Pipeline $pipeline;

    public function __construct(MuskEmpireService $muskEmpireService)
    {
        parent::__construct();
        $this->muskEmpireService = $muskEmpireService;
    }

    public function handle(): void
    {
        $message = $this->muskEmpireService->play();
        $this->info($message);
    }
}
