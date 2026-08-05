@php
    $isEdit = isset($offer) && $offer !== null;
@endphp

<div class="p-5 sm:p-6">

    {{-- Section: Offer details --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900">Offer details</h2>
        <p class="mt-0.5 text-xs text-gray-500">Basic information about this offer.</p>
    </div>

    <div class="mt-5 space-y-5">
        {{-- Offer Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">
                Offer name <span class="text-red-500">*</span>
            </label>
            <input
                type="text"
                name="name"
                id="name"
                value="{{ old('name', $offer->name ?? '') }}"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                placeholder="e.g., Nutra COD – US Tier 1"
                required
                autofocus
            />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Destination URL --}}
        <div>
            <label for="destination_url" class="block text-sm font-medium text-gray-700">
                Destination URL <span class="text-red-500">*</span>
            </label>
            <input
                type="url"
                name="destination_url"
                id="destination_url"
                value="{{ old('destination_url', $offer->destination_url ?? '') }}"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                placeholder="https://example.com/landing-page"
                required
            />
            <p class="mt-1 text-xs text-gray-500">Visitors will be redirected to this URL through generated tracking links.</p>
            @error('destination_url')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Divider --}}
    <div class="my-6 border-t border-gray-200"></div>

    {{-- Section: Commercial settings --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900">Commercial settings</h2>
        <p class="mt-0.5 text-xs text-gray-500">Configure payout and status.</p>
    </div>

    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
        {{-- Payout --}}
        <div>
            <label for="payout" class="block text-sm font-medium text-gray-700">
                Payout <span class="text-red-500">*</span>
            </label>
            <div class="mt-1 relative rounded-lg shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 text-sm">$</span>
                </div>
                <input
                    type="number"
                    name="payout"
                    id="payout"
                    value="{{ old('payout', $offer->payout ?? '0.00') }}"
                    step="0.01"
                    min="0"
                    class="block w-full pl-7 rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    required
                />
            </div>
            @error('payout')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Status --}}
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">
                Status <span class="text-red-500">*</span>
            </label>
            <select
                name="status"
                id="status"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
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
            @error('status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Divider --}}
    <div class="my-6 border-t border-gray-200"></div>

    {{-- Section: Notes --}}
    <div>
        <h2 class="text-base font-semibold text-gray-900">Notes</h2>
        <p class="mt-0.5 text-xs text-gray-500">Optional internal details about this offer.</p>
    </div>

    <div class="mt-5">
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea
            name="description"
            id="description"
            rows="4"
            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
            placeholder="Optional — internal notes about this offer"
        >{{ old('description', $offer->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Divider --}}
    <div class="my-6 border-t border-gray-200"></div>

    {{-- Action bar --}}
    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('offers.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
            Cancel
        </a>
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150">
            {{ $isEdit ? 'Update offer' : 'Create offer' }}
        </button>
    </div>

</div>
