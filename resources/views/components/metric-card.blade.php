@props(['label', 'value', 'color' => 'brand', 'helperText' => null])

@php
    $colorClasses = match($color) {
        'brand' => 'border-l-brand-500',
        'emerald' => 'border-l-emerald-500',
        'amber' => 'border-l-amber-500',
        'red' => 'border-l-red-500',
        'indigo' => 'border-l-indigo-500',
        'violet' => 'border-l-violet-500',
        default => 'border-l-brand-500',
    };

    $iconBg = match($color) {
        'brand' => 'bg-brand-50',
        'emerald' => 'bg-emerald-50',
        'amber' => 'bg-amber-50',
        'red' => 'bg-red-50',
        'indigo' => 'bg-indigo-50',
        'violet' => 'bg-violet-50',
        default => 'bg-brand-50',
    };
@endphp

<div {{ $attributes->merge(['class' => "bg-white rounded-card shadow-card p-5 border-l-4 $colorClasses animate-fade-in"]) }}>
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ $value }}</p>
        </div>
        @if ($icon ?? $slot->isNotEmpty())
            <div class="flex-shrink-0 p-2.5 rounded-lg {{ $iconBg }}">
                {!! $icon ?? $slot !!}
            </div>
        @endif
    </div>
    @if ($helperText)
        <p class="mt-2 text-xs text-gray-400">{{ $helperText }}</p>
    @endif
</div>
