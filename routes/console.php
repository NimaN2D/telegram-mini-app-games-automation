<?php

use App\Console\Commands\PlayHamsterCommand;
use Illuminate\Support\Facades\Schedule;


Schedule::command(PlayHamsterCommand::class)->everyFiveMinutes();
