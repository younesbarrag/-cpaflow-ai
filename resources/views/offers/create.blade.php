<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Create Offer" description="Add a new CPA offer to your account." />

            <form method="POST" action="{{ route('offers.store') }}" class="max-w-4xl">
                @csrf
                @include('offers.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
