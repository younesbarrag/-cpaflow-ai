<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="mb-4">
                <ol class="flex items-center gap-1.5 text-sm text-gray-500">
                    <li><a href="{{ route('offers.index') }}" class="hover:text-gray-700 transition-colors duration-150">Offers</a></li>
                    <li><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></li>
                    <li class="text-gray-900 font-medium">Create</li>
                </ol>
            </nav>

            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Create offer</h1>
                <p class="mt-1 text-sm text-gray-500">Add a new CPA offer to your account.</p>
            </div>

            {{-- Form Card --}}
            <div class="bg-white rounded-card shadow-card border border-gray-200 max-w-4xl">
                <form method="POST" action="{{ route('offers.store') }}">
                    @csrf
                    @include('offers.partials.form')
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
