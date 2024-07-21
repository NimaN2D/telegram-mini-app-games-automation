<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;
use Illuminate\Support\Facades\Log;

class ClaimQuest
{
    public function handle(MuskEmpireService $empireService, Closure $next)
    {
        $this->initializeQuests($empireService);

        $quests = $empireService->syncData['quests'] ?? [];
        foreach ($quests as $quest) {
            if (! $quest['isRewarded'] && $this->canClaimQuest($empireService, $quest['key'])) {
                $dbQuest = $this->getDbQuest($empireService, $quest['key']);
                if ($dbQuest && $this->shouldPurchaseImprovement($dbQuest)) {
                    $this->purchaseImprovement($empireService, $dbQuest);
                } elseif (! $this->shouldPurchaseImprovement($dbQuest)) {
                    $this->claimQuest($empireService, $quest['key']);
                }
            }
        }

        return $next($empireService);
    }

    private function initializeQuests(MuskEmpireService $empireService): void
    {
        $empireService->syncData['dbQuests'] = $empireService->getResponseData('/dbs', 'dbQuests');
    }

    private function canClaimQuest(MuskEmpireService $empireService, string $questKey): bool
    {
        $quests = $empireService->syncData['dbQuests'] ?? [];
        foreach ($quests as $quest) {
            if ($quest['key'] === $questKey) {
                if ($quest['requiredLevel'] > $empireService->syncData['hero']['level']) {
                    return false;
                }

                if ($quest['needCheck'] && $quest['checkType'] !== 'improve') {
                    return $this->validateAction($quest['checkType'], $quest['checkData']);
                }

                return true;
            }
        }

        return false;
    }

    private function validateAction(string $checkType, string $checkData): bool
    {
        if ($checkType === 'telegramChannel') {
            return true;
        }

        if ($checkType === 'fakeCheck') {
            return true;
        }

        return false;
    }

    private function shouldPurchaseImprovement(array $dbQuest): bool
    {
        return $dbQuest['checkType'] === 'improve';
    }

    private function purchaseImprovement(MuskEmpireService $empireService, array $dbQuest): void
    {
        try {
            $empireService->postAndLogResponse('/skills/improve', [
                'data' => [$dbQuest['checkData'], null],
            ]);
        } catch (\Exception $e) {
            Log::error('MuskEmpire | Failed to purchase improvement', ['quest' => $dbQuest['key'], 'error' => $e->getMessage()]);
        }
    }

    private function claimQuest(MuskEmpireService $empireService, string $questKey): void
    {
        $payload = ['data' => [$questKey, null]];

        try {
            $response = $empireService->postAndLogResponse('/quests/claim', $payload);
        } catch (\Exception $e) {
            Log::error('MuskEmpire | Failed to claim quest', ['quest' => $questKey, 'error' => $e->getMessage()]);
        }
    }

    private function getDbQuest(MuskEmpireService $empireService, string $questKey): ?array
    {
        $dbQuests = $empireService->syncData['dbQuests'] ?? [];
        foreach ($dbQuests as $dbQuest) {
            if ($dbQuest['key'] === $questKey) {
                return $dbQuest;
            }
        }

        return null;
    }
}
