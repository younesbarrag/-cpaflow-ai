@props(['action', 'method' => 'POST', 'confirmMessage' => 'Are you sure?', 'label', 'class' => '', 'modalTitle' => 'Confirm action', 'confirmLabel' => 'Confirm'])

@php
    $uniqueId = 'confirm-' . substr(md5($action . $method . $label), 0, 8);
@endphp

<button type="button" class="{{ $class }}" x-data @click="$dispatch('open-modal', '{{ $uniqueId }}')">
    {{ $label }}
</button>

<x-modal name="{{ $uniqueId }}" :show="false" max-width="md" focusable>
    <div class="p-6">
        <h2 class="text-lg font-semibold text-gray-900">{{ $modalTitle }}</h2>
        <p class="mt-2 text-sm text-gray-600">{{ $confirmMessage }}</p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" @click="$dispatch('close-modal', '{{ $uniqueId }}')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                Cancel
            </button>
            <form method="POST" action="{{ $action }}" class="inline-block">
                @csrf
                @if ($method !== 'POST')
                    @method($method)
                @endif
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-150">
                    {{ $confirmLabel }}
                </button>
            </form>
        </div>
    </div>
</x-modal>
