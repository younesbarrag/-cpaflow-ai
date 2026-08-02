@props(['title', 'description' => null])

<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>
