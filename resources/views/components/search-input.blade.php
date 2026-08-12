@props(['name' => 'search', 'value' => '', 'placeholder' => 'Search...'])

<div class="relative w-full" x-data="{ query: '{{ $value }}' }">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </div>
    <input
        type="text"
        name="{{ $name }}"
        x-model="query"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        class="block w-full pl-10 pr-9 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder-gray-500 bg-white focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 transition-colors duration-150"
    />
    <div class="absolute inset-y-0 right-0 pr-3 flex items-center" x-show="query.length > 0">
        <button
            type="button"
            @click="query = ''; $el.closest('form')?.submit()"
            class="text-gray-400 hover:text-gray-600 transition-colors duration-150"
            aria-label="Clear search"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
