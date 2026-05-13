@extends('layouts.app')
@section('title', 'WhatsApp Accounts')
@section('page-title', 'WhatsApp Accounts')

@section('header-actions')
<a href="{{ route('accounts.create') }}" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
    <i class="fas fa-plus mr-1"></i> Add Account
</a>
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
    @forelse($accounts as $account)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition">
        <div class="p-5">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fab fa-whatsapp text-green-600 text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800">{{ $account->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $account->mobile_number }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1" x-data="{ status: '{{ $account->connection_status }}', checking: false }"
                     id="status-{{ $account->_id }}">
                    <span x-show="!checking">
                        <span x-show="status === 'connected'" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> Connected
                        </span>
                        <span x-show="status === 'disconnected'" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span> Disconnected
                        </span>
                        <span x-show="status === 'checking'" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                            <i class="fas fa-spinner fa-spin text-xs"></i> Checking
                        </span>
                    </span>
                    <span x-show="checking" class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                        <i class="fas fa-spinner fa-spin text-xs"></i> Checking
                    </span>
                </div>
            </div>

            <div class="mt-4 space-y-2 text-xs text-gray-500">
                <div class="flex justify-between">
                    <span>Business ID:</span>
                    <span class="font-mono text-gray-700">{{ Str::limit($account->meta_business_id, 20) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Phone Number ID:</span>
                    <span class="font-mono text-gray-700">{{ Str::limit($account->meta_phone_number_id, 20) }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Templates:</span>
                    <span class="font-medium text-gray-700">{{ $account->templates()->count() }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Last Checked:</span>
                    <span class="text-gray-700">{{ $account->last_checked_at?->diffForHumans() ?? 'Never' }}</span>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-5 py-3 flex items-center justify-between border-t border-gray-100">
            <div class="flex gap-2">
                <a href="{{ route('accounts.show', $account->_id) }}" class="text-xs text-blue-600 hover:underline font-medium">
                    <i class="fas fa-eye mr-1"></i>View
                </a>
                <a href="{{ route('accounts.edit', $account->_id) }}" class="text-xs text-gray-600 hover:underline font-medium">
                    <i class="fas fa-edit mr-1"></i>Edit
                </a>
            </div>
            <button onclick="checkConnection('{{ $account->_id }}')"
                    class="text-xs text-green-600 hover:underline font-medium">
                <i class="fas fa-sync mr-1"></i>Re-check
            </button>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center py-16">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fab fa-whatsapp text-gray-300 text-4xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-600">No WhatsApp Accounts</h3>
        <p class="text-gray-400 mt-1">Add your first WhatsApp Business account to get started.</p>
        <a href="{{ route('accounts.create') }}" class="mt-4 inline-block bg-green-500 hover:bg-green-600 text-white px-5 py-2 rounded-lg text-sm font-medium transition">
            <i class="fas fa-plus mr-1"></i> Add Account
        </a>
    </div>
    @endforelse
</div>

@push('scripts')
<script>
function checkConnection(accountId) {
    fetch(`/accounts/${accountId}/check-connection`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        location.reload();
    })
    .catch(e => alert('Connection check failed'));
}
</script>
@endpush
@endsection
