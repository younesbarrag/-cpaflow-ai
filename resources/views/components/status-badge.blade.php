@props(['status'])

@php
    $statusClasses = match($status) {
        'draft' => 'bg-gray-100 text-gray-700',
        'active' => 'bg-emerald-100 text-emerald-700',
        'suspended' => 'bg-amber-100 text-amber-700',
        'archived' => 'bg-red-100 text-red-700',
        default => 'bg-gray-100 text-gray-700',
    };
    $dotClasses = match($status) {
        'draft' => 'bg-gray-400',
        'active' => 'bg-emerald-500',
        'suspended' => 'bg-amber-500',
        'archived' => 'bg-red-400',
        default => 'bg-gray-400',
    };
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
    {{ ucfirst($status) }}
</span>
