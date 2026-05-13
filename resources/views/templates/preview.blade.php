@extends('layouts.app')
@section('title', 'Preview Template')
@section('page-title', 'Template Preview: ' . $template->name)

@section('content')
<div class="max-w-4xl mx-auto" x-data="templateStatus()" x-init="init()">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Template Info --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Template Details</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Name:</span>
                        <span class="font-medium text-gray-800">{{ $template->name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Meta Name:</span>
                        <span class="font-mono text-gray-800 text-xs">{{ $template->meta_template_name }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Language:</span>
                        <span class="text-gray-800">{{ $template->language }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Category:</span>
                        <span class="text-gray-800">{{ ucfirst($template->category) }}</span>
                    </div>
                    <div class="flex justify-between text-sm items-center">
                        <span class="text-gray-500">Status:</span>
                        <div class="flex items-center gap-2">
                            {{-- Real-time status badge --}}
                            <template x-if="status === 'approved'">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    <i class="fas fa-check-circle"></i> Approved
                                </span>
                            </template>
                            <template x-if="status === 'pending'">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                    <i class="fas fa-clock animate-pulse"></i> Pending
                                </span>
                            </template>
                            <template x-if="status === 'rejected'">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            </template>
                            <template x-if="status === 'draft'">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                    <i class="fas fa-pencil-alt"></i> Draft
                                </span>
                            </template>
                            {{-- Polling indicator --}}
                            <span x-show="polling" class="text-[10px] text-gray-400 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></span> checking...
                            </span>
                        </div>
                    </div>
                    {{-- Rejection reason --}}
                    <template x-if="status === 'rejected' && rejectionReason">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                            <strong>Rejection Reason:</strong> <span x-text="rejectionReason"></span>
                        </div>
                    </template>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Header:</span>
                        <span class="text-gray-800">{{ ucfirst($template->header_type ?? 'none') }}</span>
                    </div>
                </div>
            </div>

            {{-- Status change notification --}}
            <template x-if="statusChanged">
                <div class="bg-green-50 border border-green-300 rounded-lg p-3 text-sm text-green-800 flex items-center gap-2 animate-pulse">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span>Template status updated to <strong x-text="status"></strong>!</span>
                </div>
            </template>

            <div class="flex gap-3">
                {{-- Submit button (draft/rejected) --}}
                <template x-if="status === 'draft' || status === 'rejected'">
                    <form method="POST" action="{{ route('templates.submit', [$account->_id, $template->_id]) }}" class="flex-1">
                        @csrf
                        <button class="w-full px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition">
                            <i class="fas fa-paper-plane mr-1"></i> Submit for Meta Approval
                        </button>
                    </form>
                </template>

                {{-- Campaign button (approved) --}}
                <template x-if="status === 'approved'">
                    <a href="{{ route('campaigns.create', [$account->_id, $template->_id]) }}"
                       class="flex-1 px-4 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition text-center">
                        <i class="fas fa-bullhorn mr-1"></i> Start Campaign
                    </a>
                </template>

                {{-- Manual refresh (only for pending) --}}
                <template x-if="status === 'pending'">
                    <button @click="checkNow()" :disabled="polling"
                            class="px-4 py-2.5 border border-blue-300 rounded-lg text-sm font-medium text-blue-600 hover:bg-blue-50 transition"
                            :class="polling && 'opacity-50 cursor-not-allowed'">
                        <i class="fas fa-sync-alt" :class="polling && 'animate-spin'"></i>
                    </button>
                </template>

                <a href="{{ route('accounts.show', $account->_id) }}"
                   class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>

        {{-- WhatsApp Preview --}}
        <div>
            <h4 class="text-sm font-medium text-gray-600 mb-3">WhatsApp Preview</h4>
            <div class="bg-[#e5ddd5] rounded-2xl p-6" style="min-height: 400px">
                <div class="bg-white rounded-xl shadow-sm max-w-sm mx-auto overflow-hidden">
                    @if($template->header_type && $template->header_type !== 'none')
                    <div class="bg-gray-100">
                        @if($template->header_type === 'image' && $template->header_media_url)
                            <img src="{{ $template->header_media_url }}" class="w-full h-48 object-cover">
                        @elseif($template->header_type === 'video' && $template->header_media_url)
                            <video src="{{ $template->header_media_url }}" class="w-full h-48 object-cover" controls></video>
                        @else
                            <div class="h-48 flex items-center justify-center text-gray-400">
                                <i class="fas fa-{{ $template->header_type === 'video' ? 'video' : 'image' }} text-4xl"></i>
                            </div>
                        @endif
                    </div>
                    @endif
                    <div class="p-4">
                        <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $template->body_text }}</p>
                        @if($template->footer_text)
                            <p class="text-xs text-gray-400 mt-3">{{ $template->footer_text }}</p>
                        @endif
                        <p class="text-[10px] text-gray-400 text-right mt-2">{{ now()->format('h:i A') }} <i class="fas fa-check-double text-blue-400 ml-1"></i></p>
                    </div>
                    @if(!empty($template->buttons))
                    <div class="border-t border-gray-100">
                        @foreach($template->buttons as $btn)
                        <div class="text-center py-2 border-b border-gray-100 last:border-0">
                            <span class="text-sm text-blue-500 font-medium">
                                @if($btn['type'] === 'url')<i class="fas fa-external-link-alt mr-1 text-xs"></i>@endif
                                @if($btn['type'] === 'phone_number')<i class="fas fa-phone mr-1 text-xs"></i>@endif
                                {{ $btn['text'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function templateStatus() {
    return {
        status: '{{ $template->status }}',
        rejectionReason: '{{ $template->rejection_reason ?? '' }}',
        polling: false,
        statusChanged: false,
        interval: null,

        init() {
            // Only poll if status is pending (not approved/rejected/draft)
            if (this.status === 'pending') {
                this.checkNow();
                this.startPolling();
            }
        },

        startPolling() {
            this.interval = setInterval(() => this.fetchStatus(), 5000);
        },

        stopPolling() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },

        async checkNow() {
            await this.fetchStatus();
        },

        async fetchStatus() {
            this.polling = true;
            try {
                const response = await fetch('{{ route("templates.status", [$account->_id, $template->_id]) }}');
                const data = await response.json();

                if (data.status && data.status !== this.status) {
                    const oldStatus = this.status;
                    this.status = data.status;
                    this.rejectionReason = data.rejection_reason || '';

                    // Show status change notification
                    this.statusChanged = true;
                    setTimeout(() => this.statusChanged = false, 5000);

                    // Stop polling if no longer pending
                    if (data.status !== 'pending') {
                        this.stopPolling();
                    }
                }
            } catch (e) {
                console.error('Status check failed:', e);
            }
            this.polling = false;
        },
    }
}
</script>
@endsection
