@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full px-3 py-2 rounded-md text-base font-medium text-brand-700 bg-brand-50 focus:outline-none focus:text-brand-800 focus:bg-brand-100 transition-colors duration-150'
            : 'block w-full px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 focus:outline-none focus:text-gray-800 focus:bg-gray-50 transition-colors duration-150';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
