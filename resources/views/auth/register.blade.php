<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-xl font-bold text-gray-900">Create your account</h1>
        <p class="mt-1 text-sm text-gray-500">Start managing your CPA campaigns in one place.</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">
                    Name
                </label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    required
                    autofocus
                    autocomplete="name"
                />
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

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
                    autocomplete="username"
                />
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

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
                    autocomplete="new-password"
                />
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                    Confirm password
                </label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    required
                    autocomplete="new-password"
                />
                @error('password_confirmation')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                Create account
            </button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-gray-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700 transition-colors duration-150">
            Sign in
        </a>
    </p>
</x-guest-layout>
