<?php

namespace App\Services;

use App\Pipes\Hamster\Authenticate;
use App\Pipes\Hamster\HandleBoosts;
use App\Pipes\Hamster\HandleStreakDaysTask;
use App\Pipes\Hamster\HandleTaps;
use App\Pipes\Hamster\HandleUpgrades;
use App\Pipes\Hamster\Sync;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HamsterService
{
    protected Pipeline $pipeline;

    protected string $baseUrl;

    protected ?string $authToken = null;

    public array $syncData = [];

    public array $upgradesForBuy = [];

    public array $purchasedUpgrades = [];

    public function __construct(Pipeline $pipeline)
    {
        $this->baseUrl = 'https://api.hamsterkombatgame.io';
        $this->pipeline = $pipeline;
    }

    public function setAuthToken(string $authToken): void
    {
        $this->authToken = $authToken;
    }

    public function setSyncData(array $syncData): void
    {
        $this->syncData = $syncData;
    }

    public function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0',
        ];

        if (! empty($this->authToken)) {
            $headers['Authorization'] = "Bearer {$this->authToken}";
        }

        return $headers;
    }

    public function getResponseData(string $url, string $propertyName): array
    {
        $response = $this->postAndLogResponse($url);

        return $response[$propertyName];
    }

    public function postAndLogResponse(string $url, ?array $data = null): array
    {
        $response = Http::withHeaders($this->getHeaders())->post("{$this->baseUrl}{$url}", $data);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("$url failed", ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception("$url failed");
    }

    public function play(): string
    {
        try {
            $this->pipeline
                ->send($this)
                ->through([
                    Authenticate::class,
                    HandleStreakDaysTask::class,
                    Sync::class,
                    HandleBoosts::class,
                    HandleTaps::class,
                    HandleUpgrades::class,
                ])
                ->then(function () {
                    Log::info('Hamster Kombat automation completed.');
                });
        } catch (\Exception $e) {
            Log::error('Hamster | Error during automation', ['exception' => $e]);

            return 'Hamster | Error during automation';
        }

        return 'Hamster Kombat automation completed.';
    }
}
