@extends('layouts.app')
@section('title', $account->name)
@section('page-title', $account->name)

@section('header-actions')
<a href="{{ route('templates.create', $account->_id) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
    <i class="fas fa-plus mr-1"></i> Create Template
</a>
@endsection

@section('content')
<div class="space-y-6">
    {{-- Account Info Card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center">
                    <i class="fab fa-whatsapp text-green-600 text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">{{ $account->name }}</h2>
                    <p class="text-gray-500">{{ $account->mobile_number }}</p>
                    <div class="flex items-center gap-3 mt-2">
                        @if($account->connection_status === 'connected')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Connected
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                <span class="w-2 h-2 bg-red-500 rounded-full"></span> Disconnected
                            </span>
                        @endif
                        <span class="text-xs text-gray-400">Last checked: {{ $account->last_checked_at?->diffForHumans() ?? 'Never' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('accounts.edit', $account->_id) }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <form method="POST" action="{{ route('accounts.destroy', $account->_id) }}" onsubmit="return confirm('Delete this account?')">
                    @csrf @method('DELETE')
                    <button class="px-3 py-2 border border-red-300 rounded-lg text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Webhook Configuration Card --}}
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 shadow-sm p-6" x-data="{ copied: null }">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-link text-white text-sm"></i>
            </div>
            <div>
                <h3 class="font-semibold text-blue-900">Meta Webhook Configuration</h3>
                <p class="text-xs text-blue-600">Add these details in your Meta Developer Console &rarr; WhatsApp &rarr; Configuration</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Callback URL --}}
            <div>
                <label class="block text-xs font-semibold text-blue-800 mb-1.5">
                    <i class="fas fa-globe mr-1"></i> Callback URL
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly id="webhookUrl"
                           value="{{ config('app.url') }}/api/webhook"
                           class="flex-1 px-3 py-2.5 bg-white border border-blue-300 rounded-lg text-sm font-mono text-gray-800 select-all">
                    <button @click="navigator.clipboard.writeText($refs.webhookUrl?.value || document.getElementById('webhookUrl').value); copied = 'url'; setTimeout(() => copied = null, 2000)"
                            class="px-3 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition whitespace-nowrap"
                            :class="copied === 'url' && 'bg-green-500'">
                        <i :class="copied === 'url' ? 'fas fa-check' : 'fas fa-copy'"></i>
                        <span x-text="copied === 'url' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
                <p class="text-[10px] text-blue-500 mt-1">
                    <i class="fas fa-exclamation-triangle mr-0.5"></i>
                    For production, replace <code>localhost</code> with your public domain (e.g., <code>https://yourdomain.com/api/webhook</code>)
                </p>
            </div>

            {{-- Verify Token --}}
            <div>
                <label class="block text-xs font-semibold text-blue-800 mb-1.5">
                    <i class="fas fa-key mr-1"></i> Verify Token
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" readonly id="verifyToken"
                           value="{{ $account->meta_verify_token }}"
                           class="flex-1 px-3 py-2.5 bg-white border border-blue-300 rounded-lg text-sm font-mono text-gray-800 select-all">
                    <button @click="navigator.clipboard.writeText(document.getElementById('verifyToken').value); copied = 'token'; setTimeout(() => copied = null, 2000)"
                            class="px-3 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition whitespace-nowrap"
                            :class="copied === 'token' && 'bg-green-500'">
                        <i :class="copied === 'token' ? 'fas fa-check' : 'fas fa-copy'"></i>
                        <span x-text="copied === 'token' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
                <p class="text-[10px] text-blue-500 mt-1">Auto-generated. Paste this in Meta's "Verify Token" field.</p>
            </div>
        </div>

        {{-- Steps Guide --}}
        <div class="mt-4 bg-white/60 rounded-lg p-4 border border-blue-100">
            <p class="text-xs font-semibold text-blue-800 mb-2"><i class="fas fa-list-ol mr-1"></i> Setup Steps in Meta Developer Console:</p>
            <ol class="text-xs text-blue-700 space-y-1 list-decimal list-inside">
                <li>Go to <strong>Meta for Developers</strong> &rarr; Your App &rarr; <strong>WhatsApp</strong> &rarr; <strong>Configuration</strong></li>
                <li>Under <strong>Webhook</strong>, click <strong>Edit</strong></li>
                <li>Paste the <strong>Callback URL</strong> above</li>
                <li>Paste the <strong>Verify Token</strong> above</li>
                <li>Click <strong>Verify and Save</strong></li>
                <li>Subscribe to: <code>messages</code>, <code>message_template_status_update</code></li>
            </ol>
        </div>
    </div>

    {{-- Templates --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Templates</h3>
            <a href="{{ route('templates.create', $account->_id) }}" class="text-sm text-blue-600 hover:underline">
                <i class="fas fa-plus mr-1"></i>New Template
            </a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($templates as $template)
            <div class="p-5 hover:bg-gray-50 transition">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="font-medium text-gray-800">{{ $template->name }}</h4>
                            @php
                                $statusConfig = [
                                    'draft' => ['bg-gray-100', 'text-gray-700', 'fas fa-file'],
                                    'pending' => ['bg-yellow-100', 'text-yellow-700', 'fas fa-clock'],
                                    'approved' => ['bg-green-100', 'text-green-700', 'fas fa-check-circle'],
                                    'rejected' => ['bg-red-100', 'text-red-700', 'fas fa-times-circle'],
                                ];
                                $sc = $statusConfig[$template->status] ?? $statusConfig['draft'];
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $sc[0] }} {{ $sc[1] }}">
                                <i class="{{ $sc[2] }} text-[10px]"></i> {{ ucfirst($template->status) }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $template->body_text }}</p>
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                            <span><i class="fas fa-language mr-1"></i>{{ $template->language }}</span>
                            <span><i class="fas fa-tag mr-1"></i>{{ ucfirst($template->category) }}</span>
                            @if($template->header_type !== 'none' && $template->header_type)
                                <span><i class="fas fa-image mr-1"></i>{{ ucfirst($template->header_type) }} header</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 ml-4">
                        <a href="{{ route('templates.preview', [$account->_id, $template->_id]) }}"
                           class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-xs font-medium text-gray-700 transition">
                            <i class="fas fa-eye mr-1"></i>Preview
                        </a>

                        @if($template->status === 'draft' || $template->status === 'rejected')
                        <form method="POST" action="{{ route('templates.submit', [$account->_id, $template->_id]) }}">
                            @csrf
                            <button class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 rounded-lg text-xs font-medium text-white transition">
                                <i class="fas fa-paper-plane mr-1"></i>Submit
                            </button>
                        </form>
                        @endif

                        @if($template->status === 'pending')
                        <button onclick="checkTemplateStatus('{{ $account->_id }}', '{{ $template->_id }}')"
                                class="px-3 py-1.5 bg-yellow-100 hover:bg-yellow-200 rounded-lg text-xs font-medium text-yellow-700 transition">
                            <i class="fas fa-sync mr-1"></i>Check Status
                        </button>
                        @endif

                        @if($template->status === 'approved')
                        <a href="{{ route('campaigns.create', [$account->_id, $template->_id]) }}"
                           class="px-3 py-1.5 bg-green-500 hover:bg-green-600 rounded-lg text-xs font-medium text-white transition">
                            <i class="fas fa-bullhorn mr-1"></i>Start Campaign
                        </a>
                        @endif

                        <form method="POST" action="{{ route('templates.destroy', [$account->_id, $template->_id]) }}"
                              onsubmit="return confirm('Delete template?')">
                            @csrf @method('DELETE')
                            <button class="px-2 py-1.5 text-red-400 hover:text-red-600 text-xs"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-gray-400">
                <i class="fas fa-file-alt text-3xl mb-2"></i>
                <p>No templates yet. Create one to start campaigns.</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Recent Campaigns for this account --}}
    @if($campaigns->count())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Recent Campaigns</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach($campaigns as $campaign)
            <a href="{{ route('campaigns.show', $campaign->_id) }}" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-gray-800 text-sm">{{ $campaign->name }}</h4>
                        <p class="text-xs text-gray-400">{{ $campaign->created_at?->diffForHumans() }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-medium px-2 py-1 rounded-full
                            {{ $campaign->status === 'completed' ? 'bg-green-100 text-green-700' : ($campaign->status === 'running' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">
                            {{ ucfirst($campaign->status) }}
                        </span>
                        <p class="text-xs text-gray-400 mt-1">{{ number_format($campaign->total_sent) }} sent</p>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function checkTemplateStatus(accountId, templateId) {
    fetch(`/accounts/${accountId}/templates/${templateId}/status`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        alert(`Template status: ${data.status}`);
        location.reload();
    });
}
</script>
@endpush
@endsection
