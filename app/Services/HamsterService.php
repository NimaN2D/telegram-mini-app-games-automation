<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HamsterService
{
    protected string $baseUrl;
    protected string $authToken;
    protected array $syncData = [];
    protected array $upgrades = [];
    protected array $purchasedUpgrades = [];

    public function __construct()
    {
        $this->baseUrl = 'https://api.hamsterkombatgame.io';
    }

    public function authenticate(): void
    {
        $this->authToken = cache()->remember('hamsterAuthKey', now()->addHour(), function() {
            Log::info('auth called');
            $response = Http::post("{$this->baseUrl}/auth/auth-by-telegram-webapp", [
                'initDataRaw' => config('hamster.init_data_raw'),
                'fingerprint' => json_decode(config('hamster.fingerprint'), true)
            ]);

            if ($response->successful()) {
                $authToken = $response->json()['authToken'];
                Log::info('Authentication successful', ['authToken' => $authToken]);
                return $authToken;
            } else {
                Log::error('Authentication failed', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('Authentication failed');
            }
        });
    }

    public function sync(): void
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/sync");

        if ($response->successful()) {
            $this->syncData = $response->json();
        } else {
            Log::error('Sync failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Sync failed');
        }
    }

    public function tap(int $count, int $availableTaps): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/tap", [
            'count' => $count,
            'availableTaps' => $availableTaps,
            'timestamp' => time()
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('Tap failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception('Tap failed');
    }

    public function buyUpgrade(string $upgradeId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/buy-upgrade", [
            'upgradeId' => $upgradeId,
            'timestamp' => time()
        ]);

        if ($response->successful()) {
            Log::info('Buy upgrade successful', ['data' => $response->json()]);
            return $response->json();
        }

        Log::error('Buy Upgrade failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception('Buy Upgrade failed');
    }

    public function getUpgradesForBuy(): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/upgrades-for-buy");

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['upgradesForBuy'])) {
                $this->upgrades = $data['upgradesForBuy'];
                return $this->upgrades;
            } else {
                Log::error('Expected key "upgradesForBuy" not found in response', ['response' => $data]);
                throw new \Exception('Invalid response format');
            }
        }

        Log::error('Get Upgrades for Buy failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception('Get Upgrades for Buy failed');
    }

    public function getBoostsForBuy(): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/boosts-for-buy");

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['boostsForBuy'])) {
                return $data['boostsForBuy'];
            } else {
                Log::error('Expected key "boostsForBuy" not found in response', ['response' => $data]);
                throw new \Exception('Invalid response format');
            }
        }

        Log::error('Get Boosts for Buy failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception('Get Boosts for Buy failed');
    }

    public function buyBoost(string $boostId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ])->post("{$this->baseUrl}/clicker/buy-boost", [
            'boostId' => $boostId,
            'timestamp' => time()
        ]);

        if ($response->successful()) {
            Log::info('Buy boost successful', ['data' => $response->json()]);
            return $response->json();
        }

        Log::error('Buy Boost failed', ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception('Buy Boost failed');
    }

    public function handleBoosts(array $boosts): void
    {
        foreach ($boosts as $boost) {
            if ($boost['id'] === 'BoostFullAvailableTaps' && $boost['cooldownSeconds'] === 0) {
                $this->buyBoost($boost['id']);
            }
        }
    }

    public function handleTaps(): void
    {
        $availableTaps = $this->syncData['clickerUser']['availableTaps'];
        $earnPerTap = $this->syncData['clickerUser']['earnPerTap'];
        $totalTaps = 0;
        while ($availableTaps > 0) {
            $count = min($earnPerTap, rand(8, 125));
            $tapResult = $this->tap($count, $availableTaps);
            $availableTaps -= $count;
            $totalTaps += $count;
        }
        Log::info("Total Taps Made: {$totalTaps}");
    }

    public function evaluateAndBuyUpgrades(array $upgrades): array
    {
        $balanceCoins = $this->syncData['clickerUser']['balanceCoins'];
        $this->purchasedUpgrades = [];
        $spendPercentage = config('hamster.spend_percentage', 1);
        $minBalance = config('hamster.min_balance', 0);
        $budget = $balanceCoins * $spendPercentage;
        $maxSpendable = $balanceCoins - $minBalance;
        Log::info('Budget: ' . $budget);
        Log::info('Max Spendable: ' . $maxSpendable);

        $finalBudget = min($budget, $maxSpendable);
        $threshold = 1;

        usort($upgrades, function ($a, $b) {
            if ($a['price'] == 0) return 1;
            if ($b['price'] == 0) return -1;
            $valueA = $a['profitPerHour'] / $a['price'];
            $valueB = $b['profitPerHour'] / $b['price'];
            return $valueB <=> $valueA;
        });

        $purchasedUpgrades = [];

        foreach ($upgrades as $upgrade) {
            if ($upgrade['price'] > $finalBudget) {
                continue;
            }

            if (isset($upgrade['condition'])) {
                $conditionValid = $this->validateConditionFormat($upgrade['condition']);
                if (!$conditionValid || !$this->purchaseDependency($upgrade['condition'], $finalBudget)) {
                    continue;
                }
            }

            if ($upgrade['price'] == 0) {
                continue;
            }

            $valueRatio = $upgrade['profitPerHour'] / $upgrade['price'];
            if ($valueRatio < $threshold) {
                continue;
            }

            if (in_array($upgrade['id'], $this->purchasedUpgrades)) {
                continue;
            }

            try {
                $this->buyUpgrade($upgrade['id']);
                $finalBudget -= $upgrade['price'];
                $this->purchasedUpgrades[] = $upgrade['id'];
                $purchasedUpgrades[] = $upgrade['name'];
                Log::info('Upgrade purchased', ['upgrade' => $upgrade['name']]);
            } catch (\Exception $e) {
                Log::error('Error purchasing upgrade', ['upgrade' => $upgrade['name'], 'error' => $e->getMessage()]);
            }
        }

        Log::info('Purchased Upgrades', ['upgrades' => $purchasedUpgrades]);
        return ['purchased' => $purchasedUpgrades];
    }

    private function purchaseDependency(array $condition, float &$budget): bool
    {
        $validCondition = $this->validateConditionFormat($condition);
        if (!$validCondition) {
            return false;
        }

        $upgradeId = $condition['upgradeId'];
        $requiredLevel = $condition['level'];
        $currentLevel = $this->getUpgradeLevel($upgradeId);

        if ($currentLevel >= $requiredLevel) {
            return true;
        }

        $upgrades = $this->upgrades ?? $this->getUpgradesForBuy();
        foreach ($upgrades as $upgrade) {
            if ($upgrade['id'] === $upgradeId) {
                if ($upgrade['price'] > $budget) {
                    Log::info("Skipping dependency {$upgrade['id']} due to insufficient budget.");
                    return false;
                }

                if ($upgrade['price'] == 0) {
                    Log::info("Skipping dependency {$upgrade['id']} due to zero price.");
                    return false;
                }

                if (in_array($upgrade['id'], $this->purchasedUpgrades)) {
                    return true;
                }

                try {
                    $this->buyUpgrade($upgrade['id']);
                    $budget -= $upgrade['price'];
                    $this->purchasedUpgrades[] = $upgrade['id'];
                    Log::info('Dependency upgrade purchased', ['upgrade' => $upgrade['id']]);
                } catch (\Exception $e) {
                    Log::error('Error purchasing dependency upgrade', ['upgrade' => $upgrade['id'], 'error' => $e->getMessage()]);
                    return false;
                }

                if (!$this->purchaseDependency($condition, $budget)) {
                    return false;
                }

                break;
            }
        }

        return true;
    }

    private function validateConditionFormat(array $condition): bool
    {
        if (!isset($condition['upgradeId']) || !isset($condition['level'])) {
            Log::error("Invalid condition format", ['condition' => $condition]);
            return false;
        }
        return true;
    }

    private function getUpgradeLevel(string $upgradeId): int
    {
        foreach ($this->upgrades as $upgrade) {
            if ($upgrade['id'] === $upgradeId) {
                return $upgrade['level'];
            }
        }

        Log::error("Upgrade not found", ['upgradeId' => $upgradeId]);
        return 0;
    }
}
