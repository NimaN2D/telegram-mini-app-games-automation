<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HamsterService;
use Illuminate\Support\Facades\Log;

class PlayHamsterCommand extends Command
{
    protected $signature = 'play:hamster';
    protected $description = 'Automate the Hamster Kombat game process';
    protected HamsterService $hamsterService;

    public function __construct(HamsterService $hamsterService)
    {
        parent::__construct();
        $this->hamsterService = $hamsterService;
    }

    public function handle(): void
    {
        $this->info('Starting Hamster Kombat automation...');
        Log::info('Starting Hamster Kombat automation...');

        try {
            $this->hamsterService->authenticate();

            $syncData = $this->hamsterService->sync();
            $availableTaps = $syncData['clickerUser']['availableTaps'];
            $earnPerTap = $syncData['clickerUser']['earnPerTap'];
            $totalTaps = 0;

            while ($availableTaps > 0) {
                $count = min($earnPerTap, rand(8, 125));
                $tapResult = $this->hamsterService->tap($count, $availableTaps);
                $availableTaps -= $count;
                $totalTaps += $count;
            }

            $this->info("Total Taps Made: {$totalTaps}");
            Log::info("Total Taps Made: {$totalTaps}");

            $boosts = $this->hamsterService->getBoostsForBuy();
            foreach ($boosts as $boost) {
                if ($boost['id'] === 'BoostFullAvailableTaps' && $boost['cooldownSeconds'] === 0) {
                    $boostResult = $this->hamsterService->buyBoost($boost['id']);
                    Log::info('Boost purchased', ['boost' => $boost['id']]);
                    $this->info('Purchased Boost: ' . $boost['id']);
                    $syncData = $boostResult; // Use the response from buyBoost method
                    $availableTaps = $syncData['clickerUser']['availableTaps'];

                    while ($availableTaps > 0) {
                        $count = min($earnPerTap, rand(8, 125));
                        $tapResult = $this->hamsterService->tap($count, $availableTaps);
                        $availableTaps -= $count;
                        $totalTaps += $count;
                    }

                    $this->info("Total Taps Made After Boost: {$totalTaps}");
                    Log::info("Total Taps Made After Boost: {$totalTaps}");
                }
            }

            $upgrades = $this->hamsterService->getUpgradesForBuy();
            $balanceCoins = $syncData['clickerUser']['balanceCoins'];

            $result = $this->hamsterService->evaluateAndBuyUpgrades($upgrades, $balanceCoins);

            if (empty($result['purchased'])) {
                $this->info('No upgrades purchased due to insufficient balance or unmet conditions.');
                Log::info('No upgrades purchased due to insufficient balance or unmet conditions.');
            } else {
                $this->info('Purchased Upgrades: ' . implode(', ', $result['purchased']));
                Log::info('Purchased Upgrades: ' . implode(', ', $result['purchased']));
            }

            $this->info('Hamster Kombat automation completed.');
            Log::info('Hamster Kombat automation completed.');
        } catch (\Exception $e) {
            $this->error('Error during automation: ' . $e->getMessage());
            $this->error('Error during automation: ' . $e->getMessage());
            Log::error('Error during automation', ['exception' => $e]);
        }
    }
}
