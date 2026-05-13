@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">
    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Accounts</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total_accounts'] }}</p>
                    <p class="text-xs text-green-600 mt-1">{{ $stats['active_accounts'] }} connected</p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users-cog text-green-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Templates</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total_templates'] }}</p>
                    <p class="text-xs text-green-600 mt-1">{{ $stats['approved_templates'] }} approved</p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-file-alt text-blue-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Campaigns</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total_campaigns'] }}</p>
                    <p class="text-xs text-orange-600 mt-1">{{ $stats['running_campaigns'] }} running</p>
                </div>
                <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-bullhorn text-orange-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Assemblies</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total_assemblies'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">234 total</p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-map-marked-alt text-purple-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Messages Sent</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($stats['total_messages_sent']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">All time</p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-paper-plane text-emerald-500 text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <a href="{{ route('accounts.create') }}" class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center group-hover:bg-green-500 transition">
                    <i class="fas fa-plus text-green-600 text-xl group-hover:text-white transition"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">Add WhatsApp Account</h3>
                    <p class="text-sm text-gray-500">Add new Meta credentials</p>
                </div>
            </div>
        </a>
        <a href="{{ route('accounts.index') }}" class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-500 transition">
                    <i class="fas fa-file-alt text-blue-600 text-xl group-hover:text-white transition"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">Create Template</h3>
                    <p class="text-sm text-gray-500">Design and submit for approval</p>
                </div>
            </div>
        </a>
        <a href="{{ route('campaigns.index') }}" class="bg-white rounded-xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition group">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-orange-100 rounded-xl flex items-center justify-center group-hover:bg-orange-500 transition">
                    <i class="fas fa-bullhorn text-orange-600 text-xl group-hover:text-white transition"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">View Campaigns</h3>
                    <p class="text-sm text-gray-500">Monitor active campaigns</p>
                </div>
            </div>
        </a>
    </div>

    {{-- Recent Campaigns --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Recent Campaigns</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Campaign</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Account</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Status</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Progress</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentCampaigns as $campaign)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <a href="{{ route('campaigns.show', $campaign->_id) }}" class="text-sm font-medium text-blue-600 hover:underline">
                                {{ $campaign->name }}
                            </a>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ $campaign->account_name }}</td>
                        <td class="px-6 py-3">
                            @php $statusColors = ['draft'=>'gray','queued'=>'yellow','running'=>'blue','paused'=>'orange','completed'=>'green','failed'=>'red']; @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $statusColors[$campaign->status] ?? 'gray' }}-100 text-{{ $statusColors[$campaign->status] ?? 'gray' }}-800">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $campaign->getProgressPercentage() }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ $campaign->getProgressPercentage() }}%</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $campaign->created_at?->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">No campaigns yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
