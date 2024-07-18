<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HamsterService
{
    protected string $baseUrl;
    protected ?string $authToken;
    protected array $syncData = [];
    protected array $upgradesForBuy = [];
    protected array $purchasedUpgrades = [];

    public function __construct()
    {
        $this->baseUrl = 'https://api.hamsterkombatgame.io';
    }

    public function authenticate(): void
    {
        $this->authToken = cache()->remember('hamsterAuthKey', now()->addHour(), function() {
            $response = $this->postAndLogResponse('/auth/auth-by-telegram-webapp', [
                'initDataRaw' => config('hamster.init_data_raw'),
                'fingerprint' => json_decode(config('hamster.fingerprint'), true)
            ]);

            return $response['authToken'];
        });
    }

    public function sync(): void
    {
        $response = Http::withHeaders($this->getHeaders())->post("{$this->baseUrl}/clicker/sync");

        if ($response->successful()) {
            $this->syncData = $response->json();
        } else {
            Log::error('Sync failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \Exception('Sync failed');
        }
    }

    public function tap(int $count, int $availableTaps): array
    {
        $response = Http::withHeaders($this->getHeaders())->post("{$this->baseUrl}/clicker/tap", [
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

    public function buyUpgrade(string $upgradeId, ?float $price = null, ?float &$budget = null): void
    {
        if ($budget !== null && $price !== null && $budget < $price) {
            return;
        }

        $this->postAndLogResponse("/clicker/buy-upgrade", [
            'upgradeId' => $upgradeId,
            'timestamp' => time()
        ]);
        $this->purchasedUpgrades[] = $upgradeId;
        if ($price && $budget) {
            $budget -= $price;
        }
    }

    public function getUpgradesForBuy(): array
    {
        return $this->getResponseData("/clicker/upgrades-for-buy", 'upgradesForBuy');
    }

    public function getBoostsForBuy(): array
    {
        return $this->getResponseData("/clicker/boosts-for-buy", 'boostsForBuy');
    }

    public function buyBoost(string $boostId): array
    {
        return $this->postAndLogResponse("/clicker/buy-boost", [
            'boostId' => $boostId,
            'timestamp' => time()
        ]);
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
        $totalTaps = 0;

        while ($availableTaps > 0) {
            $count = min($availableTaps, rand(30, 100));
            $tapResult = $this->tap($count, $availableTaps);
            $availableTaps -= $count;
            $totalTaps += $count;
        }

        Log::info("Total Taps Made: {$totalTaps}");
    }

    public function evaluateAndBuyUpgrades(array $upgrades): void
    {
        $balanceCoins = $this->syncData['clickerUser']['balanceCoins'];
        $this->purchasedUpgrades = [];
        $spendPercentage = config('hamster.spend_percentage', 1);
        $minBalance = config('hamster.min_balance', 0);
        $budget = $balanceCoins * $spendPercentage;
        $maxSpendable = $balanceCoins - $minBalance;

        $finalBudget = min($budget, $maxSpendable);
        $upgrades = $this->filterAndRankUpgrades($upgrades, $finalBudget);

        foreach ($upgrades as $upgrade) {
            $this->buyUpgrade($upgrade['id'], $upgrade['price'], $finalBudget);
        }

        Log::info('Purchased Upgrades', ['upgrades' => $this->purchasedUpgrades]);
    }

    public function handleStreakDaysTask(): void
    {
        $tasks = $this->getDailyTasks();

        foreach ($tasks as $task) {
            if ($task['id'] === 'streak_days' && !$task['isCompleted']) {
                $this->postAndLogResponse("/clicker/check-task", [
                    'taskId' => $task['id']
                ]);
                Log::info("Completed streak_days task", ['taskId' => $task['id']]);
            }
        }
    }

    private function getDailyTasks(): array
    {
        return $this->getResponseData("/clicker/list-tasks", 'tasks');
    }

    private function purchaseDependency(array $condition, float &$budget): bool
    {
        $validCondition = $this->validateConditionFormat($condition);

        if (!$validCondition) {
            return false;
        }

        $upgrades = $this->upgradesForBuy ?? $this->getUpgradesForBuy();

        foreach ($upgrades as $upgrade) {
            if ($upgrade['id'] === $condition['upgradeId']) {
                if (isset($upgrade['condition'])) {
                    $conditionValid = $this->validateConditionFormat($upgrade['condition']);

                    if (!$conditionValid || !$this->purchaseDependency($upgrade['condition'], $budget)) {
                        return false;
                    }
                }

                if ($upgrade['price'] > $budget) {
                    Log::info("Skipping dependency {$upgrade['id']} due to insufficient budget.");
                    return false;
                }

                if (in_array($upgrade['id'], $this->purchasedUpgrades)) {
                    return true;
                }

                $this->buyUpgrade($upgrade['id'], $upgrade['price'], $budget);
                Log::info('Dependency upgrade purchased', ['upgrade' => $upgrade['id']]);
            }
        }

        return true;
    }

    private function validateConditionFormat(array $condition): bool
    {
        if (!isset($condition['upgradeId']) || !isset($condition['level']) || $condition['_type'] != 'ByUpgrade') {
            return false;
        }

        return true;
    }

    private function filterAndRankUpgrades(array $upgrades, float &$finalBudget): array
    {
        $validUpgrades = [];
        $potentialUpgrades = [];

        foreach ($upgrades as $upgrade) {
            if (isset($upgrade['condition'])) {
                $conditionValid = $this->validateConditionFormat($upgrade['condition']);

                if (!$conditionValid || !$this->purchaseDependency($upgrade['condition'], $finalBudget)) {
                    continue;
                }
            }

            if (isset($upgrade['cooldownSeconds']) && $upgrade['cooldownSeconds'] > 0) {
                continue;
            }

            if ($upgrade['isAvailable'] === false || $upgrade['isExpired'] === true) {
                continue;
            }

            if ($upgrade['price'] == 0 && !isset($upgrade['condition'])) {
                $this->buyUpgrade($upgrade['id']);
                continue;
            }

            if (in_array($upgrade['id'], $this->purchasedUpgrades)) {
                continue;
            }

            $validUpgrades[] = $upgrade;

            // Add to potential upgrades list if worth waiting for
            if ($upgrade['price'] <= $finalBudget) {
                $potentialUpgrades[] = $upgrade;
            }
        }

        // Sort valid upgrades by value (profitPerHour / price)
        usort($validUpgrades, function ($a, $b) {
            $valueA = $a['profitPerHour'] / $a['price'];
            $valueB = $b['profitPerHour'] / $b['price'];
            return $valueB <=> $valueA;
        });

        // Check if there is a potential upgrade worth waiting for
        $bestPotentialUpgrade = null;
        if (!empty($potentialUpgrades)) {
            usort($potentialUpgrades, function ($a, $b) {
                $valueA = $a['profitPerHour'] / $a['price'];
                $valueB = $b['profitPerHour'] / $b['price'];
                return $valueB <=> $valueA;
            });

            $bestPotentialUpgrade = $potentialUpgrades[0];
        }

        // If the best potential upgrade has significantly higher value, decide to wait
        if ($bestPotentialUpgrade && !empty($validUpgrades)) {
            $bestCurrentUpgrade = $validUpgrades[0];
            $currentValue = $bestCurrentUpgrade['profitPerHour'] / $bestCurrentUpgrade['price'];
            $potentialValue = $bestPotentialUpgrade['profitPerHour'] / $bestPotentialUpgrade['price'];

            if ($potentialValue > $currentValue * 2) { // 2x more value
                Log::info('Decided to wait for a better upgrade', ['upgrade' => $bestPotentialUpgrade]);
                return [];
            }
        }

        return $validUpgrades;
    }

    private function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; Mobile; rv:91.0) Gecko/91.0 Firefox/91.0'
        ];

        if (!empty($this->authToken)) {
            $headers['Authorization'] = "Bearer {$this->authToken}";
        }

        return $headers;
    }

    private function postAndLogResponse(string $url, ?array $data = null): array
    {
        $response = Http::withHeaders($this->getHeaders())->post("{$this->baseUrl}{$url}", $data);

        if ($response->successful()) {
            Log::info('Operation successful', ['data' => $response->json()]);
            return $response->json();
        }

        Log::error("$url failed", ['status' => $response->status(), 'body' => $response->body()]);
        throw new \Exception("$url failed");
    }

    private function getResponseData(string $url, string $propertyName): array
    {
        $response = $this->postAndLogResponse($url);
        return $response[$propertyName];
    }
}

