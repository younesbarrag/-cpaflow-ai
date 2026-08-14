<x-app-layout>
    <div class="py-8" x-data="campaignShow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="mb-4">
                <ol class="flex items-center gap-1.5 text-sm text-gray-500">
                    <li><a href="{{ route('campaigns.index') }}" class="hover:text-gray-700 transition-colors duration-150">Campaigns</a></li>
                    <li><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></li>
                    <li class="text-gray-900 font-medium">{{ $campaign->name }}</li>
                </ol>
            </nav>

            {{-- Header --}}
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900">{{ $campaign->name }}</h1>
                        <x-status-badge :status="$campaign->status->value" />
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ $campaign->offer->name ?? '—' }} &middot; {{ $campaign->traffic_source }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @if (in_array($campaign->status->value, ['draft', 'suspended']))
                        <form method="POST" action="{{ route('campaigns.activate', $campaign) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-800 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                                </svg>
                                Activate
                            </button>
                        </form>
                    @endif
                    @if ($campaign->status->value === 'active')
                        <form method="POST" action="{{ route('campaigns.suspend', $campaign) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-700 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                </svg>
                                Suspend
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('campaigns.edit', $campaign) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                        Edit
                    </a>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex gap-6 -mb-px" role="tablist">
                    @foreach (['overview' => 'Overview', 'tracking' => 'Tracking', 'expenses' => 'Expenses', 'conversions' => 'Conversions', 'ai' => 'AI'] as $key => $label)
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === '{{ $key }}'"
                            @click="activeTab = '{{ $key }}'"
                            :class="activeTab === '{{ $key }}'
                                ? 'border-brand-500 text-brand-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 text-sm font-medium transition-colors duration-150"
                        >
                            {{ $label }}
                            @if ($key === 'tracking' && $campaign->trackingLinks->count() > 0)
                                <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">{{ $campaign->trackingLinks->count() }}</span>
                            @endif
                            @if ($key === 'conversions' && $campaign->conversions->where('status', \App\Enums\ConversionStatus::Pending)->count() > 0)
                                <span class="ml-1.5 inline-flex items-center justify-center w-5 h-5 text-xs font-medium rounded-full bg-amber-100 text-amber-700">{{ $campaign->conversions->where('status', \App\Enums\ConversionStatus::Pending)->count() }}</span>
                            @endif
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- Tab: Overview --}}
            <div x-show="activeTab === 'overview'" x-cloak role="tabpanel">
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-900">Campaign Details</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Status</p>
                        <div class="mt-2"><x-status-badge :status="$campaign->status->value" /></div>
                    </div>
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Offer</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ $campaign->offer->name ?? '—' }}</p>
                    </div>
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Traffic source</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">{{ $campaign->traffic_source }}</p>
                    </div>
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</p>
                        <p class="mt-2 text-sm font-semibold text-gray-900">${{ number_format((float) $campaign->budget, 2) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total conversions</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">{{ $campaign->conversions->count() }}</p>
                    </div>
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total expenses</p>
                        <p class="mt-2 text-2xl font-bold text-gray-900">${{ number_format((float) $campaign->expenses->sum('amount'), 2) }}</p>
                    </div>
                </div>

                @if ($campaign->offer && $campaign->offer->destination_url)
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Landing page</h3>
                        <p class="text-sm text-gray-500 mb-3">The destination URL visitors are directed to after clicking the tracking link.</p>
                        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            <code class="text-sm text-gray-700 break-all">{{ $campaign->offer->destination_url }}</code>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tab: Tracking --}}
            <div x-show="activeTab === 'tracking'" x-cloak role="tabpanel">
                @if ($campaign->trackingLinks->isNotEmpty())
                    <div class="space-y-3">
                        @foreach ($campaign->trackingLinks as $link)
                            <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                                <x-tracking-url :url="route('tracking.redirect', $link->code)" :code="$link->code" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No tracking links yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Generate a unique tracking link to start driving traffic to your offer.</p>
                        <div class="mt-6">
                            <form method="POST" action="{{ route('campaigns.tracking-links.store', $campaign) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                                    </svg>
                                    Generate Link
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($campaign->trackingLinks->isNotEmpty())
                    <div class="mt-4">
                        <form method="POST" action="{{ route('campaigns.tracking-links.store', $campaign) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Generate another link
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Tab: Expenses --}}
            <div x-show="activeTab === 'expenses'" x-cloak role="tabpanel" x-data="expenseManager">

                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Expenses</h3>
                        <p class="mt-0.5 text-xs text-gray-500">Track campaign spending and keep profit calculations accurate.</p>
                    </div>
                    <button
                        type="button"
                        @click="openAddForm()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Expense
                    </button>
                </div>

                {{-- Add / Edit Form --}}
                <div x-show="showForm" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="mb-6">
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-semibold text-gray-900" x-text="editingExpense ? 'Edit Expense' : 'Add Expense'"></h4>
                            <button type="button" @click="cancelForm()" class="text-gray-400 hover:text-gray-500 transition-colors duration-150">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitForm()" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="expense-amount" class="block text-xs font-medium text-gray-700 mb-1">Amount <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500">$</span>
                                        <input
                                            type="text"
                                            id="expense-amount"
                                            x-model="form.amount"
                                            inputmode="decimal"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            class="block w-full pl-7 pr-3 py-2 border rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors duration-150"
                                            :class="formErrors.amount ? 'border-red-300' : 'border-gray-300'"
                                        />
                                    </div>
                                    <template x-if="formErrors.amount">
                                        <p class="mt-1 text-xs text-red-600" x-text="formErrors.amount[0]"></p>
                                    </template>
                                </div>
                                <div>
                                    <label for="expense-spent-at" class="block text-xs font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                                    <input
                                        type="date"
                                        id="expense-spent-at"
                                        x-model="form.spent_at"
                                        :max="today"
                                        required
                                        class="block w-full px-3 py-2 border rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors duration-150"
                                        :class="formErrors.spent_at ? 'border-red-300' : 'border-gray-300'"
                                    />
                                    <template x-if="formErrors.spent_at">
                                        <p class="mt-1 text-xs text-red-600" x-text="formErrors.spent_at[0]"></p>
                                    </template>
                                </div>
                                <div>
                                    <label for="expense-description" class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                                    <input
                                        type="text"
                                        id="expense-description"
                                        x-model="form.description"
                                        maxlength="10000"
                                        placeholder="e.g. Facebook ads"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition-colors duration-150"
                                        :class="formErrors.description ? 'border-red-300' : ''"
                                    />
                                    <template x-if="formErrors.description">
                                        <p class="mt-1 text-xs text-red-600" x-text="formErrors.description[0]"></p>
                                    </template>
                                </div>
                            </div>
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" @click="cancelForm()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="formSubmitting"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150 disabled:opacity-50"
                                >
                                    <svg x-show="formSubmitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="editingExpense ? 'Save Changes' : 'Add Expense'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                @if ($campaign->expenses->isNotEmpty())
                    <div class="hidden sm:block bg-white rounded-card shadow-card border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50">
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($campaign->expenses as $expense)
                                        <tr class="hover:bg-gray-50 transition-colors duration-100">
                                            <td class="px-6 py-3 text-sm text-gray-500">{{ $expense->spent_at->format('M j, Y') }}</td>
                                            <td class="px-6 py-3 text-sm text-gray-700">{{ $expense->description ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm font-semibold text-gray-900 text-right">${{ number_format((float) $expense->amount, 2) }}</td>
                                            <td class="px-6 py-3 text-right">
                                                <div class="flex items-center justify-end gap-1">
                                                    <button
                                                        type="button"
                                                        @click="openEditForm({{ json_encode(['id' => $expense->id, 'amount' => number_format((float) $expense->amount, 2, '.', ''), 'spent_at' => $expense->spent_at->format('Y-m-d'), 'description' => $expense->description]) }})"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-150"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                                        </svg>
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="deleteExpense({{ json_encode(['id' => $expense->id]) }})"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sm:hidden space-y-3">
                        @foreach ($campaign->expenses as $expense)
                            <div class="bg-white rounded-card shadow-card border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-3 mb-1">
                                    <p class="text-sm font-semibold text-gray-900">${{ number_format((float) $expense->amount, 2) }}</p>
                                    <span class="text-xs text-gray-500">{{ $expense->spent_at->format('M j, Y') }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mb-3">{{ $expense->description ?? 'No description' }}</p>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditForm({{ json_encode(['id' => $expense->id, 'amount' => number_format((float) $expense->amount, 2, '.', ''), 'spent_at' => $expense->spent_at->format('Y-m-d'), 'description' => $expense->description]) }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors duration-150"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteExpense({{ json_encode(['id' => $expense->id]) }})"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No expenses yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Record campaign costs so expenses and profit stay accurate.</p>
                        <div class="mt-6">
                            <button
                                type="button"
                                @click="openAddForm()"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 transition-colors duration-150"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add Expense
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tab: Conversions --}}
            <div x-show="activeTab === 'conversions'" x-cloak role="tabpanel">
                @if ($campaign->conversions->isNotEmpty())
                    <div class="bg-white rounded-card shadow-card border border-gray-200 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="border-b border-gray-200 bg-gray-50">
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Source</th>
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">External ID</th>
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Revenue</th>
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($campaign->conversions as $conversion)
                                        <tr class="hover:bg-gray-50 transition-colors duration-100" id="conversion-{{ $conversion->id }}">
                                            <td class="px-6 py-3 text-sm text-gray-700">{{ $conversion->source ?? '—' }}</td>
                                            <td class="px-6 py-3 text-sm text-gray-500 font-mono">{{ $conversion->external_id }}</td>
                                            <td class="px-6 py-3 text-sm font-medium text-gray-900">${{ number_format((float) $conversion->revenue, 2) }}</td>
                                            <td class="px-6 py-3">
                                                <x-status-badge :status="$conversion->status->value" />
                                            </td>
                                            <td class="px-6 py-3 text-sm text-gray-500">{{ $conversion->converted_at->format('M j, Y g:i A') }}</td>
                                            <td class="px-6 py-3 text-right">
                                                @if ($conversion->status->value === 'pending')
                                                    <div class="flex items-center justify-end gap-1" x-data="{ processing: false }">
                                                        <button
                                                            type="button"
                                                            :disabled="processing"
                                                            @click="
                                                                processing = true;
                                                                fetch('{{ route('api.v1.campaigns.conversions.approve', [$campaign, $conversion]) }}', {
                                                                    method: 'POST',
                                                                    headers: {
                                                                        'Content-Type': 'application/json',
                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                                        'Accept': 'application/json'
                                                                    }
                                                                })
                                                                .then(r => {
                                                                    if (r.ok) return r.json();
                                                                    throw r;
                                                                })
                                                                .then(() => { location.reload(); })
                                                                .catch(async (e) => {
                                                                    const body = await e.json().catch(() => ({}));
                                                                    alert(body.message || 'Failed to approve conversion.');
                                                                    processing = false;
                                                                });
                                                            "
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                            </svg>
                                                            Approve
                                                        </button>
                                                        <button
                                                            type="button"
                                                            :disabled="processing"
                                                            @click="
                                                                processing = true;
                                                                fetch('{{ route('api.v1.campaigns.conversions.reject', [$campaign, $conversion]) }}', {
                                                                    method: 'POST',
                                                                    headers: {
                                                                        'Content-Type': 'application/json',
                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                                                                        'Accept': 'application/json'
                                                                    }
                                                                })
                                                                .then(r => {
                                                                    if (r.ok) return r.json();
                                                                    throw r;
                                                                })
                                                                .then(() => { location.reload(); })
                                                                .catch(async (e) => {
                                                                    const body = await e.json().catch(() => ({}));
                                                                    alert(body.message || 'Failed to reject conversion.');
                                                                    processing = false;
                                                                });
                                                            "
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-150 disabled:opacity-50"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                            Reject
                                                        </button>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                            <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No conversions yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Conversions will appear here as they are recorded through your tracking links.</p>
                    </div>
                @endif
            </div>

            {{-- Tab: AI --}}
            <div x-show="activeTab === 'ai'" x-cloak role="tabpanel">
                @php
                    $analysis = $campaign->offer->analysis ?? null;
                    $generations = $campaign->offer->generations ?? collect();
                @endphp

                @if ($analysis || $generations->isNotEmpty())
                    @if ($analysis)
                        <div class="bg-white rounded-card shadow-card border border-gray-200 p-5 mb-4">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-gray-900">Offer analysis</h3>
                                <x-status-badge :status="$analysis->status->value" />
                            </div>
                            @if ($analysis->status->value === 'completed')
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="text-center">
                                        <p class="text-3xl font-bold text-brand-600">{{ $analysis->score }}/100</p>
                                        <p class="text-xs text-gray-500 mt-1">Score</p>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-700">{{ $analysis->summary }}</p>
                                    </div>
                                </div>
                                @if ($analysis->strengths)
                                    <div class="mb-3">
                                        <h4 class="text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">Strengths</h4>
                                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                            @foreach ($analysis->strengths as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if ($analysis->weaknesses)
                                    <div class="mb-3">
                                        <h4 class="text-xs font-semibold text-red-600 uppercase tracking-wider mb-1">Weaknesses</h4>
                                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                            @foreach ($analysis->weaknesses as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if ($analysis->recommendations)
                                    <div>
                                        <h4 class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1">Recommendations</h4>
                                        <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                            @foreach ($analysis->recommendations as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @elseif ($analysis->status->value === 'pending' || $analysis->status->value === 'processing')
                                <p class="text-sm text-gray-500">Analysis is {{ $analysis->status->value }}...</p>
                            @else
                                <p class="text-sm text-red-600">{{ $analysis->error_message ?? 'Analysis failed.' }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($generations->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($generations->take(3) as $gen)
                                <div class="bg-white rounded-card shadow-card border border-gray-200 p-5">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-semibold text-gray-900">AI generation</h3>
                                        <x-status-badge :status="$gen->status->value" />
                                    </div>
                                    @if ($gen->status->value === 'completed')
                                        @if ($gen->hooks)
                                            <div class="mb-3">
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Hooks</h4>
                                                <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                                    @foreach ($gen->hooks as $hook)
                                                        <li>{{ $hook }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                        @if ($gen->captions)
                                            <div>
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Captions</h4>
                                                <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                                    @foreach ($gen->captions as $caption)
                                                        <li>{{ $caption }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @elseif ($gen->status->value === 'pending' || $gen->status->value === 'processing')
                                        <p class="text-sm text-gray-500">Generation is {{ $gen->status->value }}...</p>
                                    @else
                                        <p class="text-sm text-red-600">{{ $gen->error_message ?? 'Generation failed.' }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="bg-white rounded-card shadow-card border border-gray-200 p-8 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-50">
                            <svg class="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-gray-900">No AI analysis yet</h3>
                        <p class="mt-2 text-sm text-gray-500 max-w-sm mx-auto">Analyze the linked offer to see insights and generated marketing content here.</p>
                        @if ($campaign->offer)
                            <div class="mt-5">
                                <a href="{{ route('offers.edit', $campaign->offer) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                    </svg>
                                    Open linked offer
                                </a>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('campaignShow', () => ({
                activeTab: 'overview',
                tabs: ['overview', 'tracking', 'expenses', 'conversions', 'ai'],
                campaignId: @js($campaign->id),
                csrfToken: document.querySelector('meta[name="csrf-token"]').content,
            }));

            Alpine.data('expenseManager', () => ({
                campaignId: @js($campaign->id),
                csrfToken: document.querySelector('meta[name="csrf-token"]').content,
                today: new Date().toISOString().split('T')[0],

                showForm: false,
                editingExpense: null,
                form: { amount: '', spent_at: '', description: '' },
                formErrors: {},
                formSubmitting: false,

                apiHeaders() {
                    return {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    };
                },

                openAddForm() {
                    this.editingExpense = null;
                    this.form = { amount: '', spent_at: this.today, description: '' };
                    this.formErrors = {};
                    this.showForm = true;
                    this.$nextTick(() => {
                        const el = document.getElementById('expense-amount');
                        if (el) el.focus();
                    });
                },

                openEditForm(expense) {
                    this.editingExpense = expense;
                    this.form = {
                        amount: expense.amount,
                        spent_at: expense.spent_at,
                        description: expense.description || '',
                    };
                    this.formErrors = {};
                    this.showForm = true;
                    this.$nextTick(() => {
                        const el = document.getElementById('expense-amount');
                        if (el) el.focus();
                    });
                },

                cancelForm() {
                    this.showForm = false;
                    this.editingExpense = null;
                    this.form = { amount: '', spent_at: '', description: '' };
                    this.formErrors = {};
                },

                async submitForm() {
                    this.formSubmitting = true;
                    this.formErrors = {};

                    const isEdit = this.editingExpense !== null;
                    const url = isEdit
                        ? '/api/v1/campaigns/' + this.campaignId + '/expenses/' + this.editingExpense.id
                        : '/api/v1/campaigns/' + this.campaignId + '/expenses';

                    const body = {
                        amount: this.form.amount,
                        spent_at: this.form.spent_at,
                    };
                    if (this.form.description) {
                        body.description = this.form.description;
                    } else if (!isEdit) {
                        body.description = null;
                    }

                    try {
                        const response = await fetch(url, {
                            method: isEdit ? 'PATCH' : 'POST',
                            headers: this.apiHeaders(),
                            body: JSON.stringify(body),
                        });

                        if (response.ok) {
                            location.reload();
                            return;
                        }

                        if (response.status === 422) {
                            const data = await response.json();
                            this.formErrors = data.errors || {};
                            return;
                        }

                        const data = await response.json().catch(() => ({}));
                        alert(data.message || 'Something went wrong.');
                    } catch (e) {
                        alert('Network error. Please try again.');
                    } finally {
                        this.formSubmitting = false;
                    }
                },

                async deleteExpense(expense) {
                    if (!confirm('Are you sure you want to delete this expense?')) {
                        return;
                    }

                    try {
                        const response = await fetch('/api/v1/campaigns/' + this.campaignId + '/expenses/' + expense.id, {
                            method: 'DELETE',
                            headers: this.apiHeaders(),
                        });

                        if (response.ok || response.status === 204) {
                            location.reload();
                            return;
                        }

                        const data = await response.json().catch(() => ({}));
                        alert(data.message || 'Failed to delete expense.');
                    } catch (e) {
                        alert('Network error. Please try again.');
                    }
                },
            }));
        });
    </script>
</x-app-layout>
