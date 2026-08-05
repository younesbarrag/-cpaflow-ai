<section>
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-sm font-medium text-gray-700">
                Current password <span class="text-red-500">*</span>
            </label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                autocomplete="current-password"
            />
            @error('current_password', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password" class="block text-sm font-medium text-gray-700">
                New password <span class="text-red-500">*</span>
            </label>
            <input
                id="update_password_password"
                name="password"
                type="password"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                autocomplete="new-password"
            />
            @error('password', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-sm font-medium text-gray-700">
                Confirm password <span class="text-red-500">*</span>
            </label>
            <input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                autocomplete="new-password"
            />
            @error('password_confirmation', 'updatePassword')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                Update password
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >Saved.</p>
            @endif
        </div>
    </form>
</section>
