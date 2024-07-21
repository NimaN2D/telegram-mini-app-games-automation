<?php

use App\Console\Commands\PlayHamsterCommand;
use App\Console\Commands\PlayMuskEmpireCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(PlayHamsterCommand::class)->everyFiveMinutes();
Schedule::command(PlayMuskEmpireCommand::class)->everyTwoMinutes();
