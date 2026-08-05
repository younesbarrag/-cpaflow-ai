<section>
    <button
        type="button"
        class="inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-lg text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-150"
        x-data
        @click="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        Delete Account
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <div class="flex items-start gap-4">
                <div class="shrink-0 mt-0.5">
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Are you sure you want to delete your account?
                    </h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                    </p>

                    <div class="mt-5">
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            placeholder="Enter your password"
                        />
                        @error('password', 'userDeletion')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" @click="$dispatch('close-modal', 'confirm-user-deletion')" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-700 transition-colors duration-150">
                            Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-modal>
</section>
