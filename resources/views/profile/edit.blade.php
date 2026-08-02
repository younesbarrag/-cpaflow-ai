<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-page-header
                title="Profile"
                description="Manage your account settings and preferences."
            />

            <div class="space-y-6 max-w-2xl">
                {{-- Profile Information --}}
                <div class="bg-white rounded-lg shadow-card">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Profile Information</h2>
                        <p class="mt-1 text-sm text-gray-600">Update your account name and email address.</p>
                    </div>
                    <div class="p-6">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                {{-- Update Password --}}
                <div class="bg-white rounded-lg shadow-card">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Update Password</h2>
                        <p class="mt-1 text-sm text-gray-600">Ensure your account is using a long, random password to stay secure.</p>
                    </div>
                    <div class="p-6">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                {{-- Delete Account --}}
                <div class="bg-white rounded-lg shadow-card">
                    <div class="px-6 py-4 border-b border-red-100 bg-red-50">
                        <h2 class="text-lg font-semibold text-red-900">Delete Account</h2>
                        <p class="mt-1 text-sm text-red-600">Permanently delete your account. Once deleted, all resources and data will be permanently removed.</p>
                    </div>
                    <div class="p-6">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
