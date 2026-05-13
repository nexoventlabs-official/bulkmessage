<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    /**
     * Verify webhook (GET request from Meta)
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Check against all stored verify tokens
        $accounts = \App\Models\WhatsAppAccount::all();
        foreach ($accounts as $account) {
            if ($mode === 'subscribe' && $token === $account->meta_verify_token) {
                return response($challenge, 200);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Receive webhook events (POST from Meta)
     */
    public function receive(Request $request)
    {
        $payload = $request->all();

        try {
            if (isset($payload['entry'])) {
                foreach ($payload['entry'] as $entry) {
                    foreach ($entry['changes'] ?? [] as $change) {
                        if ($change['field'] === 'messages') {
                            $this->handleMessageStatus($change['value']);
                        }
                        if ($change['field'] === 'message_template_status_update') {
                            $this->handleTemplateStatus($change['value']);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Webhook processing error: {$e->getMessage()}", ['payload' => $payload]);
        }

        return response('OK', 200);
    }

    /**
     * Handle message delivery status updates
     */
    protected function handleMessageStatus(array $value): void
    {
        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;

            if (!$messageId || !$statusValue) continue;

            $log = MessageLog::where('whatsapp_message_id', $messageId)->first();
            if (!$log) continue;

            $updateData = ['status' => $statusValue];

            switch ($statusValue) {
                case 'delivered':
                    $updateData['delivered_at'] = now();
                    $redisKey = "campaign:{$log->campaign_id}:delivered";
                    Redis::incr($redisKey);
                    break;
                case 'read':
                    $updateData['read_at'] = now();
                    break;
                case 'failed':
                    $updateData['error_message'] = $status['errors'][0]['message'] ?? 'Delivery failed';
                    break;
            }

            $log->update($updateData);
        }
    }

    /**
     * Handle template status updates from Meta
     */
    protected function handleTemplateStatus(array $value): void
    {
        $templateName = $value['message_template_name'] ?? null;
        $newStatus = strtolower($value['event'] ?? '');

        if (!$templateName) return;

        $template = \App\Models\Template::where('meta_template_name', $templateName)->first();
        if (!$template) return;

        $statusMap = [
            'approved' => \App\Models\Template::STATUS_APPROVED,
            'rejected' => \App\Models\Template::STATUS_REJECTED,
            'pending' => \App\Models\Template::STATUS_PENDING,
        ];

        if (isset($statusMap[$newStatus])) {
            $updateData = ['status' => $statusMap[$newStatus]];
            if ($newStatus === 'approved') {
                $updateData['approved_at'] = now();
            }
            if ($newStatus === 'rejected') {
                $updateData['rejection_reason'] = $value['reason'] ?? 'Unknown reason';
            }
            $template->update($updateData);
        }
    }
}
