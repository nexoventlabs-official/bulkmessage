@extends('layouts.app')
@section('title', 'Create Campaign')
@section('page-title', 'Create Campaign')

@section('content')
<div x-data="campaignCreator()" class="space-y-6">
    <form method="POST" action="{{ route('campaigns.store') }}" id="campaignForm">
        @csrf
        <input type="hidden" name="account_id" value="{{ $account->_id }}">
        <input type="hidden" name="template_id" value="{{ $template->_id }}">

        {{-- Campaign Info --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- Campaign Name --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Campaign Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name *</label>
                            <input type="text" name="name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm"
                                   placeholder="e.g. Festival Greeting - All Assemblies">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Using Template</label>
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border">
                                <i class="fas fa-file-alt text-blue-500"></i>
                                <span class="text-sm text-gray-700">{{ $template->name }}</span>
                                <span class="ml-auto text-xs text-green-600 font-medium">Approved</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Account</label>
                        <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border">
                            <i class="fab fa-whatsapp text-green-500"></i>
                            <span class="text-sm text-gray-700">{{ $account->name }} ({{ $account->mobile_number }})</span>
                        </div>
                    </div>

                    {{-- Test Mode --}}
                    <div class="mt-4 bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="test_mode" value="1" x-model="testMode"
                                   class="rounded text-orange-500 focus:ring-orange-500">
                            <span class="text-sm font-medium text-orange-800"><i class="fas fa-flask mr-1"></i> Test Mode</span>
                            <span class="text-xs text-orange-600">(Send only to specific numbers)</span>
                        </label>
                        <div x-show="testMode" x-transition class="mt-3">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Test Phone Numbers (comma separated)</label>
                            <input type="text" name="test_numbers" x-model="testNumbers"
                                   class="w-full px-3 py-2 border border-orange-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none text-sm font-mono"
                                   placeholder="918903162114, 919876543210">
                            <p class="text-[10px] text-orange-500 mt-1">Enter numbers with country code (91). Campaign will ONLY send to these numbers instead of assembly voters.</p>
                        </div>
                    </div>
                </div>

                {{-- Assembly Selection --}}
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">Select Assemblies</h3>
                            <p class="text-xs text-gray-500 mt-1">Choose assemblies to send messages to their voters</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="text" x-model="searchQuery" placeholder="Search assembly..."
                                   class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs w-48 focus:ring-2 focus:ring-green-500 outline-none">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" x-model="selectAll" @change="toggleSelectAll()"
                                       class="rounded text-green-500 focus:ring-green-500" name="select_all" value="1">
                                <span class="text-xs font-medium text-gray-700">Select All (234)</span>
                            </label>
                        </div>
                    </div>

                    <div class="p-4 max-h-[500px] overflow-y-auto">
                        @foreach($assemblies as $zone => $districts)
                        <div class="mb-4" x-show="isZoneVisible('{{ addslashes($zone) }}')">
                            <div class="flex items-center gap-2 mb-2 cursor-pointer" @click="toggleZone('{{ addslashes($zone) }}')">
                                <i class="fas fa-chevron-right text-xs text-gray-400 transition-transform" :class="openZones.includes('{{ addslashes($zone) }}') && 'rotate-90'"></i>
                                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">{{ $zone }}</span>
                                <span class="text-xs text-gray-400">({{ collect($districts)->flatten(1)->count() }} assemblies)</span>
                            </div>
                            <div x-show="openZones.includes('{{ addslashes($zone) }}')" x-collapse>
                                @foreach($districts as $district => $distAssemblies)
                                <div class="ml-4 mb-3">
                                    <p class="text-xs font-semibold text-gray-500 mb-1.5"><i class="fas fa-map-marker-alt mr-1 text-purple-400"></i>{{ $district }}</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5 ml-4">
                                        @foreach($distAssemblies as $assembly)
                                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-green-50 cursor-pointer transition text-sm"
                                               x-show="isAssemblyVisible({{ $assembly['ac_no'] }}, '{{ addslashes($assembly['constituency']) }}')"
                                               :class="selectedAssemblies.includes({{ $assembly['ac_no'] }}) && 'bg-green-50 border border-green-200'">
                                            <input type="checkbox" name="assemblies[]" value="{{ $assembly['ac_no'] }}"
                                                   x-model.number="selectedAssemblies"
                                                   @change="updateCounts()"
                                                   class="rounded text-green-500 focus:ring-green-500">
                                            <span class="text-gray-700">
                                                <span class="font-medium">{{ $assembly['ac_no'] }}.</span>
                                                {{ $assembly['constituency'] }}
                                            </span>
                                            <span class="ml-auto text-xs text-gray-400">{{ number_format($assembly['total_voters'] ?? 0) }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right Side - Summary --}}
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 sticky top-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Campaign Summary</h3>

                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Selected Assemblies:</span>
                            <span class="font-bold text-gray-800" x-text="selectedAssemblies.length"></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Est. Total Voters:</span>
                            <span class="font-bold text-gray-800" x-text="formatNumber(estimatedVoters)"></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Est. With Mobile:</span>
                            <span class="font-bold text-green-600" x-text="loadingCounts ? 'Loading...' : formatNumber(votersWithMobile)"></span>
                        </div>

                        <hr class="my-3">

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Messages will only be sent to voters who have mobile numbers.
                        </div>

                        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 space-y-1">
                            <p><strong>Rate:</strong> ~80 msgs/sec</p>
                            <p><strong>Est. Time:</strong> <span x-text="estimatedTime()"></span></p>
                        </div>
                    </div>

                    <button type="submit" :disabled="!testMode && selectedAssemblies.length === 0"
                            class="w-full mt-4 px-4 py-3 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition shadow-lg shadow-green-500/20">
                        <i class="fas fa-bullhorn mr-1"></i>
                        <span x-text="testMode ? 'Create Test Campaign' : 'Create Campaign'"></span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function campaignCreator() {
    const allAssemblies = @json($allAssemblies);
    return {
        selectedAssemblies: [],
        selectAll: false,
        searchQuery: '',
        openZones: [],
        estimatedVoters: 0,
        votersWithMobile: 0,
        loadingCounts: false,
        testMode: false,
        testNumbers: '918903162114',

        toggleSelectAll() {
            if (this.selectAll) {
                this.selectedAssemblies = allAssemblies.map(a => a.ac_no);
            } else {
                this.selectedAssemblies = [];
            }
            this.updateCounts();
        },

        toggleZone(zone) {
            const idx = this.openZones.indexOf(zone);
            if (idx >= 0) this.openZones.splice(idx, 1);
            else this.openZones.push(zone);
        },

        isZoneVisible(zone) {
            if (!this.searchQuery) return true;
            return zone.toLowerCase().includes(this.searchQuery.toLowerCase());
        },

        isAssemblyVisible(acNo, name) {
            if (!this.searchQuery) return true;
            const q = this.searchQuery.toLowerCase();
            return name.toLowerCase().includes(q) || String(acNo).includes(q);
        },

        updateCounts() {
            let total = 0;
            this.selectedAssemblies.forEach(acNo => {
                const a = allAssemblies.find(x => x.ac_no === acNo);
                if (a) total += (a.total_voters || 0);
            });
            this.estimatedVoters = total;

            if (this.selectedAssemblies.length > 0) {
                this.loadingCounts = true;
                fetch('{{ route("campaigns.voter-count") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ assemblies: this.selectedAssemblies })
                })
                .then(r => r.json())
                .then(data => {
                    this.votersWithMobile = data.total_with_mobile || 0;
                    this.loadingCounts = false;
                })
                .catch(() => { this.loadingCounts = false; });
            } else {
                this.votersWithMobile = 0;
            }
        },

        estimatedTime() {
            if (!this.votersWithMobile) return 'N/A';
            const seconds = this.votersWithMobile / 80;
            if (seconds < 60) return Math.ceil(seconds) + ' seconds';
            if (seconds < 3600) return Math.ceil(seconds / 60) + ' minutes';
            return (seconds / 3600).toFixed(1) + ' hours';
        },

        formatNumber(n) {
            if (n >= 10000000) return (n / 10000000).toFixed(1) + ' Cr';
            if (n >= 100000) return (n / 100000).toFixed(1) + ' L';
            if (n >= 1000) return (n / 1000).toFixed(1) + ' K';
            return n.toLocaleString();
        }
    }
}
</script>
@endpush
@endsection
