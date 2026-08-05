@php
    $user = Auth::user();
    $initials = collect(explode(' ', $user->name))
        ->map(fn($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<nav x-data="{ mobileOpen: false }" class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                        <div class="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center shadow-sm">
                            <span class="text-white font-bold text-sm">C</span>
                        </div>
                        <span class="text-lg font-bold text-gray-900 tracking-tight">CPAFlow</span>
                    </a>
                </div>

                <div class="hidden space-x-1 sm:ms-8 sm:flex sm:items-center">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Overview') }}
                    </x-nav-link>
                    <x-nav-link :href="route('offers.index')" :active="request()->routeIs('offers.*')">
                        {{ __('Offers') }}
                    </x-nav-link>
                    <x-nav-link :href="route('campaigns.index')" :active="request()->routeIs('campaigns.*')">
                        {{ __('Campaigns') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2.5 px-1 py-1 rounded-lg hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                            <div class="w-9 h-9 rounded-full bg-brand-100 flex items-center justify-center">
                                <span class="text-sm font-semibold text-brand-700">{{ $initials }}</span>
                            </div>
                            <span class="hidden lg:block text-sm font-medium text-gray-700">{{ $user->name }}</span>
                            <svg class="hidden lg:block h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="flex items-center sm:hidden">
                <button @click="mobileOpen = ! mobileOpen" class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors duration-150" aria-label="Toggle navigation menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': mobileOpen, 'inline-flex': ! mobileOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! mobileOpen, 'inline-flex': mobileOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="sm:hidden border-t border-gray-200 bg-white"
    >
        <div class="pt-2 pb-3 space-y-1 px-4">
            <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }} transition-colors duration-150">
                {{ __('Overview') }}
            </a>
            <a href="{{ route('offers.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('offers.*') ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }} transition-colors duration-150">
                {{ __('Offers') }}
            </a>
            <a href="{{ route('campaigns.index') }}" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->routeIs('campaigns.*') ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }} transition-colors duration-150">
                {{ __('Campaigns') }}
            </a>
        </div>

        <div class="pt-3 pb-4 border-t border-gray-200 px-4">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-9 h-9 rounded-full bg-brand-100 flex items-center justify-center">
                    <span class="text-sm font-semibold text-brand-700">{{ $initials }}</span>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-800">{{ $user->name }}</div>
                    <div class="text-xs text-gray-500">{{ $user->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150">
                    {{ __('Profile') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors duration-150">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
