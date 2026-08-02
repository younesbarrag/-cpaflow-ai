<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Offers" description="Manage your CPA offers and destination URLs.">
                <x-slot:actions>
                    <a href="{{ route('offers.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Create Offer
                    </a>
                </x-slot:actions>
            </x-page-header>

            {{-- Filters — only shown when account has offers --}}
            @if ($hasOffers)
                <form method="GET" action="{{ route('offers.index') }}" class="mb-6">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <x-search-input name="search" value="{{ request('search') }}" placeholder="Search offers by name..." />
                        </div>
                        <div class="sm:w-48">
                            <select name="status" class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">
                                <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition ease-in-out duration-150">
                            Filter
                        </button>
                    </div>
                </form>
            @endif

            {{-- Empty States --}}
            @if ($offers->isEmpty())
                @if (! $hasOffers)
                    {{-- First-use state: zero offers in account --}}
                    <div class="bg-white rounded-lg shadow-card p-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                        <h3 class="mt-3 text-sm font-semibold text-gray-900">No offers yet</h3>
                        <p class="mt-1 text-sm text-gray-500">Create your first CPA offer to start building campaigns and tracking links.</p>
                    </div>
                @else
                    {{-- Filtered-empty state: filters returned zero results --}}
                    <div class="bg-white rounded-lg shadow-card p-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <h3 class="mt-3 text-sm font-semibold text-gray-900">No offers match your filters</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or status filter.</p>
                        <div class="mt-4">
                            <a href="{{ route('offers.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Clear filters
                            </a>
                        </div>
                    </div>
                @endif
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block bg-white rounded-lg shadow-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payout</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @foreach ($offers as $offer)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $offer->name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate">
                                            {{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-emerald-700">
                                            ${{ number_format((float) $offer->payout, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <x-status-badge :status="$offer->status->value" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('offers.edit', $offer) }}" class="text-brand-600 hover:text-brand-900 font-medium">
                                                    Edit
                                                </a>
                                                @if ($offer->status->value !== 'archived')
                                                    <x-confirm-button
                                                        action="{{ route('offers.archive', $offer) }}"
                                                        confirmMessage="Are you sure you want to archive this offer?"
                                                        label="Archive"
                                                        class="text-red-600 hover:text-red-900 font-medium bg-transparent border-0 p-0 cursor-pointer"
                                                    />
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
                <div class="sm:hidden space-y-4">
                    @foreach ($offers as $offer)
                        <div class="bg-white rounded-lg shadow-card p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $offer->name }}</h3>
                                <x-status-badge :status="$offer->status->value" />
                            </div>
                            <p class="text-xs text-gray-500 mb-3 truncate">{{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-emerald-700">${{ number_format((float) $offer->payout, 2) }}</span>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('offers.edit', $offer) }}" class="text-sm text-brand-600 hover:text-brand-900 font-medium">Edit</a>
                                    @if ($offer->status->value !== 'archived')
                                        <x-confirm-button
                                            action="{{ route('offers.archive', $offer) }}"
                                            confirmMessage="Are you sure you want to archive this offer?"
                                            label="Archive"
                                            class="text-sm text-red-600 hover:text-red-900 font-medium bg-transparent border-0 p-0 cursor-pointer"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-6">
                    {{ $offers->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
