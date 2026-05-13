<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\Template;
use App\Models\Campaign;
use App\Models\Assembly;
use App\Services\CampaignService;
use App\Services\VoterService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    protected CampaignService $campaignService;
    protected VoterService $voterService;

    public function __construct(CampaignService $campaignService, VoterService $voterService)
    {
        $this->campaignService = $campaignService;
        $this->voterService = $voterService;
    }

    public function index()
    {
        $campaigns = Campaign::orderBy('created_at', 'desc')->paginate(20);

        $campaigns->each(function ($campaign) {
            $campaign->account_name = WhatsAppAccount::find($campaign->account_id)?->name ?? 'N/A';
            $campaign->template_name = Template::find($campaign->template_id)?->name ?? 'N/A';
        });

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(string $accountId, string $templateId)
    {
        $account = WhatsAppAccount::findOrFail($accountId);
        $template = Template::findOrFail($templateId);

        if (!$template->isApproved()) {
            return redirect()->route('accounts.show', $accountId)
                ->with('error', 'Template must be approved before starting a campaign.');
        }

        $assemblies = $this->voterService->getAssembliesGrouped();
        $allAssemblies = $this->voterService->getAllAssemblies();

        return view('campaigns.create', compact('account', 'template', 'assemblies', 'allAssemblies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|string',
            'template_id' => 'required|string',
            'name' => 'required|string|max:255',
            'assemblies' => 'nullable|array',
            'assemblies.*' => 'integer',
            'select_all' => 'nullable|boolean',
            'test_mode' => 'nullable',
            'test_numbers' => 'nullable|string',
        ]);

        // Require assemblies if not in test mode
        if (!$request->has('test_mode') && empty($validated['assemblies'])) {
            return back()->withErrors(['assemblies' => 'Please select at least one assembly.'])->withInput();
        }

        $account = WhatsAppAccount::findOrFail($validated['account_id']);
        $template = Template::findOrFail($validated['template_id']);

        // If "select all" is checked, get all assembly numbers
        $assemblyNumbers = $validated['assemblies'] ?? [];
        if ($request->boolean('select_all')) {
            $assemblyNumbers = Assembly::pluck('ac_no')->toArray();
        }

        // Parse test numbers if test mode is enabled
        $testNumbers = null;
        if ($request->has('test_mode') && !empty($validated['test_numbers'])) {
            $testNumbers = array_map('trim', explode(',', $validated['test_numbers']));
            $testNumbers = array_filter($testNumbers);
        }

        $campaign = $this->campaignService->createCampaign(
            $account,
            $template,
            $assemblyNumbers,
            $validated['name'],
            $testNumbers
        );

        return redirect()->route('campaigns.show', $campaign->_id)
            ->with('success', 'Campaign created! Review details and start when ready.');
    }

    public function show(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $account = WhatsAppAccount::find($campaign->account_id);
        $template = Template::find($campaign->template_id);
        $liveStats = $this->campaignService->getLiveStats($id);

        // Get assembly details
        $assemblyDetails = Assembly::whereIn('ac_no', $campaign->assemblies ?? [])
            ->orderBy('ac_no')
            ->get();

        return view('campaigns.show', compact('campaign', 'account', 'template', 'liveStats', 'assemblyDetails'));
    }

    public function start(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->campaignService->startCampaign($campaign);

        return redirect()->route('campaigns.show', $id)
            ->with('success', 'Campaign started! Messages are being sent.');
    }

    public function pause(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->campaignService->pauseCampaign($campaign);

        return redirect()->route('campaigns.show', $id)
            ->with('info', 'Campaign paused.');
    }

    public function resume(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $this->campaignService->resumeCampaign($campaign);

        return redirect()->route('campaigns.show', $id)
            ->with('success', 'Campaign resumed!');
    }

    public function liveStats(string $id)
    {
        $campaign = Campaign::findOrFail($id);
        $stats = $this->campaignService->getLiveStats($id);
        $stats['progress'] = $campaign->getProgressPercentage();
        $stats['status'] = $campaign->status;
        $stats['total_with_mobile'] = $campaign->total_with_mobile;

        // Also sync to DB periodically via this endpoint
        $this->campaignService->syncStatsToDb($campaign);

        return response()->json($stats);
    }

    public function getVoterCount(Request $request)
    {
        $assemblyNumbers = $request->input('assemblies', []);
        if (empty($assemblyNumbers)) {
            return response()->json(['total_voters' => 0, 'total_with_mobile' => 0]);
        }

        $counts = $this->voterService->countVotersWithMobile($assemblyNumbers);
        return response()->json($counts);
    }
}
