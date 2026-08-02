<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Campaigns" description="Manage your CPA campaigns and traffic sources.">
                <x-slot:actions>
                    @if ($hasEligibleOffers)
                        <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Create Campaign
                        </a>
                    @endif
                </x-slot:actions>
            </x-page-header>

            {{-- Empty State --}}
            @if ($campaigns->isEmpty())
                <div class="bg-white rounded-lg shadow-card p-8 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">No campaigns yet</h3>
                    @if ($hasEligibleOffers)
                        <p class="mt-1 text-sm text-gray-500">Create your first campaign to start driving traffic to your offers.</p>
                        <div class="mt-4">
                            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Create Your First Campaign
                            </a>
                        </div>
                    @else
                        <p class="mt-1 text-sm text-gray-500">You need at least one active offer before you can create a campaign.</p>
                        <div class="mt-4">
                            <a href="{{ route('offers.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Create an Offer First
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Offer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Traffic Source</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse ($campaigns as $campaign)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <a href="{{ route('campaigns.show', $campaign) }}" class="hover:text-brand-600">{{ $campaign->name }}</a>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $campaign->offer->name ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $campaign->traffic_source }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            ${{ number_format((float) $campaign->budget, 2) }}
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <x-status-badge :status="$campaign->status->value" />
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 flex items-center gap-2">
                                            <a href="{{ route('campaigns.show', $campaign) }}" class="text-brand-600 hover:text-brand-700 font-medium">View</a>
                                            @if ($campaign->status->value === 'active')
                                                <form method="POST" action="{{ route('campaigns.suspend', $campaign) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-yellow-600 hover:text-yellow-700 font-medium">Suspend</button>
                                                </form>
                                            @endif
                                            @if (in_array($campaign->status->value, ['draft', 'suspended']))
                                                <form method="POST" action="{{ route('campaigns.activate', $campaign) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-emerald-600 hover:text-emerald-700 font-medium">Activate</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No campaigns found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($campaigns->hasPages())
                        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50">
                            {{ $campaigns->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
