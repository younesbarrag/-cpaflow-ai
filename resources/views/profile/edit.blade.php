<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">Profile</h1>
                <p class="mt-1 text-sm text-gray-500">Manage your account information and security.</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8">

                {{-- Main Column --}}
                <div class="flex-1 min-w-0 space-y-6">

                    {{-- Profile Information --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-base font-semibold text-gray-900">Profile Information</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Update your account name and email address.</p>
                        </div>
                        <div class="p-5">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>

                    {{-- Update Password --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200">
                        <div class="px-5 py-4 border-b border-gray-200">
                            <h2 class="text-base font-semibold text-gray-900">Update Password</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Ensure your account is using a long, random password to stay secure.</p>
                        </div>
                        <div class="p-5">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="w-full lg:w-80 shrink-0 space-y-6">

                    {{-- Account Summary --}}
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-5">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">Account</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-full bg-brand-100 flex items-center justify-center shrink-0">
                                <span class="text-lg font-bold text-brand-700">{{ strtoupper(substr($user->name, 0, 2)) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="space-y-2.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Role</span>
                                <span class="font-medium text-gray-900 capitalize">{{ $user->role->value }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Member since</span>
                                <span class="font-medium text-gray-900">{{ $user->created_at->format('M j, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Last updated</span>
                                <span class="font-medium text-gray-900">{{ $user->updated_at->format('M j, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Danger Zone --}}
                    <div class="rounded-card border border-red-200 bg-red-50 p-5">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-red-900">Delete Account</h3>
                                <p class="mt-1 text-xs text-red-700">Permanently delete your account and all associated data. This action cannot be undone.</p>
                                <div class="mt-3">
                                    @include('profile.partials.delete-user-form')
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>
