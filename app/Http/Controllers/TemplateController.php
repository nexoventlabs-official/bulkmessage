<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\Template;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    protected MetaWhatsAppService $metaService;

    public function __construct(MetaWhatsAppService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function create(string $accountId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);
        return view('templates.create', compact('account'));
    }

    public function store(Request $request, string $accountId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|max:10',
            'category' => 'required|in:marketing,utility,authentication',
            'header_type' => 'nullable|in:none,text,image,video,document',
            'header_media' => 'nullable|file|max:16384',
            'body_text' => 'required|string|max:1024',
            'footer_text' => 'nullable|string|max:60',
            'buttons' => 'nullable|array',
            'buttons.*.type' => 'required_with:buttons|in:url,phone_number,quick_reply',
            'buttons.*.text' => 'required_with:buttons|string|max:25',
            'buttons.*.url' => 'nullable|url',
            'buttons.*.phone_number' => 'nullable|string',
        ]);

        $mediaUrl = null;
        $mediaPublicId = null;
        $mediaType = null;

        // Upload media to Cloudinary
        if ($request->hasFile('header_media') && $validated['header_type'] !== 'none') {
            $file = $request->file('header_media');
            $resourceType = in_array($validated['header_type'], ['video']) ? 'video' : 'image';

            $uploadResult = Cloudinary::upload($file->getRealPath(), [
                'folder' => 'bulk-campaign/templates',
                'resource_type' => $resourceType,
                'public_id' => 'template_' . Str::slug($validated['name']) . '_' . time(),
            ]);

            $mediaUrl = $uploadResult->getSecurePath();
            $mediaPublicId = $uploadResult->getPublicId();
            $mediaType = $file->getMimeType();
        }

        // Generate Meta template name (lowercase, underscores, no spaces)
        $metaTemplateName = strtolower(Str::slug($validated['name'], '_'));

        $template = Template::create([
            'account_id' => $account->_id,
            'name' => $validated['name'],
            'meta_template_name' => $metaTemplateName,
            'language' => $validated['language'],
            'category' => $validated['category'],
            'header_type' => $validated['header_type'] ?? 'none',
            'header_media_url' => $mediaUrl,
            'header_media_public_id' => $mediaPublicId,
            'header_media_type' => $mediaType,
            'body_text' => $validated['body_text'],
            'footer_text' => $validated['footer_text'],
            'buttons' => $validated['buttons'] ?? [],
            'status' => Template::STATUS_DRAFT,
        ]);

        // Auto-submit to Meta for approval using the account's credentials
        $result = $this->metaService->submitTemplate($account, $template);

        if ($result['success']) {
            $template->update([
                'meta_template_id' => $result['template_id'],
                'status' => Template::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            return redirect()->route('accounts.show', $accountId)
                ->with('success', 'Template created and automatically submitted to Meta for approval! Status will update when Meta reviews it.');
        }

        // Template saved locally but Meta submission failed
        return redirect()->route('accounts.show', $accountId)
            ->with('info', 'Template saved but Meta submission failed: ' . ($result['error'] ?? 'Unknown error') . '. You can retry from the template list.');
    }

    public function preview(string $accountId, string $templateId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);
        $template = Template::findOrFail($templateId);

        return view('templates.preview', compact('account', 'template'));
    }

    public function submitForApproval(string $accountId, string $templateId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);
        $template = Template::findOrFail($templateId);

        if ($template->status !== Template::STATUS_DRAFT && $template->status !== Template::STATUS_REJECTED) {
            return back()->with('error', 'Template is already submitted or approved.');
        }

        $result = $this->metaService->submitTemplate($account, $template);

        if ($result['success']) {
            $template->update([
                'meta_template_id' => $result['template_id'],
                'status' => Template::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            return back()->with('success', 'Template submitted to Meta for approval!');
        }

        $errorMsg = $result['error'] ?? 'Unknown error';
        if (!empty($result['error_details']['error_user_msg'])) {
            $errorMsg .= ' — ' . $result['error_details']['error_user_msg'];
        }
        return back()->with('error', 'Submission failed: ' . $errorMsg);
    }

    public function checkStatus(string $accountId, string $templateId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);
        $template = Template::findOrFail($templateId);

        if (!$template->meta_template_id) {
            return response()->json(['status' => 'draft', 'message' => 'Not yet submitted']);
        }

        $result = $this->metaService->checkTemplateStatus($account, $template->meta_template_id);

        $newStatus = match ($result['status']) {
            'approved' => Template::STATUS_APPROVED,
            'rejected' => Template::STATUS_REJECTED,
            default => $template->status,
        };

        if ($newStatus !== $template->status) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === Template::STATUS_APPROVED) {
                $updateData['approved_at'] = now();
            }
            if ($newStatus === Template::STATUS_REJECTED) {
                $updateData['rejection_reason'] = $result['rejection_reason'];
            }
            $template->update($updateData);
        }

        return response()->json([
            'status' => $newStatus,
            'rejection_reason' => $result['rejection_reason'] ?? null,
        ]);
    }

    public function destroy(string $accountId, string $templateId)
    {
        $template = Template::findOrFail($templateId);

        // Delete media from Cloudinary
        if ($template->header_media_public_id) {
            try {
                Cloudinary::destroy($template->header_media_public_id);
            } catch (\Exception $e) {
                // Continue even if cloudinary delete fails
            }
        }

        $template->delete();

        return redirect()->route('accounts.show', $accountId)
            ->with('success', 'Template deleted successfully!');
    }
}
