<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="mb-4">
                <ol class="flex items-center gap-1.5 text-sm text-gray-500">
                    <li><a href="{{ route('campaigns.index') }}" class="hover:text-gray-700 transition-colors duration-150">Campaigns</a></li>
                    <li><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></li>
                    <li><a href="{{ route('campaigns.show', $campaign) }}" class="hover:text-gray-700 transition-colors duration-150">{{ $campaign->name }}</a></li>
                    <li><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></li>
                    <li class="text-gray-900 font-medium">Edit</li>
                </ol>
            </nav>

            {{-- Header --}}
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900">Edit campaign</h1>
                        <x-status-badge :status="$campaign->status->value" />
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ $campaign->name }}</p>
                </div>
            </div>

            {{-- Form Card --}}
            <div class="bg-white rounded-card shadow-card border border-gray-200 max-w-4xl">
                <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
                    @csrf
                    @method('PUT')
                    @include('campaigns.partials.form', ['campaign' => $campaign, 'offers' => $offers])
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
