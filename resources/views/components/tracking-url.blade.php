@props(['url', 'code'])

<div class="flex items-center gap-2" x-data="{ copied: false }">
    <input
        type="text"
        value="{{ $url }}"
        readonly
        class="block flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-700 focus:outline-none focus:border-brand-500 focus:ring-brand-500"
    />
    <button
        type="button"
        @click="
            navigator.clipboard.writeText('{{ $url }}').then(() => {
                copied = true;
                setTimeout(() => copied = false, 2000);
            }).catch(() => {
                const input = document.createElement('input');
                input.value = '{{ $url }}';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                copied = true;
                setTimeout(() => copied = false, 2000);
            });
        "
        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition ease-in-out duration-150"
    >
        <span x-show="!copied">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
        </span>
        <span x-show="copied" class="text-emerald-600">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </button>
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition ease-in-out duration-150"
        aria-label="Open tracking link"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
    </a>
</div>
