<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;
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
            $nextLevel = $currentLevel + 1;

            if ($this->isUpgradeValid($muskEmpireService, $upgrade, $finalBudget, $nextLevel)) {
                $upgrade['priceNextLevel'] = $this->calculatePriceNextLevel($upgrade, $nextLevel);
                $upgrade['profitNextLevel'] = $this->calculateProfitNextLevel($upgrade, $nextLevel);
                $validUpgrades[] = $upgrade;
                if ($upgrade['priceNextLevel'] <= $finalBudget) {
                    $potentialUpgrades[] = $upgrade;
                }
            }
        }

        usort($validUpgrades, function ($a, $b) {
            return ($b['profitNextLevel'] / $b['priceNextLevel']) <=> ($a['profitNextLevel'] / $a['priceNextLevel']);
        });
        $this->logBestPotentialUpgrade($validUpgrades, $potentialUpgrades);

        return $validUpgrades;
    }

    private function getCurrentUpgradeLevel(MuskEmpireService $muskEmpireService, string $upgradeKey): int
    {
        return $muskEmpireService->syncData['skills'][$upgradeKey]['level'] ?? 0;
    }

    private function isUpgradeValid(MuskEmpireService $muskEmpireService, array $upgrade, float $finalBudget, int $nextLevel): bool
    {
        $highestLevel = $upgrade['maxLevel'];

        if ($highestLevel <= $this->getCurrentUpgradeLevel($muskEmpireService, $upgrade['key'])) {
            return false;
        }

        $nextLevelRequirements = $this->getLevelRequirements($upgrade['levels'], $nextLevel);

        if ($nextLevelRequirements && ! $this->validateRequiredSkills($muskEmpireService, $nextLevelRequirements['requiredSkills'])) {
            return false;
        }

        if ($nextLevelRequirements && $nextLevelRequirements['requiredHeroLevel'] > $muskEmpireService->syncData['hero']['level']) {
            return false;
        }

        if ($nextLevelRequirements && $nextLevelRequirements['requiredFriends'] > $muskEmpireService->syncData['profile']['friends']) {
            return false;
        }

        $priceNextLevel = $this->calculatePriceNextLevel($upgrade, $nextLevel);
        if ($priceNextLevel > $finalBudget) {
            return false;
        }
        if (in_array($upgrade['key'], $muskEmpireService->purchasedUpgrades)) {
            return false;
        }

        return true;
    }

    private function getLevelRequirements(array $levels, int $level): ?array
    {
        foreach ($levels as $levelData) {
            if ($levelData['level'] === $level) {
                return $levelData;
            }
        }

        return null;
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

    private function calculatePriceNextLevel(array $upgrade, int $level): float
    {
        $basePrice = $upgrade['priceBasic'];
        $percentageIncrease = $upgrade['priceFormulaK'];

        switch ($upgrade['priceFormula']) {
            case 'fnCompound':
                $increaseFactor = $percentageIncrease / 100;

                return $this->roundUpPrice($basePrice * pow(1 + $increaseFactor, $level - 1));
            case 'fnLinear':
                return $this->roundUpPrice($basePrice + $percentageIncrease * ($level - 1));
            case 'fnQuadratic':
                return $this->roundUpPrice($basePrice + $percentageIncrease * pow($level - 1, 2));
            case 'fnCubic':
                return $this->roundUpPrice($basePrice + $percentageIncrease * pow($level - 1, 3));
            case 'fnExponential':
                return $this->roundUpPrice($basePrice * pow($percentageIncrease, $level - 1));
            case 'fnLogarithmic':
                return $this->roundUpPrice($basePrice + $percentageIncrease * log($level));
            default:
                return 0;
        }
    }

    private function roundUpPrice(float $price): float
    {
        if ($price < 100) {
            return ceil($price / 5) * 5;
        } elseif ($price < 500) {
            return ceil($price / 25) * 25;
        } elseif ($price < 1000) {
            return ceil($price / 50) * 50;
        } elseif ($price < 5000) {
            return ceil($price / 100) * 100;
        } elseif ($price < 10000) {
            return ceil($price / 200) * 200;
        } elseif ($price < 100000) {
            return ceil($price / 500) * 500;
        } elseif ($price < 500000) {
            return ceil($price / 1000) * 1000;
        } elseif ($price < 1000000) {
            return ceil($price / 5000) * 5000;
        } else {
            return ceil($price / 10000) * 10000;
        }
    }

    private function calculateProfitNextLevel(array $upgrade, int $level): float
    {
        $profitBasic = $upgrade['profitBasic'];
        $profitFormulaK = $upgrade['profitFormulaK'];

        switch ($upgrade['profitFormula']) {
            case 'fnPayback':
                $totalProfit = 0;
                for ($i = 1; $i <= $level; $i++) {
                    $profitIncrement = $profitBasic + $profitFormulaK * ($i - 1);
                    $totalProfit += $profitIncrement;
                }

                return $totalProfit;
            case 'fnCompound':
                $increaseFactor = $profitFormulaK / 100;

                return $profitBasic * pow(1 + $increaseFactor, $level - 1);
            case 'fnLinear':
                return $profitBasic + $profitFormulaK * ($level - 1);
            case 'fnQuadratic':
                return $profitBasic + $profitFormulaK * pow($level - 1, 2);
            case 'fnCubic':
                return $profitBasic + $profitFormulaK * pow($level - 1, 3);
            case 'fnExponential':
                return $profitBasic * pow($profitFormulaK, $level - 1);
            case 'fnLogarithmic':
                return $profitBasic + $profitFormulaK * log($level);
            default:
                return 0;
        }
    }

    private function purchaseUpgrades(MuskEmpireService $muskEmpireService, array $validUpgrades, float &$finalBudget): void
    {
        foreach ($validUpgrades as $upgrade) {
            if ($finalBudget < $upgrade['priceNextLevel']) {
                continue;
            }

            $this->purchaseUpgrade($muskEmpireService, $upgrade['key']);
            $finalBudget -= $upgrade['priceNextLevel'];
        }
    }

    private function purchaseUpgrade(MuskEmpireService $muskEmpireService, string $upgradeKey): void
    {
        $muskEmpireService->postAndLogResponse('/skills/improve', [
            'data' => $upgradeKey,
        ]);
        $muskEmpireService->purchasedUpgrades[] = $upgradeKey;
    }

    private function logBestPotentialUpgrade(array $validUpgrades, array $potentialUpgrades): void
    {
        if (! empty($potentialUpgrades)) {
            usort($potentialUpgrades, function ($a, $b) {
                return ($b['profitNextLevel'] / $b['priceNextLevel']) <=> ($a['profitNextLevel'] / $a['priceNextLevel']);
            });

            $bestPotentialUpgrade = $potentialUpgrades[0];
            if (! empty($validUpgrades)) {
                $bestCurrentUpgrade = $validUpgrades[0];
                $currentValue = $bestCurrentUpgrade['profitNextLevel'] / $bestCurrentUpgrade['priceNextLevel'];
                $potentialValue = $bestPotentialUpgrade['profitNextLevel'] / $bestPotentialUpgrade['priceNextLevel'];

                if ($potentialValue > $currentValue * 2) {
                    Log::info('MuskEmpire | Decided to wait for a better upgrade', ['upgrade' => $bestPotentialUpgrade]);
                }
            }
        }
    }
}
