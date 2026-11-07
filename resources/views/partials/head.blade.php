<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="preload" href="/fonts/hanken-grotesk/HankenGrotesk-latin.woff2" as="font" type="font/woff2" crossorigin>
<meta name="theme-color" content="#eef0ec">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
