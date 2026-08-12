<x-app-layout>
    <div class="py-8" x-data="offerAi">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Breadcrumb --}}
            <nav class="mb-4">
                <ol class="flex items-center gap-1.5 text-sm text-gray-500">
                    <li><a href="{{ route('offers.index') }}" class="hover:text-gray-700 transition-colors duration-150">Offers</a></li>
                    <li><svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></li>
                    <li class="text-gray-900 font-medium">Edit</li>
                </ol>
            </nav>

            {{-- Header --}}
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-gray-900">Edit offer</h1>
                        <x-status-badge :status="$offer->status->value" />
                    </div>
                    <p class="mt-1 text-sm text-gray-500">{{ $offer->name }}</p>
                </div>
            </div>

            {{-- Form Card --}}
            <div class="bg-white rounded-card shadow-card border border-gray-200 max-w-4xl">
                <form method="POST" action="{{ route('offers.update', $offer) }}">
                    @csrf
                    @method('PATCH')
                    @include('offers.partials.form', ['offer' => $offer])
                </form>
            </div>

            {{-- AI Assistant --}}
            <div class="mt-6 bg-white rounded-card shadow-card border border-gray-200 max-w-4xl">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-4 h-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                        </svg>
                        <h2 class="text-base font-semibold text-gray-900">AI assistant</h2>
                    </div>
                    <p class="text-xs text-gray-500 mb-5">Analyze this offer to get a score, strengths, weaknesses and recommendations.</p>

                    {{-- Analyze Button --}}
                    <div x-show="! analysis">
                        <button
                            type="button"
                            :disabled="analyzing"
                            @click="triggerAnalysis"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                        >
                            <template x-if="! analyzing">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            </template>
                            <template x-if="analyzing">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="analyzing ? 'Analyzing...' : 'Analyze offer'"></span>
                        </button>
                    </div>

                    {{-- Analysis Processing --}}
                    <div x-show="analysis && (analysis.status === 'pending' || analysis.status === 'processing')" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <x-status-badge :status="'processing'" />
                            <span class="text-sm text-gray-500">Analysis is <span x-text="analysis.status"></span>...</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-brand-500 h-1.5 rounded-full animate-pulse" style="width: 60%"></div>
                        </div>
                    </div>

                    {{-- Analysis Failed --}}
                    <div x-show="analysis && analysis.status === 'failed'" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <x-status-badge :status="'failed'" />
                            <span class="text-sm text-red-600" x-text="analysis.error_message || 'Analysis failed.'"></span>
                        </div>
                        <button
                            type="button"
                            :disabled="analyzing"
                            @click="triggerAnalysis"
                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                            </svg>
                            Retry analysis
                        </button>
                    </div>

                    {{-- Analysis Completed --}}
                    <div x-show="analysis && analysis.status === 'completed'" class="space-y-4">
                        {{-- Stale Warning --}}
                        <div x-show="analysis.is_stale" class="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                            <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                            </svg>
                            <p class="text-sm text-amber-700">Offer details changed since this analysis.</p>
                        </div>

                        {{-- Score + Summary --}}
                        <div class="flex items-start gap-4">
                            <div class="text-center flex-shrink-0">
                                <p class="text-3xl font-bold text-brand-600" x-text="analysis.score + '/100'"></p>
                                <p class="text-xs text-gray-500 mt-1">Score</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700" x-text="analysis.summary"></p>
                            </div>
                        </div>

                        {{-- Strengths --}}
                        <div x-show="analysis.strengths && analysis.strengths.length > 0">
                            <h4 class="text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-1">Strengths</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                <template x-for="item in (analysis.strengths || [])" :key="item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>
                        </div>

                        {{-- Weaknesses --}}
                        <div x-show="analysis.weaknesses && analysis.weaknesses.length > 0">
                            <h4 class="text-xs font-semibold text-red-600 uppercase tracking-wider mb-1">Weaknesses</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                <template x-for="item in (analysis.weaknesses || [])" :key="item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>
                        </div>

                        {{-- Recommendations --}}
                        <div x-show="analysis.recommendations && analysis.recommendations.length > 0">
                            <h4 class="text-xs font-semibold text-amber-600 uppercase tracking-wider mb-1">Recommendations</h4>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-0.5">
                                <template x-for="item in (analysis.recommendations || [])" :key="item">
                                    <li x-text="item"></li>
                                </template>
                            </ul>
                        </div>

                        {{-- Re-analyze --}}
                        <div class="pt-2">
                            <button
                                type="button"
                                :disabled="analyzing"
                                @click="triggerAnalysis"
                                class="inline-flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                                </svg>
                                Re-analyze
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Generated Marketing Content --}}
            <div class="mt-6 bg-white rounded-card shadow-card border border-gray-200 max-w-4xl">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="text-base font-semibold text-gray-900">Marketing content</h2>
                        <button
                            type="button"
                            :disabled="! canGenerate || generating"
                            @click="triggerGeneration"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:bg-brand-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150"
                        >
                            <template x-if="! generating">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                </svg>
                            </template>
                            <template x-if="generating">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="generating ? 'Generating...' : 'Generate content'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mb-5">Generate hooks and captions for your marketing campaigns.</p>

                    {{-- Prerequisite notice --}}
                    <div x-show="! canGenerate && ! generating" class="flex items-center gap-2 p-3 bg-gray-50 border border-gray-200 rounded-lg mb-4">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <p class="text-sm text-gray-500">Complete a current AI analysis first to enable content generation.</p>
                    </div>

                    {{-- Generation Failed --}}
                    <div x-show="generationFailed" class="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg mb-4">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <p class="text-sm text-red-600">Content generation failed. Retry or re-analyze the offer.</p>
                    </div>

                    {{-- Generation Processing --}}
                    <div x-show="generating && ! generationFailed" class="space-y-3 mb-4">
                        <div class="flex items-center gap-3">
                            <x-status-badge :status="'processing'" />
                            <span class="text-sm text-gray-500">Generating marketing content...</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-brand-500 h-1.5 rounded-full animate-pulse" style="width: 60%"></div>
                        </div>
                    </div>

                    {{-- Generations List --}}
                    <div x-show="generations.length > 0" class="space-y-4">
                        <template x-for="gen in generations" :key="gen.id">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs text-gray-400" x-text="new Date(gen.created_at).toLocaleDateString()"></span>
                                    <div class="flex items-center gap-2">
                                        <x-status-badge :status="'completed'" x-show="gen.status === 'completed'" />
                                        <x-status-badge :status="'processing'" x-show="gen.status === 'pending' || gen.status === 'processing'" />
                                        <x-status-badge :status="'failed'" x-show="gen.status === 'failed'" />
                                        <span x-show="gen.is_stale" class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">stale</span>
                                    </div>
                                </div>

                                {{-- Completed content --}}
                                <div x-show="gen.status === 'completed'" class="space-y-3">
                                    <div x-show="gen.hooks && gen.hooks.length > 0">
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Hooks</h4>
                                        <div class="space-y-1.5">
                                            <template x-for="(hook, i) in (gen.hooks || [])" :key="i">
                                                <div class="flex items-start gap-2 group">
                                                    <p class="text-sm text-gray-700 flex-1" x-text="hook"></p>
                                                    <button
                                                        type="button"
                                                        @click="copyToClipboard(hook, $event)"
                                                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-gray-600 transition-opacity duration-150 flex-shrink-0"
                                                        title="Copy"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div x-show="gen.captions && gen.captions.length > 0">
                                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Captions</h4>
                                        <div class="space-y-1.5">
                                            <template x-for="(caption, i) in (gen.captions || [])" :key="i">
                                                <div class="flex items-start gap-2 group">
                                                    <p class="text-sm text-gray-700 flex-1" x-text="caption"></p>
                                                    <button
                                                        type="button"
                                                        @click="copyToClipboard(caption, $event)"
                                                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-gray-600 transition-opacity duration-150 flex-shrink-0"
                                                        title="Copy"
                                                    >
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                {{-- Failed --}}
                                <div x-show="gen.status === 'failed'" class="text-sm text-red-600" x-text="gen.error_message || 'Generation failed.'"></div>

                                {{-- Pending/Processing --}}
                                <div x-show="gen.status === 'pending' || gen.status === 'processing'" class="text-sm text-gray-500">
                                    Generation is <span x-text="gen.status"></span>...
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('offerAi', () => ({
                offerId: @js($offer->id),
                csrfToken: document.querySelector('meta[name="csrf-token"]').content,

                analysis: null,
                analyzing: false,
                analysisPollTimer: null,

                generations: [],
                generating: false,
                generationFailed: false,
                generationPollTimer: null,

                get canGenerate() {
                    return this.analysis
                        && this.analysis.status === 'completed'
                        && ! this.analysis.is_stale;
                },

                init() {
                    this.loadAnalysis();
                    this.loadGenerations();
                },

                destroy() {
                    if (this.analysisPollTimer) clearInterval(this.analysisPollTimer);
                    if (this.generationPollTimer) clearInterval(this.generationPollTimer);
                },

                apiHeaders() {
                    return {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    };
                },

                async loadAnalysis() {
                    try {
                        const r = await fetch(`/api/v1/offers/${this.offerId}/analysis`, {
                            headers: this.apiHeaders(),
                        });
                        if (r.ok) {
                            const json = await r.json();
                            this.analysis = json.data;
                            if (this.analysis.status === 'pending' || this.analysis.status === 'processing') {
                                this.startAnalysisPolling();
                            }
                        }
                    } catch (_) {}
                },

                async triggerAnalysis() {
                    if (this.analyzing) return;
                    this.analyzing = true;
                    this.generationFailed = false;

                    try {
                        const r = await fetch(`/api/v1/offers/${this.offerId}/analyze`, {
                            method: 'POST',
                            headers: this.apiHeaders(),
                        });

                        const json = await r.json();

                        if (r.ok || r.status === 202) {
                            this.analysis = {
                                id: json.data.id,
                                offer_id: json.data.offer_id,
                                status: json.data.status,
                                is_stale: false,
                            };
                            if (json.data.status === 'pending' || json.data.status === 'processing') {
                                this.startAnalysisPolling();
                            } else {
                                await this.loadAnalysis();
                            }
                        }
                    } catch (_) {} finally {
                        this.analyzing = false;
                    }
                },

                startAnalysisPolling() {
                    if (this.analysisPollTimer) clearInterval(this.analysisPollTimer);
                    let attempts = 0;
                    this.analysisPollTimer = setInterval(async () => {
                        attempts++;
                        if (attempts > 60) {
                            clearInterval(this.analysisPollTimer);
                            return;
                        }
                        await this.loadAnalysis();
                        if (this.analysis && (this.analysis.status === 'completed' || this.analysis.status === 'failed')) {
                            clearInterval(this.analysisPollTimer);
                        }
                    }, 5000);
                },

                async loadGenerations() {
                    try {
                        const r = await fetch(`/api/v1/offers/${this.offerId}/generations`, {
                            headers: this.apiHeaders(),
                        });
                        if (r.ok) {
                            const json = await r.json();
                            this.generations = json.data || [];
                            const hasPending = this.generations.some(g => g.status === 'pending' || g.status === 'processing');
                            if (hasPending) this.startGenerationPolling();
                        }
                    } catch (_) {}
                },

                async triggerGeneration() {
                    if (this.generating || !this.canGenerate) return;
                    this.generating = true;
                    this.generationFailed = false;

                    try {
                        const r = await fetch(`/api/v1/offers/${this.offerId}/generate`, {
                            method: 'POST',
                            headers: this.apiHeaders(),
                        });

                        if (r.ok || r.status === 202) {
                            await this.loadGenerations();
                            this.startGenerationPolling();
                        } else if (r.status === 422) {
                            this.generationFailed = true;
                        }
                    } catch (_) {
                        this.generationFailed = true;
                    } finally {
                        this.generating = false;
                    }
                },

                startGenerationPolling() {
                    if (this.generationPollTimer) clearInterval(this.generationPollTimer);
                    let attempts = 0;
                    this.generationPollTimer = setInterval(async () => {
                        attempts++;
                        if (attempts > 60) {
                            clearInterval(this.generationPollTimer);
                            return;
                        }
                        await this.loadGenerations();
                        const hasPending = this.generations.some(g => g.status === 'pending' || g.status === 'processing');
                        if (!hasPending) {
                            clearInterval(this.generationPollTimer);
                        }
                    }, 5000);
                },

                async copyToClipboard(text, event) {
                    try {
                        await navigator.clipboard.writeText(text);
                        const btn = event.currentTarget;
                        btn.innerHTML = '<svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                        setTimeout(() => {
                            btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>';
                        }, 1500);
                    } catch (_) {}
                },
            }));
        });
    </script>
</x-app-layout>
