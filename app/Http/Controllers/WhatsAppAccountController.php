<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhatsAppAccountController extends Controller
{
    protected MetaWhatsAppService $metaService;

    public function __construct(MetaWhatsAppService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function index()
    {
        $accounts = WhatsAppAccount::orderBy('created_at', 'desc')->get();
        return view('accounts.index', compact('accounts'));
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:20',
            'meta_app_id' => 'required|string',
            'meta_app_secret' => 'required|string',
            'meta_access_token' => 'required|string',
            'meta_business_id' => 'nullable|string',
            'meta_phone_number_id' => 'required|string',
            'meta_waba_id' => 'required|string',
            'whatsapp_offer_template' => 'nullable|string|max:255',
        ]);

        // Auto-generate a unique verify token
        $validated['meta_verify_token'] = 'bulk_' . Str::random(32);

        // Encrypt sensitive credentials
        $validated['meta_access_token'] = encrypt($validated['meta_access_token']);
        $validated['meta_app_secret'] = encrypt($validated['meta_app_secret']);
        $validated['connection_status'] = 'checking';
        $validated['is_active'] = true;

        $account = WhatsAppAccount::create($validated);

        // Check connection
        $status = $this->metaService->checkConnection($account);
        $account->update([
            'connection_status' => $status['connected'] ? 'connected' : 'disconnected',
            'last_checked_at' => now(),
        ]);

        return redirect()->route('accounts.show', $account->_id)
            ->with('success', 'WhatsApp account added! ' . ($status['connected'] ? 'Connected successfully.' : 'Connection check failed — verify your credentials.') . ' Now configure the Webhook URL in Meta Developer Console.');
    }

    public function show(string $id)
    {
        $account = WhatsAppAccount::findOrFail($id);
        $templates = $account->templates()->orderBy('created_at', 'desc')->get();
        $campaigns = $account->campaigns()->orderBy('created_at', 'desc')->limit(10)->get();

        return view('accounts.show', compact('account', 'templates', 'campaigns'));
    }

    public function edit(string $id)
    {
        $account = WhatsAppAccount::findOrFail($id);
        return view('accounts.edit', compact('account'));
    }

    public function update(Request $request, string $id)
    {
        $account = WhatsAppAccount::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:20',
            'meta_app_id' => 'required|string',
            'meta_app_secret' => 'nullable|string',
            'meta_access_token' => 'nullable|string',
            'meta_business_id' => 'nullable|string',
            'meta_phone_number_id' => 'required|string',
            'meta_waba_id' => 'required|string',
            'whatsapp_offer_template' => 'nullable|string|max:255',
        ]);

        // Only update secrets if provided
        if (!empty($validated['meta_access_token'])) {
            $validated['meta_access_token'] = encrypt($validated['meta_access_token']);
        } else {
            unset($validated['meta_access_token']);
        }

        if (!empty($validated['meta_app_secret'])) {
            $validated['meta_app_secret'] = encrypt($validated['meta_app_secret']);
        } else {
            unset($validated['meta_app_secret']);
        }

        $account->update($validated);

        return redirect()->route('accounts.show', $id)
            ->with('success', 'Account updated successfully!');
    }

    public function checkConnection(string $id)
    {
        $account = WhatsAppAccount::findOrFail($id);
        $status = $this->metaService->checkConnection($account);

        $account->update([
            'connection_status' => $status['connected'] ? 'connected' : 'disconnected',
            'last_checked_at' => now(),
        ]);

        return response()->json([
            'status' => $status['connected'] ? 'connected' : 'disconnected',
            'details' => $status,
        ]);
    }

    public function destroy(string $id)
    {
        $account = WhatsAppAccount::findOrFail($id);
        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', 'Account deleted successfully!');
    }
}
