@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full border-gray-300 rounded-lg text-sm text-gray-900 shadow-sm placeholder-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 disabled:bg-gray-50 disabled:text-gray-500 transition-colors duration-150']) }}>
