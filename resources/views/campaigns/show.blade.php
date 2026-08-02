<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="{{ $campaign->name }}">
                <x-slot:actions>
                    <a href="{{ route('campaigns.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Back to Campaigns
                    </a>
                    @if (in_array($campaign->status->value, ['draft', 'suspended']))
                        <form method="POST" action="{{ route('campaigns.activate', $campaign) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Activate
                            </button>
                        </form>
                    @endif
                    @if ($campaign->status->value === 'active')
                        <form method="POST" action="{{ route('campaigns.suspend', $campaign) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-600 focus:bg-yellow-600 active:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Suspend
                            </button>
                        </form>
                    @endif
                </x-slot:actions>
            </x-page-header>

            {{-- Tracking Link (prominent) --}}
            @if ($campaign->trackingLinks->isNotEmpty())
                <div class="bg-white rounded-lg shadow-card p-5 mb-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Tracking Link</h3>
                    @foreach ($campaign->trackingLinks as $link)
                        <div class="mb-2 last:mb-0">
                            <x-tracking-url :url="route('tracking.redirect', $link->code)" :code="$link->code" />
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg shadow-card p-5 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">Tracking Link</h3>
                            <p class="mt-1 text-sm text-gray-500">Generate a unique tracking link to start driving traffic.</p>
                        </div>
                        @if ($campaign->status->value === 'active')
                            <form method="POST" action="{{ route('campaigns.tracking-links.store', $campaign) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    Generate Link
                                </button>
                            </form>
                        @else
                            <span class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-md font-semibold text-xs text-gray-400 uppercase tracking-widest cursor-not-allowed" title="Activate the campaign to generate a tracking link">
                                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                                Generate Link
                            </span>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Campaign Details --}}
            <div class="bg-white rounded-lg shadow-card p-5 mb-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Campaign Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Status</span>
                        <div class="mt-1">
                            <x-status-badge :status="$campaign->status->value" />
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">Linked Offer</span>
                        <div class="mt-1 font-medium text-gray-900">
                            {{ $campaign->offer->name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">Traffic Source</span>
                        <div class="mt-1 font-medium text-gray-900">
                            {{ $campaign->traffic_source }}
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500">Budget</span>
                        <div class="mt-1 font-medium text-gray-900">${{ number_format((float) $campaign->budget, 2) }}</div>
                    </div>
                    <div>
                        <span class="text-gray-500">Created</span>
                        <div class="mt-1 font-medium text-gray-900">{{ $campaign->created_at->format('M j, Y') }}</div>
                    </div>
                </div>
            </div>

            {{-- Destination URL --}}
            @if ($campaign->offer && $campaign->offer->destination_url)
                <div class="bg-white rounded-lg shadow-card p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Landing Page</h3>
                    <p class="text-sm text-gray-500 mb-3">The destination URL visitors will be directed to after clicking the tracking link.</p>
                    <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-md border border-gray-200">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <code class="text-sm text-gray-700 break-all">{{ $campaign->offer->destination_url }}</code>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
