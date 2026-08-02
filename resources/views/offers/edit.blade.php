<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Edit Offer" description="Update offer details and payout configuration." />

            <form method="POST" action="{{ route('offers.update', $offer) }}" class="max-w-4xl">
                @csrf
                @method('PUT')
                @include('offers.partials.form', ['offer' => $offer])
            </form>
        </div>
    </div>
</x-app-layout>
