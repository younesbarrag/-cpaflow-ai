<x-guest-layout>
    <div class="text-center mb-6">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 mb-4">
            <svg class="h-7 w-7 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>

        <h1 class="text-xl font-bold text-gray-900">Confirm your password</h1>
        <p class="mt-2 text-sm text-gray-500">
            This is a secure area of the application. Please confirm your password before continuing.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">
                Password
            </label>
            <input
                id="password"
                name="password"
                type="password"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                required
                autocomplete="current-password"
            />
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                Confirm
            </button>
        </div>
    </form>
</x-guest-layout>
