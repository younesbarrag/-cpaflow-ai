@php
    $isEdit = isset($offer) && $offer !== null;
@endphp

<div class="space-y-5">
    {{-- Name --}}
    <x-form-group label="Offer Name" for="name" :required="true" :error="$errors->get('name') ?? null">
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $offer->name ?? '') }}"
            class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
            placeholder="e.g., Nutra COD – US Tier 1"
            required
            autofocus
        />
    </x-form-group>

    {{-- Destination URL --}}
    <x-form-group label="Destination URL" for="destination_url" :required="true" :error="$errors->get('destination_url') ?? null">
        <input
            type="url"
            name="destination_url"
            id="destination_url"
            value="{{ old('destination_url', $offer->destination_url ?? '') }}"
            class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
            placeholder="https://example.com/landing-page"
            required
        />
    </x-form-group>

    {{-- Payout + Status row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-form-group label="Payout ($)" for="payout" :required="true" :error="$errors->get('payout') ?? null">
            <input
                type="number"
                name="payout"
                id="payout"
                value="{{ old('payout', $offer->payout ?? '0.00') }}"
                step="0.01"
                min="0"
                class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                required
            />
        </x-form-group>

        <x-form-group label="Status" for="status" :required="true" :error="$errors->get('status') ?? null">
            <select
                name="status"
                id="status"
                class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                required
            >
                @foreach (\App\Enums\OfferStatus::cases() as $status)
                    @if ($status === \App\Enums\OfferStatus::Archived)
                        @continue
                    @endif
                    <option value="{{ $status->value }}" {{ old('status', $offer->status->value ?? 'draft') === $status->value ? 'selected' : '' }}>
                        {{ ucfirst($status->value) }}
                    </option>
                @endforeach
            </select>
        </x-form-group>
    </div>

    {{-- Description --}}
    <x-form-group label="Description" for="description" :error="$errors->get('description') ?? null">
        <textarea
            name="description"
            id="description"
            rows="3"
            class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
            placeholder="Optional — internal notes about this offer"
        >{{ old('description', $offer->description ?? '') }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Optional</p>
    </x-form-group>

    {{-- Action bar --}}
    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
        <a href="{{ route('offers.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
            {{ $isEdit ? 'Update Offer' : 'Create Offer' }}
        </button>
    </div>
</div>
