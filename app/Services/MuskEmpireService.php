<?php

namespace App\Services;

use App\Pipes\MuskEmpire\Authenticate;
use App\Pipes\MuskEmpire\ClaimQuest;
use App\Pipes\MuskEmpire\HandleTaps;
use App\Pipes\MuskEmpire\HandleUpgrades;
use App\Pipes\MuskEmpire\Sync;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MuskEmpireService
{
    protected Pipeline $pipeline;

    protected string $baseUrl;

    protected ?string $apiKey = null;

    public array $syncData = [];

    public array $upgradesForBuy = [];

    public array $purchasedUpgrades = [];

    public function __construct(Pipeline $pipeline)
    {
        $this->baseUrl = 'https://api.muskempire.io';
        $this->pipeline = $pipeline;
    }

    public function setSyncData(array $syncData): void
    {
        $this->syncData = $syncData;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Api-Key'] = $this->apiKey;
        }

        return $headers;
    }

    public function getResponseData(string $url, string $propertyName): array
    {
        $response = $this->postAndLogResponse($url);

        return $response['data'][$propertyName];
    }

    public function postAndLogResponse(string $url, ?array $data = null): array
    {
        $response = Http::withHeaders($this->getHeaders())->post("{$this->baseUrl}{$url}", $data);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("MuskEmpire | $url failed", ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception("$url failed");
    }

    public function play(): string
    {
        try {
            $this->pipeline
                ->send($this)
                ->through([
                    Authenticate::class,
                    Sync::class,
                    ClaimQuest::class,
                    HandleTaps::class,
                    Sync::class,
                    HandleUpgrades::class,
                ])
                ->then(function () {
                    Log::info('Musk Empire automation completed.');
                });
        } catch (\Exception $e) {
            Log::error('MuskEmpire | Error during automation', ['exception' => $e]);

            return 'MuskEmpire | Error during automation';
        }

        return 'Musk Empire automation completed.';
    }
}
