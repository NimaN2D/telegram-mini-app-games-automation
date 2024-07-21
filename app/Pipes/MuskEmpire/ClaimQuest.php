<?php

namespace App\Pipes\MuskEmpire;

use App\Services\MuskEmpireService;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClaimQuest
{
    public function handle(MuskEmpireService $empireService, Closure $next)
    {
        $this->initializeQuests($empireService);

        $dbQuests = $empireService->syncData['dbQuests'] ?? [];
        $claimedQuests = $empireService->syncData['quests'] ?? [];
        foreach ($dbQuests as $dbQuest) {
            $isClaimed = Arr::first($claimedQuests, function ($value) use ($dbQuest) {
                return $value['key'] === $dbQuest['key'] && $value['isRewarded'] === true;
            });
            if ($isClaimed) {
                continue;
            }
            if ($this->canClaimQuest($empireService, $dbQuest)) {
                if ($this->shouldPurchaseImprovement($dbQuest)) {
                    $this->purchaseImprovement($empireService, $dbQuest);
                } else {
                    $this->claimQuest($empireService, $dbQuest);
                }
            }
        }

        return $next($empireService);
    }

    private function initializeQuests(MuskEmpireService $empireService): void
    {
        $empireService->syncData['dbQuests'] = $empireService->getResponseData('/dbs', 'dbQuests');
    }

    private function canClaimQuest(MuskEmpireService $empireService, array $dbQuest): bool
    {
        if (Str::contains($dbQuest['title'], 'Invest')) {
            return false;
        }

        if (in_array($dbQuest['actionText'], ['Subscribe', 'Invite friends', 'Go to Negotiations', '', 'Sign Up', 'Complete KYC', 'Make a Deposit', 'Follow'])) {
            return false;
        }

        if ($dbQuest['isArchived'] === true) {
            return false;
        }

        if ($dbQuest['requiredLevel'] > $empireService->syncData['hero']['level']) {
            return false;
        }

        if ($dbQuest['needCheck']) {
            if ($dbQuest['checkType'] === 'checkCode') {
                return true;
            }

            if ($dbQuest['checkType'] !== 'improve') {
                return $this->validateAction($dbQuest['checkType'], $dbQuest['checkData']);
            }
        }

        return true;
    }

    private function validateAction(string $checkType, string $checkData): bool
    {
        if ($checkType === 'telegramChannel' || $checkType === 'fakeCheck') {
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

    private function claimQuest(MuskEmpireService $empireService, array $dbQuest): void
    {
        $payload = ['data' => [$dbQuest['key'], $dbQuest['checkData'] ?? null]];

        try {
            $empireService->postAndLogResponse('/quests/claim', $payload);
        } catch (\Exception $e) {
            Log::error('MuskEmpire | Failed to claim quest', ['quest' => $dbQuest['key'], 'error' => $e->getMessage()]);
        }
    }
}
