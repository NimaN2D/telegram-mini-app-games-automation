<?php

namespace App\Console\Commands;

use App\Services\HamsterService;
use Illuminate\Console\Command;

class PlayHamsterCommand extends Command
{
    protected $signature = 'play:hamster';

    protected $description = 'Automate the Hamster Kombat game process';

    protected HamsterService $hamsterService;

    public function __construct(HamsterService $hamsterService)
    {
        parent::__construct();
        $this->hamsterService = $hamsterService;
    }

    public function handle(): void
    {
        $message = $this->hamsterService->play();
        $this->info($message);
    }
}
