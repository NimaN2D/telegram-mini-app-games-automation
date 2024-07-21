<?php

namespace Tests\Unit;

use App\Console\Commands\PlayHamsterCommand;
use App\Services\HamsterService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class PlayHamsterCommandTest extends TestCase
{
    public function testPlayHamsterCommand()
    {
        // Mock the HamsterService
        $hamsterService = Mockery::mock(HamsterService::class);
        $hamsterService->shouldReceive('play')->once()->andReturn('Hamster Kombat automation completed.');

        // Instantiate the command with the mocked service
        $command = new PlayHamsterCommand($hamsterService);

        // Replace the command instance in the application container
        $this->app->instance(PlayHamsterCommand::class, $command);

        // Execute the command and assert the output
        Artisan::call('play:hamster');
        $output = Artisan::output();
        $this->assertStringContainsString('Hamster Kombat automation completed.', $output);
    }
}
