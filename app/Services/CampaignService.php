<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\WhatsAppAccount;
use App\Models\Template;
use App\Models\MessageLog;
use App\Jobs\ProcessCampaignBatch;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    protected VoterService $voterService;
    protected MetaWhatsAppService $metaService;

    public function __construct(VoterService $voterService, MetaWhatsAppService $metaService)
    {
        $this->voterService = $voterService;
        $this->metaService = $metaService;
    }

    /**
     * Create a new campaign
     */
    public function createCampaign(
        WhatsAppAccount $account,
        Template $template,
        array $assemblyNumbers,
        string $name,
        ?array $testNumbers = null
    ): Campaign {
        $isTestMode = !empty($testNumbers);

        if ($isTestMode) {
            // Test mode: only send to specific numbers
            $totalVoters = count($testNumbers);
            $totalWithMobile = count($testNumbers);
        } else {
            // Normal mode: count voters from MySQL
            $counts = $this->voterService->countVotersWithMobile($assemblyNumbers);
            $totalVoters = $counts['total_voters'];
            $totalWithMobile = $counts['total_with_mobile'];
        }

        $campaign = Campaign::create([
            'account_id' => $account->_id,
            'template_id' => $template->_id,
            'name' => $name,
            'assemblies' => $assemblyNumbers,
            'total_voters' => $totalVoters,
            'total_with_mobile' => $totalWithMobile,
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_read' => 0,
            'total_failed' => 0,
            'total_skipped' => 0,
            'status' => Campaign::STATUS_DRAFT,
            'rate_per_second' => (int) config('app.campaign_rate_limit', 80),
            'batch_size' => (int) config('app.campaign_batch_size', 500),
            'is_test' => $isTestMode,
            'test_numbers' => $testNumbers,
        ]);

        return $campaign;
    }

    /**
     * Start a campaign - dispatches batched jobs to Redis queue
     */
    public function startCampaign(Campaign $campaign): bool
    {
        if ($campaign->status === Campaign::STATUS_RUNNING) {
            return false;
        }

        $campaign->update([
            'status' => Campaign::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        // Initialize Redis tracking
        $this->initRedisTracking($campaign);

        // Test mode: send directly to test numbers
        if ($campaign->is_test && !empty($campaign->test_numbers)) {
            ProcessCampaignBatch::dispatch(
                $campaign->_id,
                0, // no assembly
                0, // no offset
                count($campaign->test_numbers),
                true, // test mode flag
                $campaign->test_numbers
            )->onQueue('campaign_messages');

            return true;
        }

        // Normal mode: dispatch assembly processing jobs
        $assemblies = $campaign->assemblies;
        $batchSize = $campaign->batch_size ?? 500;

        foreach ($assemblies as $index => $acNo) {
            $offset = 0;
            $hasMore = true;

            while ($hasMore) {
                ProcessCampaignBatch::dispatch(
                    $campaign->_id,
                    $acNo,
                    $offset,
                    $batchSize
                )->onQueue('campaign_messages')
                 ->delay(now()->addSeconds(intval($index * 0.5)));

                // Pre-calculate next offset from cached counts
                $offset += $batchSize;
                $counts = $this->voterService->countVotersWithMobile([$acNo]);
                $totalInAssembly = $counts['total_with_mobile'];

                $hasMore = $offset < $totalInAssembly;
            }
        }

        return true;
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(Campaign $campaign): bool
    {
        $campaign->update(['status' => Campaign::STATUS_PAUSED]);
        Redis::set("campaign:{$campaign->_id}:paused", 1);
        return true;
    }

    /**
     * Resume a campaign
     */
    public function resumeCampaign(Campaign $campaign): bool
    {
        $campaign->update(['status' => Campaign::STATUS_RUNNING]);
        Redis::del("campaign:{$campaign->_id}:paused");

        // Re-dispatch from last processed point
        $lastAssembly = $campaign->last_processed_assembly ?? $campaign->assemblies[0];
        $lastOffset = $campaign->last_processed_offset ?? 0;
        $batchSize = $campaign->batch_size ?? 500;

        $assemblies = $campaign->assemblies;
        $startIndex = array_search($lastAssembly, $assemblies);
        if ($startIndex === false) $startIndex = 0;

        for ($i = $startIndex; $i < count($assemblies); $i++) {
            $acNo = $assemblies[$i];
            $offset = ($i === $startIndex) ? $lastOffset : 0;
            $counts = $this->voterService->countVotersWithMobile([$acNo]);
            $totalInAssembly = $counts['total_with_mobile'];

            while ($offset < $totalInAssembly) {
                ProcessCampaignBatch::dispatch(
                    $campaign->_id,
                    $acNo,
                    $offset,
                    $batchSize
                )->onQueue('campaign_messages');

                $offset += $batchSize;
            }
        }

        return true;
    }

    /**
     * Get live campaign stats from Redis
     */
    public function getLiveStats(string $campaignId): array
    {
        $key = "campaign:{$campaignId}";

        return [
            'total_sent' => (int) Redis::get("{$key}:sent") ?: 0,
            'total_failed' => (int) Redis::get("{$key}:failed") ?: 0,
            'total_skipped' => (int) Redis::get("{$key}:skipped") ?: 0,
            'total_delivered' => (int) Redis::get("{$key}:delivered") ?: 0,
            'current_assembly' => Redis::get("{$key}:current_assembly") ?: 'N/A',
            'current_offset' => (int) Redis::get("{$key}:current_offset") ?: 0,
            'messages_per_minute' => (int) Redis::get("{$key}:rate") ?: 0,
            'is_paused' => (bool) Redis::get("{$key}:paused"),
            'started_at' => Redis::get("{$key}:started_at"),
            'last_activity' => Redis::get("{$key}:last_activity"),
        ];
    }

    /**
     * Initialize Redis tracking keys for a campaign
     */
    protected function initRedisTracking(Campaign $campaign): void
    {
        $key = "campaign:{$campaign->_id}";

        Redis::set("{$key}:sent", 0);
        Redis::set("{$key}:failed", 0);
        Redis::set("{$key}:skipped", 0);
        Redis::set("{$key}:delivered", 0);
        Redis::set("{$key}:started_at", now()->toISOString());
        Redis::set("{$key}:last_activity", now()->toISOString());
        Redis::del("{$key}:paused");

        // Set TTL for 7 days
        $ttl = 7 * 24 * 3600;
        foreach (['sent', 'failed', 'skipped', 'delivered', 'started_at', 'last_activity', 'current_assembly', 'current_offset', 'rate'] as $suffix) {
            Redis::expire("{$key}:{$suffix}", $ttl);
        }
    }

    /**
     * Sync Redis counters back to MongoDB campaign
     */
    public function syncStatsToDb(Campaign $campaign): void
    {
        $stats = $this->getLiveStats($campaign->_id);

        $campaign->update([
            'total_sent' => $stats['total_sent'],
            'total_failed' => $stats['total_failed'],
            'total_skipped' => $stats['total_skipped'],
            'total_delivered' => $stats['total_delivered'],
        ]);

        // Check if campaign is complete
        $totalProcessed = $stats['total_sent'] + $stats['total_failed'] + $stats['total_skipped'];
        if ($totalProcessed >= $campaign->total_with_mobile && $campaign->status === Campaign::STATUS_RUNNING) {
            $campaign->update([
                'status' => Campaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }
}
