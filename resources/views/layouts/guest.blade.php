<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CPAFlow') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 bg-gradient-to-br from-brand-50 via-white to-violet-50">

            {{-- Brand --}}
            <div class="mb-8">
                <a href="/" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 bg-brand-600 rounded-lg flex items-center justify-center shadow-sm">
                        <span class="text-white font-bold text-sm">C</span>
                    </div>
                    <span class="text-xl font-bold text-gray-900 tracking-tight">CPAFlow</span>
                </a>
            </div>

            {{-- Card --}}
            <div class="w-full max-w-md animate-fade-in">
                <div class="bg-white rounded-card shadow-card border border-gray-200 px-6 py-8">
                    {{ $slot }}
                </div>
            </div>

        </div>
    </body>
</html>
