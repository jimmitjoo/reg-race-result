{{-- The club, or the upplaga, at the top of its page, in one of the looks the organizer picks; one with a picture needs the picture. --}}
@php($edition ??= null)
@php($name = $edition?->name ?? $organizer->name)
@php($variant = in_array($block['variant'], \App\Support\Page::HEROES, true) ? $block['variant'] : 'brand')
@php($image = $block['image'] ? Storage::disk('public')->url($block['image']) : null)
@if (! $image && in_array($variant, \App\Support\Page::IMAGE_HEROES, true))
    @php($variant = 'brand-full')
@endif
{{-- A full hero first on the page meets the frame's header; an upplaga's has the way back above it. --}}
@php($top = in_array($variant, \App\Support\Page::FULL_HEROES, true) && ($first ?? false) && ! $edition ? '-mt-8 sm:-mt-12' : '')
@include("blocks.hero.{$variant}")
