<?php

namespace Tests\Unit;

use App\Services\MuskEmpireService;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class MuskEmpireServiceTest extends TestCase
{
    protected $pipeline;

    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = Mockery::mock(Pipeline::class);
        $this->service = new MuskEmpireService($this->pipeline);
    }

    public function testSetApiKey()
    {
        $this->service->setApiKey('test_key');

        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('apiKey');
        $property->setAccessible(true);

        $this->assertEquals('test_key', $property->getValue($this->service));
    }

    public function testSetSyncData()
    {
        $syncData = ['key' => 'value'];
        $this->service->setSyncData($syncData);

        $this->assertEquals($syncData, $this->service->syncData);
    }

    public function testGetHeadersWithoutApiKey()
    {
        $headers = $this->service->getHeaders();

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayNotHasKey('Api-Key', $headers);
    }

    public function testGetHeadersWithApiKey()
    {
        $this->service->setApiKey('test_key');
        $headers = $this->service->getHeaders();

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Api-Key', $headers);
        $this->assertEquals('test_key', $headers['Api-Key']);
    }

    public function testGetResponseData()
    {
        Http::fake([
            'https://api.muskempire.io/test' => Http::response(['data' => ['property' => ['key' => 'value']]], 200),
        ]);

        $result = $this->service->getResponseData('/test', 'property');

        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testPostAndLogResponseSuccess()
    {
        Http::fake([
            'https://api.muskempire.io/test' => Http::response(['key' => 'value'], 200),
        ]);

        $result = $this->service->postAndLogResponse('/test');

        $this->assertEquals(['key' => 'value'], $result);
    }

    public function testPostAndLogResponseFailure()
    {
        Http::fake([
            'https://api.muskempire.io/test' => Http::response(null, 500),
        ]);

        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('/test failed');

        $this->service->postAndLogResponse('/test');
    }

    public function testPlay()
    {
        $this->mockPipeline();

        Log::shouldReceive('info')->once()->with('Musk Empire automation completed.');

        $result = $this->service->play();

        $this->assertEquals('Musk Empire automation completed.', $result);
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
