<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Offers</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage your CPA offers, payouts and destination URLs.</p>
                </div>
                <a href="{{ route('offers.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create offer
                </a>
            </div>

            {{-- Search + Filters --}}
            @if ($hasOffers)
                <form method="GET" action="{{ route('offers.index') }}" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_11rem_auto] sm:items-center">
                    <div class="min-w-0">
                        <x-search-input name="search" value="{{ request('search') }}" placeholder="Search offers by name..." />
                    </div>
                    <div>
                        <select name="status" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All statuses</option>
                            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <button type="submit" class="whitespace-nowrap px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                        Filter
                    </button>
                </form>
            @endif

            {{-- Empty States --}}
            @if ($offers->isEmpty())
                @if (! $hasOffers)
                    {{-- True empty state: no offers at all --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
                            <svg class="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No offers yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Create your first CPA offer to define a destination URL and payout before launching a campaign.</p>
                        <div class="mt-6">
                            <a href="{{ route('offers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Create your first offer
                            </a>
                        </div>
                    </div>
                @else
                    {{-- Filtered-zero state: offers exist but search/filter returned nothing --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No offers found</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Try changing your search or status filter.</p>
                        <div class="mt-6">
                            <a href="{{ route('offers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                Clear filters
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
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Destination</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payout</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($offers as $offer)
                                    <tr class="hover:bg-gray-50 transition-colors duration-100">
                                        <td class="px-6 py-3">
                                            <span class="text-sm font-medium text-gray-900">{{ $offer->name }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm text-gray-500 max-w-xs truncate block" title="{{ $offer->destination_url }}">{{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm font-semibold text-emerald-700">${{ number_format((float) $offer->payout, 2) }}</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <x-status-badge :status="$offer->status->value" />
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('offers.edit', $offer) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                    Edit
                                                </a>
                                                @if ($offer->status->value !== 'archived')
                                                    <x-confirm-button
                                                        action="{{ route('offers.archive', $offer) }}"
                                                        confirmMessage="This offer will no longer be available for new campaign activity."
                                                        label="Archive"
                                                        modalTitle="Archive offer?"
                                                        confirmLabel="Archive offer"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150"
                                                    />
                                                @else
                                                    <x-confirm-button
                                                        action="{{ route('offers.restore', $offer) }}"
                                                        confirmMessage="This offer will be restored to Draft status."
                                                        label="Restore"
                                                        modalTitle="Restore offer?"
                                                        confirmLabel="Restore offer"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150"
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
                <div class="sm:hidden space-y-3">
                    @foreach ($offers as $offer)
                        <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $offer->name }}</h3>
                                <x-status-badge :status="$offer->status->value" />
                            </div>
                            <p class="text-xs text-gray-500 mb-3 truncate" title="{{ $offer->destination_url }}">{{ parse_url($offer->destination_url, PHP_URL_HOST) ?? $offer->destination_url }}</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-emerald-700">${{ number_format((float) $offer->payout, 2) }}</span>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('offers.edit', $offer) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors duration-150">
                                        Edit
                                    </a>
                                    @if ($offer->status->value !== 'archived')
                                        <x-confirm-button
                                            action="{{ route('offers.archive', $offer) }}"
                                            confirmMessage="This offer will no longer be available for new campaign activity."
                                            label="Archive"
                                            modalTitle="Archive offer?"
                                            confirmLabel="Archive offer"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150"
                                        />
                                    @else
                                        <x-confirm-button
                                            action="{{ route('offers.restore', $offer) }}"
                                            confirmMessage="This offer will be restored to Draft status."
                                            label="Restore"
                                            modalTitle="Restore offer?"
                                            confirmLabel="Restore offer"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if ($offers->hasPages())
                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-sm text-gray-500">
                            Showing {{ $offers->firstItem() }}–{{ $offers->lastItem() }} of {{ $offers->total() }} offers
                        </p>
                        <div>{{ $offers->links() }}</div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
