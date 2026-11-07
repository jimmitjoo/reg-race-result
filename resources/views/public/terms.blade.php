<x-layouts.public :organizer="$organizer" :title="__('privacy.terms_title').' – '.$organizer->name">
    <article class="mx-auto flex max-w-[720px] flex-col gap-8">
        <header class="flex flex-col gap-3">
            <p class="text-[15px] text-ink-soft">{{ $organizer->name }}</p>
            <h1 class="text-[38px] leading-[42px] font-normal tracking-[-0.02em] sm:text-[52px] sm:leading-[56px]">{{ __('privacy.terms_title') }}</h1>
            <p class="text-[17px] text-ink-soft sm:text-[19px]">{{ __('privacy.terms_intro', ['organizer' => $organizer->name]) }}</p>
        </header>

        <div class="flex flex-col gap-6 rounded-card bg-white p-5 shadow-rest sm:p-8">
            @foreach (['collected', 'published', 'why', 'payment'] as $part)
                <section class="flex flex-col gap-1">
                    <h2 class="text-[19px] font-medium">{{ __("privacy.{$part}_heading") }}</h2>
                    <p class="text-[17px] leading-relaxed">{{ __("privacy.{$part}") }}</p>
                </section>
            @endforeach

            <section class="flex flex-col gap-1">
                <h2 class="text-[19px] font-medium">{{ __('privacy.retention_heading') }}</h2>
                <p class="text-[17px] leading-relaxed">{{ __('privacy.retention', ['months' => config('privacy.contact_retention_months')]) }}</p>
            </section>

            <section class="flex flex-col gap-1">
                <h2 class="text-[19px] font-medium">{{ __('privacy.rights_heading') }}</h2>
                <p class="text-[17px] leading-relaxed">{{ __('privacy.rights', ['email' => $email]) }}</p>
            </section>
        </div>
    </article>
</x-layouts.public>
