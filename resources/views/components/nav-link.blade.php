@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-2 text-sm font-medium text-brand-600 border-b-2 border-brand-500 transition-colors duration-150'
            : 'inline-flex items-center px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition-colors duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
