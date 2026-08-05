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
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <a href="#main-content" class="skip-link">Skip to content</a>

        <div class="min-h-screen">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                <x-flash-message type="success" />
                <x-flash-message type="error" />
                <x-flash-message type="warning" />
            </div>

            <main id="main-content" class="page-enter">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
