<?php

namespace App\Pipes\MuskEmpire;

use Closure;
use App\Services\MuskEmpireService;
use Illuminate\Support\Facades\Log;

class HandleUpgrades
{
    public function handle(MuskEmpireService $muskEmpireService, Closure $next)
    {
        $this->initializeUpgrades($muskEmpireService);
        $finalBudget = $this->calculateFinalBudget($muskEmpireService);

        $validUpgrades = $this->filterAndRankUpgrades($muskEmpireService, $finalBudget);

        $this->purchaseUpgrades($muskEmpireService, $validUpgrades, $finalBudget);

        Log::info('MuskEmpire | Purchased Upgrades', ['upgrades' => $muskEmpireService->purchasedUpgrades]);

        return $next($muskEmpireService);
    }

    private function initializeUpgrades(MuskEmpireService $muskEmpireService): void
    {
        $muskEmpireService->upgradesForBuy = $muskEmpireService->getResponseData('/dbs', 'dbSkills');
        $muskEmpireService->purchasedUpgrades = [];
    }

    private function calculateFinalBudget(MuskEmpireService $muskEmpireService): float
    {
        $balanceMoney = $muskEmpireService->syncData['hero']['money'];
        $spendPercentage = config('muskempire.spend_percentage', 1);
        $minBalance = config('muskempire.min_balance', 0);
        $budget = $balanceMoney * $spendPercentage;
        $maxSpendable = $balanceMoney - $minBalance;

        return min($budget, $maxSpendable);
    }

    private function filterAndRankUpgrades(MuskEmpireService $muskEmpireService, float $finalBudget): array
    {
        $upgrades = $muskEmpireService->upgradesForBuy;
        $validUpgrades = [];
        $potentialUpgrades = [];

        foreach ($upgrades as $upgrade) {
            $currentLevel = $this->getCurrentUpgradeLevel($muskEmpireService, $upgrade['key']);
            $nextLevel = $this->getNextUpgradeLevel($upgrade['levels'], $currentLevel);

            if ($this->isUpgradeValid($muskEmpireService, $upgrade, $finalBudget, $nextLevel)) {
                $validUpgrades[] = $upgrade;
                if ($upgrade['priceBasic'] <= $finalBudget) {
                    $potentialUpgrades[] = $upgrade;
                }
            }
        }

        usort($validUpgrades, function ($a, $b) {
            return ($b['profitBasic'] / $b['priceBasic']) <=> ($a['profitBasic'] / $a['priceBasic']);
        });

        $this->logBestPotentialUpgrade($validUpgrades, $potentialUpgrades);

        return $validUpgrades;
    }

    private function getCurrentUpgradeLevel(MuskEmpireService $muskEmpireService, string $upgradeKey): int
    {
        return $muskEmpireService->syncData['skills'][$upgradeKey]['level'] ?? 0;
    }

    private function getNextUpgradeLevel(array $levels, int $currentLevel): array
    {
        foreach ($levels as $level) {
            if ($level['level'] > $currentLevel) {
                return $level;
            }
        }

        return end($levels);
    }

    private function isUpgradeValid(MuskEmpireService $muskEmpireService, array $upgrade, float $finalBudget, array $nextLevel): bool
    {
        $highestLevel = end($upgrade['levels'])['level'];
        reset($upgrade['levels']);

        if ($highestLevel <= $this->getCurrentUpgradeLevel($muskEmpireService, $upgrade['key'])) {
            return false;
        }
        if (isset($nextLevel['requiredSkills']) && !$this->validateRequiredSkills($muskEmpireService, $nextLevel['requiredSkills'])) {
            return false;
        }

        if ($nextLevel['requiredHeroLevel'] > $muskEmpireService->syncData['hero']['level']) {
            return false;
        }

        if ($nextLevel['requiredFriends'] > $muskEmpireService->syncData['profile']['friends']) {
            return false;
        }

        if ($upgrade['priceBasic'] > $finalBudget) {
            return false;
        }
        if (in_array($upgrade['key'], $muskEmpireService->purchasedUpgrades)) {
            return false;
        }

        return true;
    }

    private function validateRequiredSkills(MuskEmpireService $muskEmpireService, array $requiredSkills): bool
    {
        foreach ($requiredSkills as $skill => $level) {
            if (($muskEmpireService->syncData['skills'][$skill]['level'] ?? 0) < $level) {
                return false;
            }
        }

        return true;
    }

    private function purchaseUpgrades(MuskEmpireService $muskEmpireService, array $validUpgrades, float &$finalBudget): void
    {
        foreach ($validUpgrades as $upgrade) {
            if ($finalBudget < $upgrade['priceBasic']) {
                continue;
            }

            $this->purchaseUpgrade($muskEmpireService, $upgrade['key']);
            $finalBudget -= $upgrade['priceBasic'];
        }
    }

    private function purchaseUpgrade(MuskEmpireService $muskEmpireService, string $upgradeKey): void
    {
        $muskEmpireService->postAndLogResponse('/skills/improve', [
            'data' => $upgradeKey
        ]);
        $muskEmpireService->purchasedUpgrades[] = $upgradeKey;
    }

    private function logBestPotentialUpgrade(array $validUpgrades, array $potentialUpgrades): void
    {
        if (!empty($potentialUpgrades)) {
            usort($potentialUpgrades, function ($a, $b) {
                return ($b['profitBasic'] / $b['priceBasic']) <=> ($a['profitBasic'] / $a['priceBasic']);
            });

            $bestPotentialUpgrade = $potentialUpgrades[0];
            if (!empty($validUpgrades)) {
                $bestCurrentUpgrade = $validUpgrades[0];
                $currentValue = $bestCurrentUpgrade['profitBasic'] / $bestCurrentUpgrade['priceBasic'];
                $potentialValue = $bestPotentialUpgrade['profitBasic'] / $bestPotentialUpgrade['priceBasic'];

                if ($potentialValue > $currentValue * 2) {
                    Log::info('MuskEmpire | Decided to wait for a better upgrade', ['upgrade' => $bestPotentialUpgrade]);
                }
            }
        }
    }
}
