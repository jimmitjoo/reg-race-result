<x-layouts.public :title="__('privacy.terms_title')">
    <div class="flex flex-col gap-5">
        <flux:heading size="xl">{{ __('privacy.terms_title') }}</flux:heading>
        <flux:text>{{ __('privacy.terms_intro', ['organizer' => $organizer->name]) }}</flux:text>

        @foreach (['collected', 'published', 'why', 'payment'] as $part)
            <section>
                <flux:heading>{{ __("privacy.{$part}_heading") }}</flux:heading>
                <flux:text>{{ __("privacy.{$part}") }}</flux:text>
            </section>
        @endforeach

        <section>
            <flux:heading>{{ __('privacy.retention_heading') }}</flux:heading>
            <flux:text>{{ __('privacy.retention', ['months' => config('privacy.contact_retention_months')]) }}</flux:text>
        </section>

        <section>
            <flux:heading>{{ __('privacy.rights_heading') }}</flux:heading>
            <flux:text>{{ __('privacy.rights', ['email' => $email]) }}</flux:text>
        </section>
    </div>
</x-layouts.public>
