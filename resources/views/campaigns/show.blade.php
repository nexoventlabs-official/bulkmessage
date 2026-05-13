@extends('layouts.app')
@section('title', 'Campaign: ' . $campaign->name)
@section('page-title', $campaign->name)

@section('content')
<div x-data="campaignMonitor()" x-init="startPolling()" class="space-y-6">

    {{-- Top Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Status</p>
            @php $sc = ['draft'=>'gray','queued'=>'yellow','running'=>'blue','paused'=>'orange','completed'=>'green','failed'=>'red']; $color = $sc[$campaign->status] ?? 'gray'; @endphp
            <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-{{ $color }}-100 text-{{ $color }}-800" x-text="stats.status ? stats.status.charAt(0).toUpperCase() + stats.status.slice(1) : '{{ ucfirst($campaign->status) }}'">
            </span>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Total Target</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ number_format($campaign->total_with_mobile) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Sent</p>
            <p class="text-xl font-bold text-green-600 mt-1" x-text="formatNumber(stats.total_sent)">{{ number_format($campaign->total_sent) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Delivered</p>
            <p class="text-xl font-bold text-blue-600 mt-1" x-text="formatNumber(stats.total_delivered)">{{ number_format($campaign->total_delivered) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Failed</p>
            <p class="text-xl font-bold text-red-600 mt-1" x-text="formatNumber(stats.total_failed)">{{ number_format($campaign->total_failed) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Skipped</p>
            <p class="text-xl font-bold text-gray-500 mt-1" x-text="formatNumber(stats.total_skipped)">{{ number_format($campaign->total_skipped) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-500 uppercase font-medium">Speed</p>
            <p class="text-xl font-bold text-purple-600 mt-1" x-text="stats.messages_per_minute + '/min'">0/min</p>
        </div>
    </div>

    {{-- Progress Bar --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-gray-800">Campaign Progress</h3>
            <span class="text-sm font-bold" :class="stats.progress >= 100 ? 'text-green-600' : 'text-blue-600'" x-text="stats.progress + '%'">{{ $campaign->getProgressPercentage() }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
            <div class="h-4 rounded-full transition-all duration-500"
                 :class="stats.progress >= 100 ? 'bg-green-500' : 'bg-gradient-to-r from-blue-500 to-green-500'"
                 :style="'width: ' + stats.progress + '%'">
            </div>
        </div>
        <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
            <span>Current Assembly: <strong x-text="stats.current_assembly">N/A</strong></span>
            <span>Last Activity: <strong x-text="stats.last_activity ? new Date(stats.last_activity).toLocaleTimeString() : 'N/A'">N/A</strong></span>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        @if($campaign->status === 'draft' || $campaign->status === 'queued')
        <form method="POST" action="{{ route('campaigns.start', $campaign->_id) }}">
            @csrf
            <button class="px-6 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition shadow-lg shadow-green-500/20"
                    onclick="return confirm('Start sending {{ number_format($campaign->total_with_mobile) }} messages? This action cannot be undone.')">
                <i class="fas fa-play mr-2"></i>Start Campaign
            </button>
        </form>
        @endif

        @if($campaign->status === 'running')
        <form method="POST" action="{{ route('campaigns.pause', $campaign->_id) }}">
            @csrf
            <button class="px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg text-sm font-medium transition">
                <i class="fas fa-pause mr-2"></i>Pause Campaign
            </button>
        </form>
        @endif

        @if($campaign->status === 'paused')
        <form method="POST" action="{{ route('campaigns.resume', $campaign->_id) }}">
            @csrf
            <button class="px-6 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition">
                <i class="fas fa-play mr-2"></i>Resume Campaign
            </button>
        </form>
        @endif

        <a href="{{ route('campaigns.index') }}" class="px-5 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-1"></i>All Campaigns
        </a>
    </div>

    {{-- Details Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Campaign Info --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Campaign Information</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Account:</span>
                    <span class="font-medium text-gray-800">{{ $account->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Template:</span>
                    <span class="font-medium text-gray-800">{{ $template->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Voters:</span>
                    <span class="font-medium text-gray-800">{{ number_format($campaign->total_voters) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">With Mobile:</span>
                    <span class="font-bold text-green-600">{{ number_format($campaign->total_with_mobile) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Assemblies:</span>
                    <span class="font-medium text-gray-800">{{ count($campaign->assemblies ?? []) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Batch Size:</span>
                    <span class="text-gray-800">{{ $campaign->batch_size ?? 500 }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Rate Limit:</span>
                    <span class="text-gray-800">{{ $campaign->rate_per_second ?? 80 }}/sec</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Started At:</span>
                    <span class="text-gray-800">{{ $campaign->started_at?->format('d M Y, H:i:s') ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Completed At:</span>
                    <span class="text-gray-800">{{ $campaign->completed_at?->format('d M Y, H:i:s') ?? '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Selected Assemblies --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Selected Assemblies ({{ $assemblyDetails->count() }})</h3>
            </div>
            <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                @foreach($assemblyDetails as $assembly)
                <div class="px-5 py-2.5 flex items-center justify-between text-sm hover:bg-gray-50">
                    <div>
                        <span class="font-medium text-gray-800">{{ $assembly->ac_no }}. {{ $assembly->constituency }}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ $assembly->district }}</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ number_format($assembly->total_voters) }} voters</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Live Activity Log --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm" x-show="stats.status === 'running' || stats.status === 'paused'">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
            <h3 class="font-semibold text-gray-800">Live Activity</h3>
        </div>
        <div class="p-5 font-mono text-xs bg-gray-900 text-green-400 rounded-b-xl max-h-48 overflow-y-auto" id="activityLog">
            <template x-for="log in activityLogs" :key="log.time">
                <div class="py-0.5">
                    <span class="text-gray-500" x-text="log.time"></span>
                    <span x-text="log.message"></span>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
function campaignMonitor() {
    return {
        stats: {
            total_sent: {{ $liveStats['total_sent'] ?? $campaign->total_sent }},
            total_delivered: {{ $liveStats['total_delivered'] ?? $campaign->total_delivered }},
            total_failed: {{ $liveStats['total_failed'] ?? $campaign->total_failed }},
            total_skipped: {{ $liveStats['total_skipped'] ?? $campaign->total_skipped }},
            messages_per_minute: {{ $liveStats['messages_per_minute'] ?? 0 }},
            current_assembly: '{{ $liveStats['current_assembly'] ?? 'N/A' }}',
            progress: {{ $campaign->getProgressPercentage() }},
            status: '{{ $campaign->status }}',
            last_activity: null,
        },
        activityLogs: [],
        polling: null,

        startPolling() {
            if (this.stats.status === 'running' || this.stats.status === 'queued') {
                this.polling = setInterval(() => this.fetchStats(), 2000);
            }
        },

        fetchStats() {
            fetch('{{ route("campaigns.live-stats", $campaign->_id) }}', {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => {
                const prevSent = this.stats.total_sent;
                this.stats = { ...this.stats, ...data };

                if (data.total_sent > prevSent) {
                    this.addLog(`Sent ${data.total_sent - prevSent} messages (Total: ${data.total_sent}) | Assembly: AC-${data.current_assembly}`);
                }

                if (data.status === 'completed' || data.status === 'failed') {
                    clearInterval(this.polling);
                    this.addLog(`Campaign ${data.status}!`);
                }
            })
            .catch(err => {
                console.error('Polling error:', err);
            });
        },

        addLog(message) {
            const now = new Date().toLocaleTimeString();
            this.activityLogs.unshift({ time: `[${now}]`, message });
            if (this.activityLogs.length > 50) this.activityLogs.pop();
        },

        formatNumber(n) {
            if (!n) return '0';
            return Number(n).toLocaleString();
        }
    }
}
</script>
@endpush
@endsection
