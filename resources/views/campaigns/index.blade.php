@extends('layouts.app')
@section('title', 'Campaigns')
@section('page-title', 'All Campaigns')

@section('content')
<div class="bg-white rounded-xl border border-gray-100 shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Campaign</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Account</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Template</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Assemblies</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Status</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Progress</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Sent</th>
                    <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($campaigns as $campaign)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <a href="{{ route('campaigns.show', $campaign->_id) }}" class="text-sm font-medium text-blue-600 hover:underline">
                            {{ $campaign->name }}
                        </a>
                        <p class="text-xs text-gray-400">{{ $campaign->created_at?->format('d M Y, H:i') }}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $campaign->account_name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $campaign->template_name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ count($campaign->assemblies ?? []) }}</td>
                    <td class="px-6 py-4">
                        @php
                            $sc = ['draft'=>'gray','queued'=>'yellow','running'=>'blue','paused'=>'orange','completed'=>'green','failed'=>'red'];
                            $color = $sc[$campaign->status] ?? 'gray';
                        @endphp
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800">
                            @if($campaign->status === 'running')<span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>@endif
                            {{ ucfirst($campaign->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-20 bg-gray-200 rounded-full h-1.5">
                                <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $campaign->getProgressPercentage() }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500">{{ $campaign->getProgressPercentage() }}%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($campaign->total_sent) }}</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('campaigns.show', $campaign->_id) }}" class="text-xs text-blue-600 hover:underline font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-bullhorn text-3xl mb-2"></i>
                        <p>No campaigns yet</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($campaigns->hasPages())
    <div class="px-6 py-3 border-t border-gray-100">
        {{ $campaigns->links() }}
    </div>
    @endif
</div>
@endsection
