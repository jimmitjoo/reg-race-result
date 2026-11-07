@props(['title' => null, 'organizer' => null])
{{-- Löpning.se's quiet frame around an organizer's pages (#118): the organizer leads, the frame gives trust. Always light, as Löpning.se's own pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lp-light">
    <head>
        @include('partials.head', ['title' => $title, 'light' => true])
    </head>
    <body class="lp-public min-h-screen bg-ground text-ink antialiased">
        <a href="#innehall" class="sr-only z-50 rounded-full bg-white px-5 py-3 font-semibold text-ink shadow-raised focus:not-sr-only focus:fixed focus:top-3 focus:left-3">{{ __('public.skip') }}</a>

        <header class="border-b border-contour/70">
            <div class="mx-auto flex h-12 max-w-[1100px] items-center gap-3 px-4 sm:px-6">
                <a href="https://löpning.se" class="flex items-center gap-2 rounded-full text-ink">
                    <x-lopning-logo class="h-4 w-auto" />
                </a>
                <span class="text-[13px] text-ink-soft">{{ __('public.frame') }}</span>
            </div>
        </header>

        <main id="innehall" tabindex="-1" class="mx-auto max-w-[1100px] px-4 pt-8 pb-16 outline-none sm:px-6 sm:pt-12">
            {{ $slot }}
        </main>

        <footer class="border-t border-contour/70">
            <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-x-6 gap-y-2 px-4 pt-6 pb-24 text-[14px] text-ink-soft sm:px-6 lg:pb-6">
                <span>{{ __('public.powered_by') }}</span>
                @if ($organizer)
                    <a href="{{ route('public.terms', $organizer->slug) }}" class="underline decoration-contour hover:text-ink">{{ __('privacy.terms_title') }}</a>
                @endif
                <a href="mailto:{{ config('mail.platform_address') }}" class="underline decoration-contour hover:text-ink">{{ __('public.contact_platform') }}</a>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
