<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark:bg-zinc-900">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <main class="mx-auto max-w-2xl px-4 py-10">
            {{ $slot }}
        </main>
        @fluxScripts
    </body>
</html>
