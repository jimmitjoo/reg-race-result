<?php

use App\Models\WordPressSite;
use App\Publishing\WordPressPublisher;
use Illuminate\Http\Client\RequestException;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $url = '';
    public string $username = '';
    public string $appPassword = '';
    public string $parentPageId = '';
    public ?string $status = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->organizer_id, 403);
    }

    public function with(): array
    {
        return ['sites' => $this->sites()->orderBy('name')->get()];
    }

    public function add(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:https'],
            'username' => ['required', 'string', 'max:100'],
            'appPassword' => ['required', 'string', 'max:100'],
            'parentPageId' => ['nullable', 'integer', 'min:1'],
        ]);

        WordPressSite::create([
            'organizer_id' => auth()->user()->organizer_id,
            'name' => $this->name,
            'url' => rtrim($this->url, '/'),
            'username' => $this->username,
            'app_password' => $this->appPassword,
            'results_parent_page_id' => $this->parentPageId ?: null,
        ]);

        $this->reset('name', 'url', 'username', 'appPassword', 'parentPageId');
    }

    public function test(int $id): void
    {
        try {
            WordPressPublisher::test($this->sites()->findOrFail($id));
            $this->status = __('sites.connection_ok');
        } catch (RequestException $e) {
            $this->status = __('sites.connection_failed', ['message' => $e->response->json('message') ?? $e->getMessage()]);
        }
    }

    public function remove(int $id): void
    {
        $this->sites()->findOrFail($id)->delete();
    }

    private function sites()
    {
        return WordPressSite::where('organizer_id', auth()->user()->organizer_id);
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <flux:heading size="xl">{{ __('sites.title') }}</flux:heading>
    <flux:text>{{ __('sites.help') }}</flux:text>

    @if ($status)
        <flux:callout icon="information-circle" :heading="$status" />
    @endif

    <div class="flex flex-col gap-2">
        @forelse ($sites as $site)
            <div wire:key="site-{{ $site->id }}" class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div class="flex-1">
                    <div class="font-semibold">{{ $site->name }}</div>
                    <div class="text-sm text-zinc-500">{{ $site->url }} · {{ $site->username }}</div>
                </div>
                <flux:button size="sm" wire:click="test({{ $site->id }})">{{ __('sites.test') }}</flux:button>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="remove({{ $site->id }})" :aria-label="__('sites.remove')" />
            </div>
        @empty
            <flux:text>{{ __('sites.none') }}</flux:text>
        @endforelse
    </div>

    <form wire:submit="add" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('sites.name')" placeholder="friidrott.hogbyif.se" />
            <flux:input wire:model="url" type="url" :label="__('sites.url')" placeholder="https://friidrott.hogbyif.se" />
            <flux:input wire:model="username" :label="__('sites.username')" />
            <flux:input wire:model="appPassword" type="password" :label="__('sites.app_password')" />
        </div>
        <flux:input wire:model="parentPageId" inputmode="numeric" :label="__('sites.parent_page')" :description="__('sites.parent_page_help')" />
        <div><flux:button type="submit">{{ __('sites.add') }}</flux:button></div>
    </form>
</div>
