<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    protected string $baseUrl = 'https://graph.facebook.com/v19.0';

    /**
     * Check connection status for a WhatsApp account
     */
    public function checkConnection(WhatsAppAccount $account): array
    {
        try {
            $response = Http::withToken($account->getDecryptedToken())
                ->get("{$this->baseUrl}/{$account->meta_phone_number_id}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'connected' => true,
                    'phone_number' => $data['display_phone_number'] ?? 'Unknown',
                    'quality_rating' => $data['quality_rating'] ?? 'Unknown',
                    'status' => $data['code_verification_status'] ?? 'Unknown',
                ];
            }

            return [
                'connected' => false,
                'error' => $response->json('error.message', 'Unknown error'),
            ];
        } catch (\Exception $e) {
            Log::error("Meta API connection check failed: {$e->getMessage()}");
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit a template to Meta for approval
     */
    public function submitTemplate(WhatsAppAccount $account, Template $template): array
    {
        try {
            $components = [];

            // Header component
            if ($template->header_type && $template->header_type !== 'none') {
                $headerComponent = [
                    'type' => 'HEADER',
                    'format' => strtoupper($template->header_type),
                ];

                if ($template->header_type === 'text' && $template->header_text) {
                    $headerComponent['text'] = $template->header_text;
                }

                if (in_array($template->header_type, ['image', 'video', 'document']) && $template->header_media_url) {
                    // Use Meta's resumable upload to get a header_handle
                    $headerHandle = $this->createHeaderHandle($account, $template->header_media_url, $template->header_type);
                    if ($headerHandle) {
                        $headerComponent['example'] = [
                            'header_handle' => [$headerHandle]
                        ];
                    }
                }

                $components[] = $headerComponent;
            }

            // Body component
            $components[] = [
                'type' => 'BODY',
                'text' => $template->body_text,
            ];

            // Footer component
            if ($template->footer_text) {
                $components[] = [
                    'type' => 'FOOTER',
                    'text' => $template->footer_text,
                ];
            }

            // Buttons - remove null fields that cause "Invalid parameter"
            if (!empty($template->buttons)) {
                $buttons = array_map(function ($btn) {
                    $button = [
                        'type' => strtoupper($btn['type']),
                        'text' => $btn['text'],
                    ];
                    if (!empty($btn['url'])) {
                        $button['url'] = $btn['url'];
                    }
                    if (!empty($btn['phone_number'])) {
                        $button['phone_number'] = $btn['phone_number'];
                    }
                    return $button;
                }, $template->buttons);

                $buttonComponent = [
                    'type' => 'BUTTONS',
                    'buttons' => array_values($buttons),
                ];
                $components[] = $buttonComponent;
            }

            $payload = [
                'name' => $template->meta_template_name,
                'language' => $template->language ?? 'en',
                'category' => strtoupper($template->category ?? 'MARKETING'),
                'components' => $components,
            ];

            Log::info('Template submission payload', ['payload' => $payload]);

            // Use WABA ID for template submission (not Business ID)
            $wabaId = $account->meta_waba_id ?: $account->meta_business_id;
            $response = Http::withToken($account->getDecryptedToken())
                ->post("{$this->baseUrl}/{$wabaId}/message_templates", $payload);

            Log::info('Template submission response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'template_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? 'PENDING',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Template submission failed'),
                'error_code' => $response->json('error.code'),
                'error_details' => $response->json('error'),
            ];
        } catch (\Exception $e) {
            Log::error("Template submission failed: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check template status from Meta
     */
    public function checkTemplateStatus(WhatsAppAccount $account, string $templateId): array
    {
        try {
            $response = Http::withToken($account->getDecryptedToken())
                ->get("{$this->baseUrl}/{$templateId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => strtolower($data['status'] ?? 'unknown'),
                    'rejection_reason' => $data['rejected_reason'] ?? null,
                ];
            }

            return ['status' => 'unknown', 'error' => $response->json('error.message')];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a template message to a phone number
     */
    public function sendTemplateMessage(
        WhatsAppAccount $account,
        string $phoneNumber,
        Template $template
    ): array {
        try {
            $phoneNumber = $this->normalizePhoneNumber($phoneNumber);

            if (!$phoneNumber) {
                return ['success' => false, 'error' => 'Invalid phone number'];
            }

            // For templates with image/video headers, send as media message with caption
            // Meta silently drops template messages with image headers on some accounts
            $hasMediaHeader = $template->header_type
                && in_array($template->header_type, ['image', 'video', 'document'])
                && $template->header_media_url;

            if ($hasMediaHeader) {
                return $this->sendAsMediaMessage($account, $phoneNumber, $template);
            }

            // For text-only templates, send as normal template
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'template',
                'template' => [
                    'name' => $template->meta_template_name,
                    'language' => [
                        'code' => $template->language ?? 'en',
                    ],
                ],
            ];

            Log::info("Sending template message", [
                'to' => $phoneNumber,
                'template' => $template->meta_template_name,
            ]);

            $response = Http::withToken($account->getDecryptedToken())
                ->timeout(30)
                ->post("{$this->baseUrl}/{$account->meta_phone_number_id}/messages", $payload);

            Log::info("Send message response", [
                'to' => $phoneNumber,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ];
            }

            $errorCode = $response->json('error.code');
            $errorMsg = $response->json('error.message', 'Send failed');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode,
                'retry' => in_array($errorCode, [130429, 131048, 131026]),
            ];
        } catch (\Exception $e) {
            Log::error("Message send failed to {$phoneNumber}: {$e->getMessage()}");
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => true,
            ];
        }
    }

    /**
     * Send template content as a media message (image/video with caption)
     * Used as workaround for Meta silently dropping template messages with media headers
     */
    protected function sendAsMediaMessage(
        WhatsAppAccount $account,
        string $phoneNumber,
        Template $template
    ): array {
        try {
            $mediaType = $template->header_type; // image, video, document

            // Build caption from template body + footer
            $caption = $template->body_text ?? '';
            if ($template->footer_text) {
                $caption .= "\n\n" . $template->footer_text;
            }

            $mediaPayload = [
                'link' => $template->header_media_url,
            ];

            // Images and videos support captions, documents need filename
            if (in_array($mediaType, ['image', 'video'])) {
                $mediaPayload['caption'] = $caption;
            }

            if ($mediaType === 'document') {
                $mediaPayload['caption'] = $caption;
                $mediaPayload['filename'] = $template->meta_template_name . '.pdf';
            }

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => $mediaType,
                $mediaType => $mediaPayload,
            ];

            Log::info("Sending media message (template workaround)", [
                'to' => $phoneNumber,
                'template' => $template->meta_template_name,
                'media_type' => $mediaType,
            ]);

            $response = Http::withToken($account->getDecryptedToken())
                ->timeout(30)
                ->post("{$this->baseUrl}/{$account->meta_phone_number_id}/messages", $payload);

            Log::info("Media message response", [
                'to' => $phoneNumber,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ];
            }

            $errorCode = $response->json('error.code');
            $errorMsg = $response->json('error.message', 'Send failed');

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode,
                'retry' => in_array($errorCode, [130429, 131048, 131026]),
            ];
        } catch (\Exception $e) {
            Log::error("Media message send failed to {$phoneNumber}: {$e->getMessage()}");
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => true,
            ];
        }
    }

    /**
     * Upload media to Meta's messaging media API (for sending messages)
     */
    protected function uploadMediaToMeta(WhatsAppAccount $account, string $mediaUrl, string $mediaType): string
    {
        $response = Http::withToken($account->getDecryptedToken())
            ->post("{$this->baseUrl}/{$account->meta_phone_number_id}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mediaType,
                'link' => $mediaUrl,
            ]);

        return $response->json('id', '');
    }

    /**
     * Create a header_handle via Meta's resumable upload for template submission.
     * Step 1: Create upload session
     * Step 2: Upload file bytes
     * Returns the header_handle (h:xxxxx) string.
     */
    protected function createHeaderHandle(WhatsAppAccount $account, string $mediaUrl, string $mediaType): ?string
    {
        try {
            // Download the file from Cloudinary
            $fileContent = Http::timeout(30)->get($mediaUrl)->body();
            $fileSize = strlen($fileContent);

            $mimeMap = [
                'image' => 'image/png',
                'video' => 'video/mp4',
                'document' => 'application/pdf',
            ];
            $mimeType = $mimeMap[$mediaType] ?? 'image/png';

            // Step 1: Create an upload session using App ID
            $appId = $account->meta_app_id;
            $response = Http::withToken($account->getDecryptedToken())
                ->post("{$this->baseUrl}/{$appId}/uploads", [
                    'file_length' => $fileSize,
                    'file_type' => $mimeType,
                ]);

            Log::info('Upload session response', ['body' => $response->json()]);

            $uploadSessionId = $response->json('id');
            if (!$uploadSessionId) {
                Log::error('Failed to create upload session', ['response' => $response->json()]);
                return null;
            }

            // Step 2: Upload the file data
            $uploadResponse = Http::withToken($account->getDecryptedToken())
                ->withHeaders([
                    'file_offset' => '0',
                ])
                ->withBody($fileContent, $mimeType)
                ->post("{$this->baseUrl}/{$uploadSessionId}");

            Log::info('Upload file response', ['body' => $uploadResponse->json()]);

            $handle = $uploadResponse->json('h');
            if (!$handle) {
                Log::error('Failed to upload file to Meta', ['response' => $uploadResponse->json()]);
                return null;
            }

            return $handle;
        } catch (\Exception $e) {
            Log::error("Header handle upload failed: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Normalize Indian phone numbers to E.164 format
     */
    protected function normalizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) return null;

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // If starts with 91 and is 12 digits, it's already with country code
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            return '91' . substr($phone, 2);
        }

        // If 10 digits, add India country code
        if (strlen($phone) === 10) {
            return '91' . $phone;
        }

        // If already has country code
        if (strlen($phone) > 10 && str_starts_with($phone, '91')) {
            return $phone;
        }

        return null;
    }
}
