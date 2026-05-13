@extends('layouts.app')
@section('title', 'Voters')
@section('page-title', 'Voters Database')

@section('content')
<div class="space-y-5" x-data="voterPage()">

    {{-- Top Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Total Assemblies</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ $totalAssemblies }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-map-marked-alt text-purple-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">Total Voters</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($totalVotersAllStr) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-500 text-lg"></i>
                </div>
            </div>
        </div>
        @if($assembly)
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">{{ $assembly->constituency }} Voters</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($total) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-check text-green-500 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm" id="mobileStats">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase">With Mobile</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1" x-text="mobileCount !== null ? formatNum(mobileCount) : '...'">...</p>
                </div>
                <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i class="fas fa-mobile-alt text-orange-500 text-lg"></i>
                </div>
            </div>
        </div>
        @else
        <div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm col-span-2">
            <div class="flex items-center gap-3 text-gray-400">
                <i class="fas fa-arrow-left text-lg"></i>
                <p class="text-sm">Select an assembly to view voter details</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <form method="GET" action="{{ route('voters.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[250px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Select Assembly</label>
                <select name="assembly" onchange="this.form.submit()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm">
                    <option value="">-- Select Assembly --</option>
                    @foreach($grouped as $zone => $districts)
                        <optgroup label="{{ $zone }}">
                            @foreach($districts as $district => $asses)
                                @foreach($asses as $a)
                                    <option value="{{ $a->ac_no }}" {{ $selectedAc == $a->ac_no ? 'selected' : '' }}>
                                        {{ $a->ac_no }}. {{ $a->constituency }} ({{ $district }})
                                    </option>
                                @endforeach
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            @if($selectedAc)
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Search Voter</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400"><i class="fas fa-search text-xs"></i></span>
                    <input type="text" name="search" value="{{ $search }}"
                           class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm"
                           placeholder="Name, EPIC No, Mobile, Part No...">
                </div>
            </div>
            <input type="hidden" name="assembly" value="{{ $selectedAc }}">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Per Page</label>
                <select name="per_page" onchange="this.form.submit()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                    @foreach([25, 50, 100, 200] as $pp)
                        <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition">
                <i class="fas fa-search mr-1"></i> Search
            </button>
            @if($search)
            <a href="{{ route('voters.index', ['assembly' => $selectedAc]) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
            @endif
            @endif
        </form>
    </div>

    {{-- Voters Table --}}
    @if($selectedAc && $assembly)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-800">
                    <span class="text-green-600">AC {{ $assembly->ac_no }}</span> — {{ $assembly->constituency }}
                    <span class="text-sm text-gray-400 font-normal ml-2">{{ $assembly->district }}, {{ $assembly->zone }}</span>
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Table: <code class="bg-gray-100 px-1 rounded">{{ $assembly->table_name }}</code> •
                    Showing {{ number_format(($page - 1) * $perPage + 1) }}-{{ number_format(min($page * $perPage, $total)) }} of {{ number_format($total) }} voters
                    @if($search) • Filtered by: "<strong>{{ $search }}</strong>" @endif
                </p>
            </div>
            <div class="text-xs text-gray-400">
                <i class="fas fa-database mr-1"></i> MySQL (Read Only)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">#</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Voter Name</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">EPIC No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Mobile</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Age</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Gender</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Part No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Serial No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Relation</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">House No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">Polling Station</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-4 py-2.5 whitespace-nowrap">District</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($voters as $i => $voter)
                    <tr class="hover:bg-green-50/50 transition">
                        <td class="px-4 py-2.5 text-gray-400 text-xs">{{ ($page - 1) * $perPage + $i + 1 }}</td>
                        <td class="px-4 py-2.5 font-medium text-gray-800 whitespace-nowrap">{{ $voter->VOTER_NAME ?? '-' }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs text-gray-600">{{ $voter->EPIC_NO ?? '-' }}</td>
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            @if(!empty($voter->MOBILE_NUMBER))
                                <span class="inline-flex items-center gap-1 text-green-700 font-medium">
                                    <i class="fas fa-mobile-alt text-[10px] text-green-500"></i>{{ $voter->MOBILE_NUMBER }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">No mobile</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $voter->AGE ?? '-' }}</td>
                        <td class="px-4 py-2.5">
                            @if(($voter->GENDER ?? '') === 'Male')
                                <span class="text-blue-600 text-xs font-medium"><i class="fas fa-mars mr-0.5"></i>M</span>
                            @elseif(($voter->GENDER ?? '') === 'Female')
                                <span class="text-pink-600 text-xs font-medium"><i class="fas fa-venus mr-0.5"></i>F</span>
                            @else
                                <span class="text-purple-600 text-xs font-medium">{{ $voter->GENDER ?? '-' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $voter->PART_NO ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $voter->SERIAL_NO ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs whitespace-nowrap">{{ $voter->RELATION_TYPE ?? '' }} {{ $voter->RELATION_NAME ?? '' }}</td>
                        <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $voter->HOUSE_NO ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs max-w-[200px] truncate" title="{{ $voter->POLLING_STATION_NAME ?? '' }}">{{ Str::limit($voter->POLLING_STATION_NAME ?? '-', 40) }}</td>
                        <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $voter->DISTRICT ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="px-6 py-12 text-center text-gray-400">
                            @if($search)
                                <i class="fas fa-search text-3xl mb-2"></i>
                                <p>No voters found for "{{ $search }}"</p>
                            @else
                                <i class="fas fa-users text-3xl mb-2"></i>
                                <p>No voter data in this table</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($total > $perPage)
        <div class="px-6 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50">
            <p class="text-xs text-gray-500">
                Page {{ $page }} of {{ ceil($total / $perPage) }} ({{ number_format($total) }} voters)
            </p>
            <div class="flex gap-1">
                @if($page > 1)
                <a href="{{ route('voters.index', array_merge(request()->query(), ['page' => $page - 1])) }}"
                   class="px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs hover:bg-gray-50 transition">
                    <i class="fas fa-chevron-left mr-1"></i>Prev
                </a>
                @endif

                @php
                    $totalPages = ceil($total / $perPage);
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                @endphp

                @for($p = $start; $p <= $end; $p++)
                <a href="{{ route('voters.index', array_merge(request()->query(), ['page' => $p])) }}"
                   class="px-3 py-1.5 border rounded-lg text-xs transition {{ $p == $page ? 'bg-green-500 text-white border-green-500' : 'bg-white border-gray-300 hover:bg-gray-50' }}">
                    {{ $p }}
                </a>
                @endfor

                @if($page < $totalPages)
                <a href="{{ route('voters.index', array_merge(request()->query(), ['page' => $page + 1])) }}"
                   class="px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs hover:bg-gray-50 transition">
                    Next<i class="fas fa-chevron-right ml-1"></i>
                </a>
                @endif
            </div>
        </div>
        @endif
    </div>
    @elseif(!$selectedAc)
    {{-- No assembly selected - show all assemblies overview --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">All 234 Assemblies</h3>
            <p class="text-xs text-gray-500 mt-0.5">Select an assembly from the dropdown above or click on one below</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">AC No</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">Constituency</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">District</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">Zone</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">Table</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase px-6 py-2.5">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($assemblies as $a)
                    <tr class="hover:bg-green-50/50 transition">
                        <td class="px-6 py-2.5 font-bold text-green-600">{{ $a->ac_no }}</td>
                        <td class="px-6 py-2.5 font-medium text-gray-800">{{ $a->constituency }}</td>
                        <td class="px-6 py-2.5 text-gray-600">{{ $a->district }}</td>
                        <td class="px-6 py-2.5 text-gray-500 text-xs">{{ $a->zone }}</td>
                        <td class="px-6 py-2.5 font-mono text-xs text-gray-500">{{ $a->table_name }}</td>
                        <td class="px-6 py-2.5">
                            <a href="{{ route('voters.index', ['assembly' => $a->ac_no]) }}"
                               class="text-xs text-green-600 hover:underline font-medium">
                                <i class="fas fa-eye mr-1"></i>View Voters
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
function voterPage() {
    return {
        mobileCount: null,
        init() {
            @if($selectedAc)
            fetch('{{ route("voters.assembly-stats") }}?ac_no={{ $selectedAc }}', {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(data => { this.mobileCount = data.with_mobile || 0; })
            .catch(() => { this.mobileCount = 0; });
            @endif
        },
        formatNum(n) {
            if (n >= 10000000) return (n / 10000000).toFixed(1) + ' Cr';
            if (n >= 100000) return (n / 100000).toFixed(1) + ' L';
            return n.toLocaleString();
        }
    }
}
</script>
@endpush
@endsection
