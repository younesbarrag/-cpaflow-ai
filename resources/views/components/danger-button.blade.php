<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150']) }}>
    {{ $slot }}
</button>
