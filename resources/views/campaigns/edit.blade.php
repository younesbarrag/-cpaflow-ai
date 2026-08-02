<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header title="Edit Campaign" description="Update campaign details and budget." />

            <form method="POST" action="{{ route('campaigns.update', $campaign) }}" class="max-w-4xl">
                @csrf
                @method('PUT')
                @include('campaigns.partials.form', ['campaign' => $campaign, 'offers' => $offers])
            </form>
        </div>
    </div>
</x-app-layout>
