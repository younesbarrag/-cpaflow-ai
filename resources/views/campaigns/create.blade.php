<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Create Campaign" description="Launch a new campaign for an existing offer." />

            <form method="POST" action="{{ route('campaigns.store') }}" class="max-w-4xl">
                @csrf
                @include('campaigns.partials.form', ['offers' => $offers])
            </form>
        </div>
    </div>
</x-app-layout>
