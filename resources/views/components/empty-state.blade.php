@props(['title', 'description', 'actionText' => null, 'actionUrl' => null, 'icon' => 'plus'])

<div class="bg-white rounded-card shadow-card p-8 text-center">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
        @if ($icon === 'search')
            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        @elseif ($icon === 'empty')
            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        @else
            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
        @endif
    </div>

    <h3 class="mt-4 text-base font-semibold text-gray-900">{{ $title }}</h3>

    @if ($description)
        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">{{ $description }}</p>
    @endif

    @if ($actionText && $actionUrl)
        <div class="mt-5">
            <a href="{{ $actionUrl }}" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                {{ $actionText }}
            </a>
        </div>
    @endif
</div>
