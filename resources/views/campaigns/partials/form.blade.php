@php
    $isEdit = isset($campaign) && $campaign !== null;
@endphp

<div class="space-y-5">
    {{-- Offer --}}
    <x-form-group label="Offer" for="offer_id" :required="true" :error="$errors->get('offer_id') ?? null">
        @if ($isEdit)
            <input type="hidden" name="offer_id" value="{{ $campaign->offer_id }}" />
            <div class="block w-full border border-gray-200 rounded-md shadow-sm bg-gray-50 px-3 py-2 text-sm text-gray-700">
                {{ $campaign->offer->name ?? 'Unknown Offer' }}
            </div>
            <p class="mt-1 text-xs text-gray-500">Offer cannot be changed after creation.</p>
        @else
            <select
                name="offer_id"
                id="offer_id"
                class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                required
            >
                <option value="">Select an offer...</option>
                @foreach ($offers as $offer)
                    <option value="{{ $offer->id }}" {{ old('offer_id') == $offer->id ? 'selected' : '' }}>
                        {{ $offer->name }} — ${{ number_format((float) $offer->payout, 2) }}
                    </option>
                @endforeach
            </select>
        @endif
    </x-form-group>

    {{-- Name --}}
    <x-form-group label="Campaign Name" for="name" :required="true" :error="$errors->get('name') ?? null">
        <input
            type="text"
            name="name"
            id="name"
            value="{{ old('name', $campaign->name ?? '') }}"
            class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
            required
            autofocus
        />
    </x-form-group>

    {{-- Traffic Source + Budget row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <x-form-group label="Traffic Source" for="traffic_source" :required="true" :error="$errors->get('traffic_source') ?? null">
            <input
                type="text"
                name="traffic_source"
                id="traffic_source"
                value="{{ old('traffic_source', $campaign->traffic_source ?? '') }}"
                class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                placeholder="e.g., Google Ads, Facebook, TikTok"
                required
            />
        </x-form-group>

        <x-form-group label="Budget ($)" for="budget" :required="true" :error="$errors->get('budget') ?? null">
            <input
                type="number"
                name="budget"
                id="budget"
                value="{{ old('budget', $campaign->budget ?? '0.00') }}"
                step="0.01"
                min="0"
                class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm"
                required
            />
        </x-form-group>
    </div>

    {{-- Action bar --}}
    <div class="pt-4 border-t border-gray-200 flex items-center justify-end gap-3">
        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center px-4 py-2 {{ $isEdit ? 'bg-brand-600 hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-900 focus:ring-brand-500' : 'bg-emerald-600 hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:ring-emerald-500' }} border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
            {{ $isEdit ? 'Update Campaign' : 'Create Campaign' }}
        </button>
    </div>
</div>
