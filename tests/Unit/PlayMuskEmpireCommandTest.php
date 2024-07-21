<?php

namespace Tests\Unit;

use App\Console\Commands\PlayMuskEmpireCommand;
use App\Services\MuskEmpireService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class PlayMuskEmpireCommandTest extends TestCase
{
    public function testPlayMuskEmpireCommand()
    {
        // Mock the MuskEmpireService
        $muskEmpireService = Mockery::mock(MuskEmpireService::class);
        $muskEmpireService->shouldReceive('play')->once()->andReturn('Musk Empire automation completed.');

        // Instantiate the command with the mocked service
        $command = new PlayMuskEmpireCommand($muskEmpireService);

        // Replace the command instance in the application container
        $this->app->instance(PlayMuskEmpireCommand::class, $command);

        // Execute the command and assert the output
        Artisan::call('play:musk-empire');
        $output = Artisan::output();
        $this->assertStringContainsString('Musk Empire automation completed.', $output);
    }
}
