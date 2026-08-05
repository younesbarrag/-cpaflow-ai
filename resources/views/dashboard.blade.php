<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- A. Dashboard Header --}}
            <div class="mb-8">
                <p class="text-sm font-medium text-brand-600 mb-1">Dashboard</p>
                <h1 class="text-2xl font-bold text-gray-900">Welcome back, {{ strtok(auth()->user()->name, ' ') }}</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your offers, campaigns, tracking activity and performance from one place.</p>
            </div>

            {{-- B. Quick Actions --}}
            <div class="mb-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('offers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create offer
                </a>

                @if ($hasEligibleOffers)
                    <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create campaign
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed" title="Create an offer before launching a campaign">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create campaign
                    </span>
                @endif
            </div>

            {{-- C. Inventory Overview --}}
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Inventory</h2>
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex items-center gap-3 bg-white rounded-card shadow-card px-4 py-3">
                        <div class="flex-shrink-0 p-2 bg-brand-50 rounded-lg">
                            <svg class="w-4 h-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($statistics['offer_count']) }}</p>
                            <p class="text-xs text-gray-500">{{ Str::plural('Offer', $statistics['offer_count']) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white rounded-card shadow-card px-4 py-3">
                        <div class="flex-shrink-0 p-2 bg-violet-50 rounded-lg">
                            <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($statistics['campaign_count']) }}</p>
                            <p class="text-xs text-gray-500">{{ Str::plural('Campaign', $statistics['campaign_count']) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-white rounded-card shadow-card px-4 py-3">
                        <div class="flex-shrink-0 p-2 bg-emerald-50 rounded-lg">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-900">{{ number_format($statistics['active_campaign_count']) }}</p>
                            <p class="text-xs text-gray-500">Active {{ Str::plural('Campaign', $statistics['active_campaign_count']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- D. Activity Period --}}
            <div class="mb-8">
                <form method="GET" action="{{ route('dashboard') }}" id="period-filter-form">
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label for="period" class="block text-sm font-medium text-gray-700 mb-1.5">Activity period</label>
                            <select name="period" id="period" class="block w-full sm:w-auto rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">All time</option>
                                <option value="today" {{ ($activePeriod ?? '') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="last_7_days" {{ ($activePeriod ?? '') === 'last_7_days' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="last_30_days" {{ ($activePeriod ?? '') === 'last_30_days' ? 'selected' : '' }}>Last 30 days</option>
                                <option value="this_month" {{ ($activePeriod ?? '') === 'this_month' ? 'selected' : '' }}>This month</option>
                                <option value="custom" {{ ($activePeriod ?? '') === 'custom' ? 'selected' : '' }}>Custom range</option>
                            </select>
                        </div>

                        @if (($activePeriod ?? '') === 'custom')
                            <div>
                                <label for="from" class="block text-sm font-medium text-gray-700 mb-1.5">From</label>
                                <input type="date" name="from" id="from" value="{{ $activeFrom ?? '' }}" class="block rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            </div>
                            <div>
                                <label for="to" class="block text-sm font-medium text-gray-700 mb-1.5">To</label>
                                <input type="date" name="to" id="to" value="{{ $activeTo ?? '' }}" class="block rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                            </div>
                            <button type="submit" class="px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                Apply
                            </button>
                        @else
                            <button type="submit" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                Filter
                            </button>
                        @endif

                        @if (($activePeriod ?? '') !== '' && ($activePeriod ?? '') !== null)
                            <a href="{{ route('dashboard') }}" class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors duration-150">
                                Clear
                            </a>
                        @endif
                    </div>
                </form>
                <p class="mt-2 text-xs text-gray-400">Inventory totals are all-time. Performance metrics follow the selected activity period.</p>
            </div>

            {{-- E. Performance Metrics --}}
            <div class="mb-10">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Performance</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <x-metric-card
                    label="Clicks"
                    value="{{ number_format($statistics['click_count']) }}"
                    color="indigo"
                    :style="'animation-delay: 0ms'"
                >
                    <x-slot:icon>
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                        </svg>
                    </x-slot:icon>
                </x-metric-card>

                <x-metric-card
                    label="Conversions"
                    value="{{ number_format($statistics['conversion_count']) }}"
                    color="violet"
                    :style="'animation-delay: 50ms'"
                >
                    <x-slot:icon>
                        <svg class="w-5 h-5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </x-slot:icon>
                </x-metric-card>

                <x-metric-card
                    label="Revenue"
                    value="${{ number_format((float) $statistics['revenue'], 2) }}"
                    color="emerald"
                    helperText="Approved only"
                    :style="'animation-delay: 100ms'"
                >
                    <x-slot:icon>
                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </x-slot:icon>
                </x-metric-card>

                <x-metric-card
                    label="Expenses"
                    value="${{ number_format((float) $statistics['total_expenses'], 2) }}"
                    color="amber"
                    :style="'animation-delay: 150ms'"
                >
                    <x-slot:icon>
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </x-slot:icon>
                </x-metric-card>

                <x-metric-card
                    label="Profit"
                    value="${{ number_format((float) $statistics['profit'], 2) }}"
                    :color="(float) $statistics['profit'] < 0 ? 'red' : 'emerald'"
                    :style="'animation-delay: 200ms'"
                >
                    <x-slot:icon>
                        @if ((float) $statistics['profit'] < 0)
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        @endif
                    </x-slot:icon>
                </x-metric-card>
                </div>
            </div>

            {{-- F. Empty / Onboarding State --}}
            @if ($statistics['offer_count'] === 0 && $statistics['campaign_count'] === 0)
                <div class="bg-white rounded-card shadow-card p-8 mb-8">
                    <div class="text-center max-w-md mx-auto">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
                            <svg class="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">Get started with CPAFlow</h3>
                        <p class="mt-2 text-sm text-gray-500">Launch your first affiliate campaign in three steps.</p>
                    </div>

                    <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="relative flex items-start gap-3 p-4 rounded-card bg-brand-50 border border-brand-100">
                            <div class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-bold">
                                1
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Create an offer</p>
                                <p class="mt-0.5 text-xs text-gray-500">Define your landing page and payout.</p>
                            </div>
                        </div>

                        <div class="relative flex items-start gap-3 p-4 rounded-card bg-gray-50 border border-gray-200">
                            <div class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-gray-300 text-white text-sm font-bold">
                                2
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Launch a campaign</p>
                                <p class="mt-0.5 text-xs text-gray-500">Set budget and traffic source.</p>
                            </div>
                        </div>

                        <div class="relative flex items-start gap-3 p-4 rounded-card bg-gray-50 border border-gray-200">
                            <div class="flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-gray-300 text-white text-sm font-bold">
                                3
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Generate tracking link</p>
                                <p class="mt-0.5 text-xs text-gray-500">Start driving traffic.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 text-center">
                        <a href="{{ route('offers.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Create your first offer
                        </a>
                    </div>
                </div>
            @endif

            {{-- F. Recent Content --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Recent Offers --}}
                @if ($recentOffers->count() > 0)
                    <div class="bg-white rounded-card shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Recent offers</h2>
                            <a href="{{ route('offers.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors duration-150">View all</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @foreach ($recentOffers as $offer)
                                <a href="{{ route('offers.edit', $offer) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors duration-150">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $offer->name }}</p>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}</p>
                                    </div>
                                    <div class="ml-4 flex items-center gap-3 flex-shrink-0">
                                        <span class="text-sm font-semibold text-gray-900">${{ number_format((float) $offer->payout, 2) }}</span>
                                        <x-status-badge :status="$offer->status->value" />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Recent Campaigns --}}
                @if ($recentCampaigns->count() > 0)
                    <div class="bg-white rounded-card shadow-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-900">Recent campaigns</h2>
                            <a href="{{ route('campaigns.index') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors duration-150">View all</a>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @foreach ($recentCampaigns as $campaign)
                                <a href="{{ route('campaigns.show', $campaign) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors duration-150">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $campaign->name }}</p>
                                        <p class="text-xs text-gray-500 truncate mt-0.5">{{ $campaign->traffic_source }} &middot; {{ $campaign->offer->name ?? '—' }}</p>
                                    </div>
                                    <div class="ml-4 flex items-center gap-3 flex-shrink-0">
                                        <span class="text-sm font-semibold text-gray-900">${{ number_format((float) $campaign->budget, 2) }}</span>
                                        <x-status-badge :status="$campaign->status->value" />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('period')?.addEventListener('change', function() {
            if (this.value !== 'custom') {
                document.getElementById('period-filter-form').submit();
            }
        });
    </script>
    @endpush
</x-app-layout>
