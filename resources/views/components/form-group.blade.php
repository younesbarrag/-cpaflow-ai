@props(['label', 'for', 'required' => false, 'error' => null])

<div>
    <label for="{{ $for }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-red-500">*</span>
        @endif
    </label>
    <div class="mt-1">
        {{ $slot }}
    </div>
    @if ($error)
        <p class="mt-1 text-sm text-red-600">{{ $error }}</p>
    @endif
</div>
