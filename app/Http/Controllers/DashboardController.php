<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\Template;
use App\Models\Campaign;
use App\Models\Assembly;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_accounts' => WhatsAppAccount::count(),
            'active_accounts' => WhatsAppAccount::where('connection_status', 'connected')->count(),
            'total_templates' => Template::count(),
            'approved_templates' => Template::where('status', 'approved')->count(),
            'pending_templates' => Template::where('status', 'pending')->count(),
            'total_campaigns' => Campaign::count(),
            'running_campaigns' => Campaign::where('status', 'running')->count(),
            'completed_campaigns' => Campaign::where('status', 'completed')->count(),
            'total_assemblies' => Assembly::count(),
            'total_messages_sent' => Campaign::sum('total_sent'),
        ];

        $recentCampaigns = Campaign::orderBy('created_at', 'desc')->limit(5)->get();
        $recentCampaigns->each(function ($campaign) {
            $campaign->account_name = WhatsAppAccount::find($campaign->account_id)?->name ?? 'N/A';
            $campaign->template_name = Template::find($campaign->template_id)?->name ?? 'N/A';
        });

        return view('dashboard', compact('stats', 'recentCampaigns'));
    }
}
