<x-layouts.app :title="__('timing.title').' – '.$event->name">
    <div
        class="flex max-w-2xl flex-col gap-6"
        x-data="timing(@js([
            'eventId' => $event->id,
            'eventFolder' => $event->name,
            'url' => route('events.timing.reads', $event),
            'csrf' => csrf_token(),
            't' => __('timing'),
        ]))"
    >
        <div>
            <flux:heading size="xl">{{ __('timing.title') }}</flux:heading>
            <flux:subheading>{{ $event->name }}</flux:subheading>
        </div>

        <template x-if="!supported">
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('timing.unsupported_browser')" />
        </template>

        <section x-show="supported" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('timing.box') }}</flux:heading>

            <div class="flex flex-wrap gap-2">
                <flux:badge x-show="folder" color="green" icon="folder-open">{{ __('timing.folder_found') }}</flux:badge>
                <flux:badge x-show="!folder" color="zinc" icon="folder">{{ __('timing.no_folder') }}</flux:badge>
                <flux:badge x-show="folder && online" color="green" icon="wifi">{{ __('timing.internet_ok') }}</flux:badge>
            </div>

            <p x-show="savedFolder" class="text-sm">{{ __('timing.permission_needed') }}</p>
            <p x-show="prepared" class="text-sm" x-text="t.prepared.replace(':folder', folder?.name ?? '')"></p>

            <div x-show="!folder" class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="folder-open" x-on:click="chooseFolder()">{{ __('timing.choose_folder') }}</flux:button>
                <flux:button icon="folder-plus" x-on:click="prepare()">{{ __('timing.prepare') }}</flux:button>
            </div>

            <div x-show="folder" class="flex flex-col gap-2">
                <p x-show="readerNames().length === 0" class="text-sm text-zinc-500">{{ __('timing.no_reader_files') }}</p>

                <template x-for="name in readerNames()" :key="name">
                    <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-4 py-3 dark:bg-zinc-800">
                        <span class="font-semibold" x-text="t.reader.replace(':reader', name)"></span>
                        <span class="text-sm" x-text="t.reads_sent.replace(':count', readers[name].sent)"></span>
                    </div>
                </template>

                <flux:callout x-show="!online" variant="warning" icon="signal-slash">
                    <flux:callout.text x-text="t.internet_down.replace(':count', waiting())"></flux:callout.text>
                </flux:callout>

                <p x-show="online && waiting() === 0 && readerNames().length > 0" class="font-semibold text-green-700 dark:text-green-400">
                    {{ __('timing.all_sent') }}
                </p>
                <p x-show="online && waiting() > 0" class="text-sm">{{ __('timing.sending') }}</p>
            </div>
        </section>
        <section class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('timing.upload_title') }}</flux:heading>
            <flux:text class="text-sm">{{ __('timing.upload_help') }}</flux:text>
            @if (session('upload'))
                <flux:callout variant="success" icon="check-circle" :heading="session('upload')" />
            @endif
            <form method="POST" action="{{ route('events.timing.upload', $event) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">
                @csrf
                <flux:input type="file" name="file" accept=".txt" />
                <flux:button type="submit">{{ __('timing.upload') }}</flux:button>
            </form>
            <flux:error name="file" />
        </section>
    </div>

    @vite('resources/js/timing.js')
</x-layouts.app>
