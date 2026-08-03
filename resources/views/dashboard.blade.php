<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header
                title="Welcome back, {{ strtok(auth()->user()->name, ' ') }}"
                description="Manage your CPA offers, campaigns, and tracking links from one place."
            />

            {{-- Quick Actions --}}
            <div class="mb-6 flex flex-wrap items-center gap-3">
                <a href="{{ route('offers.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create Offer
                </a>

                @if ($hasEligibleOffers)
                    <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create Campaign
                    </a>
                @else
                    <span class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md font-semibold text-xs text-gray-400 uppercase tracking-widest cursor-not-allowed" title="Create an offer before launching a campaign">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create Campaign
                    </span>
                @endif
            </div>

            {{-- Counts --}}
            <div class="mb-8 flex items-center gap-6 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-900">{{ $statistics['offer_count'] }}</span>
                    <span>{{ Str::plural('Offer', $statistics['offer_count']) }}</span>
                </div>
                <div class="w-px h-4 bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-900">{{ $statistics['campaign_count'] }}</span>
                    <span>{{ Str::plural('Campaign', $statistics['campaign_count']) }}</span>
                </div>
            </div>

            {{-- Statistics Cards --}}
            <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div class="bg-white rounded-lg shadow-card p-4">
                    <div class="text-sm text-gray-500">Clicks</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['click_count']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-card p-4">
                    <div class="text-sm text-gray-500">Conversions</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($statistics['conversion_count']) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-card p-4">
                    <div class="text-sm text-gray-500">Revenue</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">${{ number_format((float) $statistics['revenue'], 2) }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">Approved only</div>
                </div>
                <div class="bg-white rounded-lg shadow-card p-4">
                    <div class="text-sm text-gray-500">Expenses</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">${{ number_format((float) $statistics['total_expenses'], 2) }}</div>
                </div>
                <div class="bg-white rounded-lg shadow-card p-4">
                    <div class="text-sm text-gray-500">Profit</div>
                    <div class="mt-1 text-2xl font-semibold {{ (float) $statistics['profit'] < 0 ? 'text-red-600' : 'text-gray-900' }}">
                        ${{ number_format((float) $statistics['profit'], 2) }}
                    </div>
                </div>
            </div>

            {{-- Empty State --}}
            @if ($statistics['offer_count'] === 0 && $statistics['campaign_count'] === 0)
                <div class="bg-white rounded-lg shadow-card p-8 text-center mb-8">
                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">Get started with CPAFlow</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Create an offer, launch a campaign, and generate tracking links — all from one place.
                    </p>
                    <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-400">
                        <span class="font-medium text-gray-600">Create Offer</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <span class="font-medium text-gray-600">Create Campaign</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        <span class="font-medium text-gray-600">Generate Links</span>
                    </div>
                </div>
            @endif

            {{-- Recent Offers --}}
            @if ($recentOffers->count() > 0)
                <div class="bg-white rounded-lg shadow-card mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900">Recent Offers</h2>
                        <a href="{{ route('offers.index') }}" class="text-sm text-brand-600 hover:text-brand-700 font-medium">View all</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payout</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($recentOffers as $offer)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <a href="{{ route('offers.edit', $offer) }}" class="hover:text-brand-600">{{ $offer->name }}</a>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                                            {{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            ${{ number_format((float) $offer->payout, 2) }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-badge :status="$offer->status->value" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Recent Campaigns --}}
            @if ($recentCampaigns->count() > 0)
                <div class="bg-white rounded-lg shadow-card">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-gray-900">Recent Campaigns</h2>
                        <a href="{{ route('campaigns.index') }}" class="text-sm text-brand-600 hover:text-brand-700 font-medium">View all</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Offer</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                    <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($recentCampaigns as $campaign)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <a href="{{ route('campaigns.show', $campaign) }}" class="hover:text-brand-600">{{ $campaign->name }}</a>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $campaign->offer->name ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            ${{ number_format((float) $campaign->budget, 2) }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-badge :status="$campaign->status->value" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
