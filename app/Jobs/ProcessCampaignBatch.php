<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\WhatsAppAccount;
use App\Models\Template;
use App\Models\MessageLog;
use App\Services\MetaWhatsAppService;
use App\Services\VoterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ProcessCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes per batch
    public int $backoff = 60;

    protected string $campaignId;
    protected int $assemblyNo;
    protected int $offset;
    protected int $limit;
    protected bool $isTestMode;
    protected array $testNumbers;

    public function __construct(string $campaignId, int $assemblyNo, int $offset, int $limit, bool $isTestMode = false, array $testNumbers = [])
    {
        $this->campaignId = $campaignId;
        $this->assemblyNo = $assemblyNo;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->isTestMode = $isTestMode;
        $this->testNumbers = $testNumbers;
    }

    public function handle(MetaWhatsAppService $metaService, VoterService $voterService): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== Campaign::STATUS_RUNNING) {
            return;
        }

        // Check if paused
        if (Redis::get("campaign:{$this->campaignId}:paused")) {
            // Re-queue with delay
            self::dispatch($this->campaignId, $this->assemblyNo, $this->offset, $this->limit)
                ->onQueue('campaign_messages')
                ->delay(now()->addSeconds(30));
            return;
        }

        $account = WhatsAppAccount::find($campaign->account_id);
        $template = Template::find($campaign->template_id);

        if (!$account || !$template) {
            Log::error("Campaign {$this->campaignId}: Account or template not found");
            return;
        }

        // Update current position in Redis
        $redisKey = "campaign:{$this->campaignId}";
        Redis::set("{$redisKey}:current_assembly", $this->assemblyNo);
        Redis::set("{$redisKey}:current_offset", $this->offset);
        Redis::set("{$redisKey}:last_activity", now()->toISOString());

        // Test mode: use provided test numbers instead of MySQL
        if ($this->isTestMode && !empty($this->testNumbers)) {
            $voterBatch = [
                'data' => array_map(fn($num) => (object)['mobile' => $num, 'voter_id' => 'test'], $this->testNumbers),
            ];
        } else {
            // Get voter batch from MySQL
            $voterBatch = $voterService->getVoterMobilesFromAssembly(
                $this->assemblyNo,
                $this->offset,
                $this->limit
            );
        }

        if (empty($voterBatch['data'])) {
            return;
        }

        $ratePerSecond = $campaign->rate_per_second ?? 80;
        $sentInThisSecond = 0;
        $secondStart = microtime(true);

        $bulkLogs = [];

        foreach ($voterBatch['data'] as $voter) {
            // Check pause again during batch
            if (Redis::get("campaign:{$this->campaignId}:paused")) {
                // Save progress
                $campaign->update([
                    'last_processed_assembly' => $this->assemblyNo,
                    'last_processed_offset' => $this->offset + array_search($voter, $voterBatch['data']),
                ]);
                break;
            }

            $mobile = $voter->mobile ?? $voter['mobile'] ?? null;
            $voterId = $voter->voter_id ?? $voter['voter_id'] ?? null;

            if (empty($mobile)) {
                Redis::incr("{$redisKey}:skipped");
                continue;
            }

            // Rate limiting: pause if we hit the rate limit
            if ($sentInThisSecond >= $ratePerSecond) {
                $elapsed = microtime(true) - $secondStart;
                if ($elapsed < 1.0) {
                    usleep((int)((1.0 - $elapsed) * 1000000));
                }
                $sentInThisSecond = 0;
                $secondStart = microtime(true);
            }

            // Send message
            $result = $metaService->sendTemplateMessage($account, $mobile, $template);

            if ($result['success']) {
                Redis::incr("{$redisKey}:sent");
                $sentInThisSecond++;

                $bulkLogs[] = [
                    'campaign_id' => $this->campaignId,
                    'account_id' => $account->_id,
                    'assembly_no' => $this->assemblyNo,
                    'voter_id' => $voterId,
                    'mobile_number' => $mobile,
                    'whatsapp_message_id' => $result['message_id'],
                    'status' => MessageLog::STATUS_SENT,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } else {
                // Check if should retry (rate limit from Meta)
                if (!empty($result['retry']) && $this->attempts() < $this->tries) {
                    $this->release(60);
                    return;
                }

                Redis::incr("{$redisKey}:failed");

                $bulkLogs[] = [
                    'campaign_id' => $this->campaignId,
                    'account_id' => $account->_id,
                    'assembly_no' => $this->assemblyNo,
                    'voter_id' => $voterId,
                    'mobile_number' => $mobile,
                    'whatsapp_message_id' => null,
                    'status' => MessageLog::STATUS_FAILED,
                    'error_message' => $result['error'] ?? 'Unknown error',
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert logs every 100 records
            if (count($bulkLogs) >= 100) {
                MessageLog::insert($bulkLogs);
                $bulkLogs = [];
            }

            // Update rate counter in Redis
            Redis::set("{$redisKey}:rate", $sentInThisSecond * 60);
        }

        // Insert remaining logs
        if (!empty($bulkLogs)) {
            MessageLog::insert($bulkLogs);
        }

        // Update campaign progress
        $campaign->update([
            'last_processed_assembly' => $this->assemblyNo,
            'last_processed_offset' => $this->offset + $this->limit,
        ]);

        // Sync stats periodically
        $totalProcessed = (int)Redis::get("{$redisKey}:sent") + (int)Redis::get("{$redisKey}:failed") + (int)Redis::get("{$redisKey}:skipped");
        if ($totalProcessed % 5000 === 0 || $totalProcessed >= $campaign->total_with_mobile) {
            app(\App\Services\CampaignService::class)->syncStatsToDb($campaign);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Campaign batch failed: Campaign={$this->campaignId}, AC={$this->assemblyNo}, Offset={$this->offset}, Error={$exception->getMessage()}");

        $redisKey = "campaign:{$this->campaignId}";
        Redis::set("{$redisKey}:last_error", $exception->getMessage());
        Redis::set("{$redisKey}:last_activity", now()->toISOString());
    }
}
