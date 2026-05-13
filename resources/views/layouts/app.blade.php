<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bulk WhatsApp Campaign')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link.active { background: rgba(37, 211, 102, 0.15); color: #25D366; border-left: 3px solid #25D366; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: true }">
        {{-- Sidebar --}}
        <aside class="bg-gray-900 text-white transition-all duration-300"
               :class="sidebarOpen ? 'w-64' : 'w-20'"
               style="min-height: 100vh;">
            <div class="p-4 border-b border-gray-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fab fa-whatsapp text-white text-xl"></i>
                    </div>
                    <div x-show="sidebarOpen" x-cloak>
                        <h1 class="font-bold text-sm leading-tight">Bulk Campaign</h1>
                        <p class="text-xs text-gray-400">Super Admin Panel</p>
                    </div>
                </div>
            </div>

            <nav class="mt-4 px-2 space-y-1">
                <a href="{{ route('dashboard') }}"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition hover:bg-gray-800 {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt w-5 text-center"></i>
                    <span x-show="sidebarOpen">Dashboard</span>
                </a>
                <a href="{{ route('accounts.index') }}"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition hover:bg-gray-800 {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                    <i class="fas fa-users-cog w-5 text-center"></i>
                    <span x-show="sidebarOpen">WhatsApp Accounts</span>
                </a>
                <a href="{{ route('voters.index') }}"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition hover:bg-gray-800 {{ request()->routeIs('voters.*') ? 'active' : '' }}">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span x-show="sidebarOpen">Voters</span>
                </a>
                <a href="{{ route('campaigns.index') }}"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition hover:bg-gray-800 {{ request()->routeIs('campaigns.*') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn w-5 text-center"></i>
                    <span x-show="sidebarOpen">Campaigns</span>
                </a>
            </nav>

            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-700" :style="sidebarOpen ? 'width:16rem' : 'width:5rem'">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user text-xs"></i>
                    </div>
                    <div x-show="sidebarOpen" class="flex-1 min-w-0">
                        <p class="text-xs font-medium truncate">{{ Auth::guard('admin')->user()->name ?? 'Admin' }}</p>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-red-400 hover:text-red-300">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            {{-- Top Bar --}}
            <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Dashboard')</h2>
                </div>
                <div class="flex items-center gap-3">
                    @yield('header-actions')
                </div>
            </header>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 flex items-center gap-3"
                     x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span class="text-green-800 text-sm">{{ session('success') }}</span>
                    <button @click="show = false" class="ml-auto text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
                </div>
            @endif
            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3 flex items-center gap-3"
                     x-data="{ show: true }" x-show="show">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span class="text-red-800 text-sm">{{ session('error') }}</span>
                    <button @click="show = false" class="ml-auto text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                </div>
            @endif
            @if(session('info'))
                <div class="mx-6 mt-4 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 flex items-center gap-3"
                     x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
                    <i class="fas fa-info-circle text-blue-500"></i>
                    <span class="text-blue-800 text-sm">{{ session('info') }}</span>
                    <button @click="show = false" class="ml-auto text-blue-400 hover:text-blue-600"><i class="fas fa-times"></i></button>
                </div>
            @endif

            {{-- Page Content --}}
            <main class="flex-1 overflow-auto p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
