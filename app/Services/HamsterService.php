<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HamsterService
{
    protected string $baseUrl;
    protected ?string $authToken = null;
    public array $syncData = [];
    public array $upgradesForBuy = [];
    public array $purchasedUpgrades = [];

    public function __construct()
    {
        $this->baseUrl = 'https://api.hamsterkombatgame.io';
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
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ];

        if (!empty($this->authToken)) {
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
            Log::info('Operation successful', ['data' => $response->json()]);
            return $response->json();
        }

        Log::error("$url failed", ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception("$url failed");
    }
}
