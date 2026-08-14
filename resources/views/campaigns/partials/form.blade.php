@php
    $isEdit = isset($campaign) && $campaign !== null;
@endphp

<div class="p-5 sm:p-6">

    {{-- Section: Campaign details --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900">Campaign details</h2>
        <p class="mt-0.5 text-xs text-gray-500">Basic information about this campaign.</p>
    </div>

    <div class="mt-5 space-y-5">
        {{-- Offer --}}
        <div>
            <label for="offer_id" class="block text-sm font-medium text-gray-700">
                Offer <span class="text-red-500">*</span>
            </label>
            @if ($isEdit)
                <div class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    {{ $campaign->offer->name ?? 'Unknown offer' }}
                </div>
                <p class="mt-1 text-xs text-gray-500">Offer cannot be changed after creation.</p>
            @else
                <select
                    name="offer_id"
                    id="offer_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
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
            @error('offer_id')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Campaign Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">
                Campaign name <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="name"
                id="name"
                value="{{ old('name', $campaign->name ?? '') }}"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                placeholder="e.g., Google Ads — Summer Sale"
                required
                autofocus
            />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Divider --}}
    <div class="my-6 border-t border-gray-200"></div>

    {{-- Section: Traffic & budget --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900">Traffic & budget</h2>
        <p class="mt-0.5 text-xs text-gray-500">Where your traffic comes from and how much to spend.</p>
    </div>

    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        {{-- Traffic Source --}}
        <div>
            <label for="traffic_source" class="block text-sm font-medium text-gray-700">
                Traffic source <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="traffic_source"
                id="traffic_source"
                value="{{ old('traffic_source', $campaign->traffic_source ?? '') }}"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                placeholder="e.g., Google Ads, Facebook, TikTok"
                required
            />
            @error('traffic_source')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Budget --}}
        <div>
            <label for="budget" class="block text-sm font-medium text-gray-700">
                Budget <span class="text-red-500">*</span>
            </label>
            <div class="mt-1 relative rounded-lg shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 text-sm">$</span>
                </div>
                <input
                    type="number"
                    name="budget"
                    id="budget"
                    value="{{ old('budget', $campaign->budget ?? '0.00') }}"
                    step="0.01"
                    min="0"
                    class="block w-full pl-7 rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    required
                />
            </div>
            @error('budget')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Divider --}}
    <div class="my-6 border-t border-gray-200"></div>

    {{-- Action bar --}}
    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('campaigns.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150">
            {{ $isEdit ? 'Update campaign' : 'Create campaign' }}
        </button>
    </div>

</div>
