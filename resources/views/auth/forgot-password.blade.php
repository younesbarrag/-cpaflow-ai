<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-xl font-bold text-gray-900">Forgot your password?</h1>
        <p class="mt-1 text-sm text-gray-500">Enter your email and we'll send you a link to reset your password.</p>
    </div>

    {{-- Session Status --}}
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">
                Email address
            </label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                required
                autofocus
            />
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                Send reset link
            </button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700 transition-colors duration-150">
            Back to sign in
        </a>
    </p>
</x-guest-layout>
