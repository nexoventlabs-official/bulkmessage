<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\MessageLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class AuditCampaign extends Command
{
    protected $signature = 'campaign:audit {id?}';
    protected $description = 'Audit campaign performance - show send rates, delivery stats, and errors';

    public function handle(): int
    {
        $campaignId = $this->argument('id');

        if ($campaignId) {
            $campaigns = collect([Campaign::findOrFail($campaignId)]);
        } else {
            $campaigns = Campaign::whereIn('status', ['running', 'completed', 'paused'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }

        foreach ($campaigns as $campaign) {
            $this->info("\n========== Campaign: {$campaign->name} ==========");
            $this->info("Status: {$campaign->status}");
            $this->info("Assemblies: " . count($campaign->assemblies ?? []));
            $this->info("Total target (with mobile): " . number_format($campaign->total_with_mobile));

            // Get Redis live stats
            $key = "campaign:{$campaign->_id}";
            $sent = (int) Redis::get("{$key}:sent") ?: $campaign->total_sent;
            $failed = (int) Redis::get("{$key}:failed") ?: $campaign->total_failed;
            $skipped = (int) Redis::get("{$key}:skipped") ?: $campaign->total_skipped;
            $delivered = (int) Redis::get("{$key}:delivered") ?: $campaign->total_delivered;

            $this->table(
                ['Metric', 'Count', 'Percentage'],
                [
                    ['Sent', number_format($sent), $campaign->total_with_mobile > 0 ? round($sent / $campaign->total_with_mobile * 100, 2) . '%' : '0%'],
                    ['Delivered', number_format($delivered), $sent > 0 ? round($delivered / $sent * 100, 2) . '%' : '0%'],
                    ['Failed', number_format($failed), $campaign->total_with_mobile > 0 ? round($failed / $campaign->total_with_mobile * 100, 2) . '%' : '0%'],
                    ['Skipped', number_format($skipped), $campaign->total_with_mobile > 0 ? round($skipped / $campaign->total_with_mobile * 100, 2) . '%' : '0%'],
                ]
            );

            // Calculate rate
            if ($campaign->started_at) {
                $elapsed = now()->diffInSeconds($campaign->started_at);
                if ($elapsed > 0) {
                    $rate = round($sent / $elapsed, 1);
                    $this->info("Average rate: {$rate} messages/second");
                    $this->info("Average rate: " . round($rate * 60) . " messages/minute");

                    if ($campaign->total_with_mobile > $sent) {
                        $remaining = $campaign->total_with_mobile - $sent - $failed - $skipped;
                        $etaSeconds = $rate > 0 ? $remaining / $rate : 0;
                        $etaHours = round($etaSeconds / 3600, 1);
                        $this->info("Estimated remaining time: {$etaHours} hours");
                    }
                }
            }

            // Top error reasons
            $errors = MessageLog::where('campaign_id', $campaign->_id)
                ->where('status', 'failed')
                ->whereNotNull('error_message')
                ->raw(function ($collection) {
                    return $collection->aggregate([
                        ['$group' => ['_id' => '$error_message', 'count' => ['$sum' => 1]]],
                        ['$sort' => ['count' => -1]],
                        ['$limit' => 5],
                    ]);
                });

            if (!empty($errors)) {
                $this->info("\nTop Error Reasons:");
                foreach ($errors as $error) {
                    $this->warn("  [{$error['count']}x] {$error['_id']}");
                }
            }
        }

        return 0;
    }
}
