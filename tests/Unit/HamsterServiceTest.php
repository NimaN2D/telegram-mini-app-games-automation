<?php

namespace Tests\Unit;

use App\Services\HamsterService;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class HamsterServiceTest extends TestCase
{
    protected $pipeline;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = Mockery::mock(Pipeline::class);
        $this->service = new HamsterService($this->pipeline);
    }

    public function testSetAuthToken()
    {
        $this->service->setAuthToken('test_token');

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('authToken');
        $property->setAccessible(true);

        $this->assertEquals('test_token', $property->getValue($this->service));
    }

    public function testSetSyncData()
    {
        $syncData = ['key' => 'value'];
        $this->service->setSyncData($syncData);

        $this->assertEquals($syncData, $this->service->syncData);
    }

    public function testGetHeadersWithoutAuthToken()
    {
        $headers = $this->service->getHeaders();

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    public function testGetHeadersWithAuthToken()
    {
        $this->service->setAuthToken('test_token');
        $headers = $this->service->getHeaders();

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test_token', $headers['Authorization']);
    }

    public function testGetResponseData()
    {
        Http::fake([
            'https://api.hamsterkombatgame.io/test' => Http::response(['property' => ['key' => 'value']], 200),
        ]);

        $result = $this->service->getResponseData('/test', 'property');

        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testPostAndLogResponseSuccess()
    {
        Http::fake([
            'https://api.hamsterkombatgame.io/test' => Http::response(['key' => 'value'], 200),
        ]);

        $result = $this->service->postAndLogResponse('/test');

        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testPostAndLogResponseFailure()
    {
        Http::fake([
            'https://api.hamsterkombatgame.io/test' => Http::response(null, 500),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('/test failed');

        $this->service->postAndLogResponse('/test');
    }

    public function testPlay()
    {
        $this->mockPipeline();

        Log::shouldReceive('info')->once()->with('Hamster Kombat automation completed.');

        $result = $this->service->play();

        $this->assertEquals('Hamster Kombat automation completed.', $result);
    }

    protected function mockPipeline()
    {
        $this->pipeline->shouldReceive('send')->andReturnSelf();
        $this->pipeline->shouldReceive('through')->andReturnSelf();
        $this->pipeline->shouldReceive('then')->andReturnUsing(function ($callback) {
            $callback();
        });
    }
}
