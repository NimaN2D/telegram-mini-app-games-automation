<?php

namespace App\Pipes\Hamster;

use Closure;
use App\Services\HamsterService;
use Illuminate\Support\Facades\Log;

class HandleUpgrades
{
    public function handle(HamsterService $hamsterService, Closure $next)
    {
        $this->initializeUpgrades($hamsterService);
        $finalBudget = $this->calculateFinalBudget($hamsterService);

        $validUpgrades = $this->filterAndRankUpgrades($hamsterService, $finalBudget);

        $this->purchaseUpgrades($hamsterService, $validUpgrades, $finalBudget);

        Log::info('Hamster | Purchased Upgrades', ['upgrades' => $hamsterService->purchasedUpgrades]);

        return $next($hamsterService);
    }

    private function initializeUpgrades(HamsterService $hamsterService): void
    {
        $hamsterService->upgradesForBuy = $hamsterService->getResponseData('/clicker/upgrades-for-buy', 'upgradesForBuy');
        $hamsterService->purchasedUpgrades = [];
    }

    private function calculateFinalBudget(HamsterService $hamsterService): float
    {
        $balanceCoins = $hamsterService->syncData['clickerUser']['balanceCoins'];
        $spendPercentage = config('hamster.spend_percentage', 1);
        $minBalance = config('hamster.min_balance', 0);
        $budget = $balanceCoins * $spendPercentage;
        $maxSpendable = $balanceCoins - $minBalance;

        return min($budget, $maxSpendable);
    }

    private function filterAndRankUpgrades(HamsterService $hamsterService, float $finalBudget): array
    {
        $upgrades = $hamsterService->upgradesForBuy;
        $validUpgrades = [];
        $potentialUpgrades = [];

        foreach ($upgrades as $upgrade) {
            if ($this->isUpgradeValid($hamsterService, $upgrade, $finalBudget)) {
                $validUpgrades[] = $upgrade;
                if ($upgrade['price'] <= $finalBudget) {
                    $potentialUpgrades[] = $upgrade;
                }
            }
        }

        usort($validUpgrades, function ($a, $b) {
            return ($b['profitPerHour'] / $b['price']) <=> ($a['profitPerHour'] / $a['price']);
        });

        $this->logBestPotentialUpgrade($validUpgrades, $potentialUpgrades);

        return $validUpgrades;
    }

    private function isUpgradeValid(HamsterService $hamsterService, array $upgrade, float $finalBudget): bool
    {
        if (isset($upgrade['cooldownSeconds']) && $upgrade['cooldownSeconds'] > 0) {
            return false;
        }

        if (!$upgrade['isAvailable'] || $upgrade['isExpired']) {
            return false;
        }

        if ($upgrade['price'] == 0 && !isset($upgrade['condition'])) {
            $this->purchaseUpgrade($hamsterService, $upgrade['id']);
            return false;
        }

        if (isset($upgrade['condition']) && !$this->validateCondition($hamsterService, $upgrade['condition'], $finalBudget)) {
            return false;
        }

        if (in_array($upgrade['id'], $hamsterService->purchasedUpgrades)) {
            return false;
        }

        return true;
    }

    private function purchaseUpgrades(HamsterService $hamsterService, array $validUpgrades, float &$finalBudget): void
    {
        foreach ($validUpgrades as $upgrade) {
            if ($finalBudget < $upgrade['price']) {
                continue;
            }

            $this->purchaseUpgrade($hamsterService, $upgrade['id']);
            $finalBudget -= $upgrade['price'];
        }
    }

    private function purchaseUpgrade(HamsterService $hamsterService, string $upgradeId): void
    {
        $hamsterService->postAndLogResponse('/clicker/buy-upgrade', [
            'upgradeId' => $upgradeId,
            'timestamp' => time()
        ]);
        $hamsterService->purchasedUpgrades[] = $upgradeId;
    }

    private function logBestPotentialUpgrade(array $validUpgrades, array $potentialUpgrades): void
    {
        if (!empty($potentialUpgrades)) {
            usort($potentialUpgrades, function ($a, $b) {
                return ($b['profitPerHour'] / $b['price']) <=> ($a['profitPerHour'] / $a['price']);
            });

            $bestPotentialUpgrade = $potentialUpgrades[0];
            if (!empty($validUpgrades)) {
                $bestCurrentUpgrade = $validUpgrades[0];
                $currentValue = $bestCurrentUpgrade['profitPerHour'] / $bestCurrentUpgrade['price'];
                $potentialValue = $bestPotentialUpgrade['profitPerHour'] / $bestPotentialUpgrade['price'];

                if ($potentialValue > $currentValue * 2) {
                    Log::info('Hamster | Decided to wait for a better upgrade', ['upgrade' => $bestPotentialUpgrade]);
                }
            }
        }
    }

    private function validateCondition(HamsterService $hamsterService, array $condition, float &$budget): bool
    {
        if (!isset($condition['upgradeId'], $condition['level'], $condition['_type']) || $condition['_type'] !== 'ByUpgrade') {
            return false;
        }

        $upgrades = $hamsterService->upgradesForBuy ?? $hamsterService->getResponseData('/clicker/upgrades-for-buy', 'upgradesForBuy');

        foreach ($upgrades as $upgrade) {
            if ($this->isConditionMet($hamsterService, $upgrade, $condition, $budget)) {
                return true;
            }
        }

        return false;
    }

    private function isConditionMet(HamsterService $hamsterService, array $upgrade, array $condition, float &$budget): bool
    {
        if ($upgrade['id'] !== $condition['upgradeId']) {
            return false;
        }

        if (isset($upgrade['cooldownSeconds']) && $upgrade['cooldownSeconds'] > 0) {
            return false;
        }

        if (!$upgrade['isAvailable'] || $upgrade['isExpired'] || $upgrade['price'] > $budget) {
            return false;
        }

        if (in_array($upgrade['id'], $hamsterService->purchasedUpgrades)) {
            return true;
        }

        if ($upgrade['level'] > $condition['level']) {
            return true;
        }

        if (isset($upgrade['condition']) && !$this->validateCondition($hamsterService, $upgrade['condition'], $budget)) {
            return false;
        }

        $this->purchaseUpgrade($hamsterService, $upgrade['id']);
        $budget -= $upgrade['price'];

        return true;
    }
}
