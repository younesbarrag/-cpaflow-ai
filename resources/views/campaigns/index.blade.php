<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Campaigns</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage your CPA campaigns and traffic sources.</p>
                </div>
                @if ($hasEligibleOffers)
                    <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create campaign
                    </a>
                @endif
            </div>

            {{-- Empty States --}}
            @if ($campaigns->isEmpty())
                @if (! $hasEligibleOffers)
                    {{-- No eligible offers --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50">
                            <svg class="h-7 w-7 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No campaigns yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">You need at least one active or draft offer before you can create a campaign.</p>
                        <div class="mt-6">
                            <a href="{{ route('offers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create an offer first
                            </a>
                        </div>
                    </div>
                @else
                    {{-- True empty state: has offers but no campaigns --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
                            <svg class="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No campaigns yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Create your first campaign to start driving traffic to your offers.</p>
                        <div class="mt-6">
                            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create your first campaign
                            </a>
                        </div>
                    </div>
                @endif
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block bg-white rounded-card shadow-card border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Offer</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Traffic source</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Budget</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($campaigns as $campaign)
                                    <tr class="hover:bg-gray-50 transition-colors duration-100">
                                        <td class="px-6 py-3">
                                            <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm font-medium text-gray-900 hover:text-brand-600 transition-colors duration-150">{{ $campaign->name }}</a>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm text-gray-500">{{ $campaign->offer->name ?? '—' }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm text-gray-500">{{ $campaign->traffic_source }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm font-medium text-gray-900">${{ number_format((float) $campaign->budget, 2) }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :status="$campaign->status->value" />
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('campaigns.show', $campaign) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                                                    View
                                                </a>
                                                @if (in_array($campaign->status->value, ['draft', 'suspended']))
                                                    <form method="POST" action="{{ route('campaigns.activate', $campaign) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150">
                                                            Activate
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($campaign->status->value === 'active')
                                                    <form method="POST" action="{{ route('campaigns.suspend', $campaign) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-colors duration-150">
                                                            Suspend
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden space-y-3">
                    @foreach ($campaigns as $campaign)
                        <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm font-semibold text-gray-900 hover:text-brand-600 transition-colors duration-150">{{ $campaign->name }}</a>
                                <x-status-badge :status="$campaign->status->value" />
                            </div>
                            <p class="text-xs text-gray-500 mb-1">{{ $campaign->offer->name ?? '—' }} &middot; {{ $campaign->traffic_source }}</p>
                            <div class="flex items-center justify-between mt-3">
                                <span class="text-sm font-medium text-gray-900">${{ number_format((float) $campaign->budget, 2) }}</span>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('campaigns.show', $campaign) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                                        View
                                    </a>
                                    @if (in_array($campaign->status->value, ['draft', 'suspended']))
                                        <form method="POST" action="{{ route('campaigns.activate', $campaign) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150">
                                                Activate
                                            </button>
                                        </form>
                                    @endif
                                    @if ($campaign->status->value === 'active')
                                        <form method="POST" action="{{ route('campaigns.suspend', $campaign) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition-colors duration-150">
                                                Suspend
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if ($campaigns->hasPages())
                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-sm text-gray-500">
                            Showing {{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }} of {{ $campaigns->total() }} campaigns
                        </p>
                        <div>{{ $campaigns->links() }}</div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
