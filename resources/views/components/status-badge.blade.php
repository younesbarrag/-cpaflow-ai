@props(['status'])

@php
    $config = match($status) {
        'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'pulse' => false],
        'active' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'pulse' => false],
        'suspended' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'pulse' => false],
        'archived' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-500', 'dot' => 'bg-gray-400', 'pulse' => false],
        'pending' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'pulse' => true],
        'processing' => ['bg' => 'bg-brand-100', 'text' => 'text-brand-700', 'dot' => 'bg-brand-500', 'pulse' => true],
        'completed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'pulse' => false],
        'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500', 'pulse' => false],
        'approved' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'pulse' => false],
        'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'dot' => 'bg-red-500', 'pulse' => false],
        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'pulse' => false],
    };
@endphp

<span {{ $attributes->class([
    'inline-flex',
    'items-center',
    'gap-1.5',
    'px-2.5',
    'py-0.5',
    'rounded-full',
    'text-xs',
    'font-medium',
    $config['bg'],
    $config['text'],
]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }} {{ $config['pulse'] ? 'animate-pulse-dot' : '' }}"></span>
    {{ ucfirst($status) }}
</span>
