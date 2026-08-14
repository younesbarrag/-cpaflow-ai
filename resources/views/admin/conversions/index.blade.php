<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Conversion Review</h1>
                <p class="mt-1 text-sm text-gray-500">Review and approve or reject pending conversions from affiliate postbacks.</p>
            </div>

            {{-- Empty State --}}
            @if ($conversions->isEmpty())
                <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50">
                        <svg class="h-7 w-7 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-gray-900">No pending conversions</h3>
                    <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">All conversions have been reviewed. New postback conversions will appear here.</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block bg-white rounded-card shadow-card border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Campaign</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Offer</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">External ID</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Revenue</th>
                                    <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($conversions as $conversion)
                                    <tr class="hover:bg-gray-50 transition-colors duration-100" id="conversion-{{ $conversion->id }}">
                                        <td class="px-6 py-3">
                                            <a href="{{ route('campaigns.show', $conversion->campaign) }}" class="text-sm font-medium text-gray-900 hover:text-brand-600 transition-colors duration-150">{{ $conversion->campaign->name }}</a>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="text-sm text-gray-500">{{ $conversion->campaign->offer->name ?? '—' }}</span>
                                        </td>
                                        <td class="px-6 py-3 text-sm text-gray-700">{{ $conversion->source ?? '—' }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-500 font-mono">{{ $conversion->external_id }}</td>
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">${{ number_format((float) $conversion->revenue, 2) }}</td>
                                        <td class="px-6 py-3 text-sm text-gray-500">{{ $conversion->converted_at->format('M j, Y g:i A') }}</td>
                                        <td class="px-6 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1" x-data="{ processing: false }">
                                                <button
                                                    type="button"
                                                    :disabled="processing"
                                                    @click="
                                                        processing = true;
                                                        fetch('{{ route('api.v1.campaigns.conversions.approve', [$conversion->campaign, $conversion]) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                                'Accept': 'application/json'
                                                            }
                                                        })
                                                        .then(r => {
                                                            if (r.ok) return r.json();
                                                            throw r;
                                                        })
                                                        .then(() => { location.reload(); })
                                                        .catch(async (e) => {
                                                            const body = await e.json().catch(() => ({}));
                                                            alert(body.message || 'Failed to approve conversion.');
                                                            processing = false;
                                                        });
                                                    "
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                    Approve
                                                </button>
                                                <button
                                                    type="button"
                                                    :disabled="processing"
                                                    @click="
                                                        processing = true;
                                                        fetch('{{ route('api.v1.campaigns.conversions.reject', [$conversion->campaign, $conversion]) }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                                'Accept': 'application/json'
                                                            }
                                                        })
                                                        .then(r => {
                                                            if (r.ok) return r.json();
                                                            throw r;
                                                        })
                                                        .then(() => { location.reload(); })
                                                        .catch(async (e) => {
                                                            const body = await e.json().catch(() => ({}));
                                                            alert(body.message || 'Failed to reject conversion.');
                                                            processing = false;
                                                        });
                                                    "
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                                >
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </button>
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
                    @foreach ($conversions as $conversion)
                        <div class="bg-white rounded-card shadow-card border border-gray-200 p-4" id="conversion-mobile-{{ $conversion->id }}">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <a href="{{ route('campaigns.show', $conversion->campaign) }}" class="text-sm font-semibold text-gray-900 hover:text-brand-600 transition-colors duration-150">{{ $conversion->campaign->name }}</a>
                                <span class="text-sm font-medium text-gray-900">${{ number_format((float) $conversion->revenue, 2) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mb-1">{{ $conversion->campaign->offer->name ?? '—' }} &middot; {{ $conversion->source ?? '—' }}</p>
                            <p class="text-xs text-gray-400 font-mono mb-3">{{ $conversion->external_id }}</p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">{{ $conversion->converted_at->format('M j, Y g:i A') }}</span>
                                <div class="flex items-center gap-1" x-data="{ processing: false }">
                                    <button
                                        type="button"
                                        :disabled="processing"
                                        @click="
                                            processing = true;
                                            fetch('{{ route('api.v1.campaigns.conversions.approve', [$conversion->campaign, $conversion]) }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                    'Accept': 'application/json'
                                                }
                                            })
                                            .then(r => {
                                                if (r.ok) return r.json();
                                                throw r;
                                            })
                                            .then(() => { location.reload(); })
                                            .catch(async (e) => {
                                                const body = await e.json().catch(() => ({}));
                                                alert(body.message || 'Failed to approve conversion.');
                                                processing = false;
                                            });
                                        "
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="processing"
                                        @click="
                                            processing = true;
                                            fetch('{{ route('api.v1.campaigns.conversions.reject', [$conversion->campaign, $conversion]) }}', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                    'Accept': 'application/json'
                                                }
                                            })
                                            .then(r => {
                                                if (r.ok) return r.json();
                                                throw r;
                                            })
                                            .then(() => { location.reload(); })
                                            .catch(async (e) => {
                                                const body = await e.json().catch(() => ({}));
                                                alert(body.message || 'Failed to reject conversion.');
                                                processing = false;
                                            });
                                        "
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                    >
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if ($conversions->hasPages())
                    <div class="mt-6 flex items-center justify-between">
                        <p class="text-sm text-gray-500">
                            Showing {{ $conversions->firstItem() }}–{{ $conversions->lastItem() }} of {{ $conversions->total() }} pending conversions
                        </p>
                        <div>{{ $conversions->links() }}</div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</x-app-layout>
