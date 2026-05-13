<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Sync campaign stats from Redis to MongoDB every minute
        $schedule->call(function () {
            $runningCampaigns = \App\Models\Campaign::where('status', 'running')->get();
            $campaignService = app(\App\Services\CampaignService::class);

            foreach ($runningCampaigns as $campaign) {
                $campaignService->syncStatsToDb($campaign);
            }
        })->everyMinute();

        // Check template statuses every 5 minutes
        $schedule->call(function () {
            $pendingTemplates = \App\Models\Template::where('status', 'pending')->get();
            $metaService = app(\App\Services\MetaWhatsAppService::class);

            foreach ($pendingTemplates as $template) {
                $account = \App\Models\WhatsAppAccount::find($template->account_id);
                if (!$account || !$template->meta_template_id) continue;

                $result = $metaService->checkTemplateStatus($account, $template->meta_template_id);

                if ($result['status'] === 'approved') {
                    $template->update(['status' => 'approved', 'approved_at' => now()]);
                } elseif ($result['status'] === 'rejected') {
                    $template->update([
                        'status' => 'rejected',
                        'rejection_reason' => $result['rejection_reason'] ?? 'Unknown',
                    ]);
                }
            }
        })->everyFiveMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
