<?php

use App\Models\Event;
use App\Timing\ChipListParser;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Event $event;

    public string $contents = '';
    public $file = null;
    public ?array $previewRows = null;
    public ?array $header = null;
    public ?int $chipColumn = null;
    public ?int $bibColumn = null;
    public ?string $message = null;

    /** @var array<int, int|string> first bib per race id */
    public array $firstBib = [];

    public function with(): array
    {
        $chipped = $this->event->chips()->pluck('bib')->all();
        $bibs = $this->event->registrations()
            ->whereRelation('raceClass', 'timed', true)
            ->where('registrations.status', '!=', 'dns')
            ->whereNotNull('bib')
            ->pluck('bib');

        return [
            'hasBibs' => $bibs->isNotEmpty(),
            'missing' => $bibs->diff($chipped)->sort()->values(),
            'races' => $this->event->races()->orderBy('name')->get()->map(fn ($race) => [
                'race' => $race,
                'withoutBib' => $this->event->registrations()->whereRelation('raceClass', 'race_id', $race->id)->whereNull('bib')->count(),
            ]),
        ];
    }

    public function updatedFile(): void
    {
        $this->contents = (string) file_get_contents($this->file->getRealPath());
        $this->preview();
    }

    public function preview(): void
    {
        $list = ChipListParser::parse($this->contents);
        $this->header = $list->header;
        $this->previewRows = array_slice($list->rows, 0, 5);
        $this->chipColumn = $list->chipColumn;
        $this->bibColumn = $list->bibColumn;
        $this->message = null;
    }

    public function import(): void
    {
        $this->validate(['chipColumn' => ['required', 'integer'], 'bibColumn' => ['required', 'integer']]);
        if ($this->chipColumn === $this->bibColumn) {
            $this->addError('chipColumn', __('chips.different_columns'));

            return;
        }

        $count = 0;
        DB::transaction(function () use (&$count) {
            foreach (ChipListParser::parse($this->contents)->rows as $row) {
                $code = trim($row[$this->chipColumn] ?? '');
                $bib = trim($row[$this->bibColumn] ?? '');
                if ($code === '' || ! ctype_digit($bib) || (int) $bib === 0) {
                    continue;
                }

                $this->event->chips()->where(fn ($q) => $q->where('code', $code)->orWhere('bib', (int) $bib))->delete();
                $this->event->chips()->create(['code' => $code, 'bib' => (int) $bib]);
                $count++;
            }
        });

        $this->reset('contents', 'file', 'previewRows', 'header', 'chipColumn', 'bibColumn');
        $this->message = __('chips.imported', ['count' => $count]);
    }

    public function assignBibs(int $raceId): void
    {
        $race = $this->event->races()->findOrFail($raceId);
        $this->validate(["firstBib.{$raceId}" => ['required', 'integer', 'min:1']]);

        $used = array_flip($this->event->registrations()->whereNotNull('bib')->pluck('bib')->all());
        $next = (int) $this->firstBib[$raceId];
        $count = 0;

        $waiting = $this->event->registrations()->whereRelation('raceClass', 'race_id', $race->id)->whereNull('bib')
            ->orderBy('registrations.created_at')->orderBy('registrations.id')->get();

        foreach ($waiting as $registration) {
            while (isset($used[$next])) {
                $next++;
            }
            $registration->update(['bib' => $next]);
            $used[$next] = true;
            $count++;
        }

        $this->message = __('chips.assigned', ['count' => $count]);
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('chips.title') }}</flux:heading>
        <flux:subheading>{{ $event->name }}</flux:subheading>
    </div>

    @if ($message)
        <flux:callout variant="success" icon="check-circle" :heading="$message" />
    @endif

    <section class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('chips.status_title') }}</flux:heading>
        @if (! $hasBibs)
            <flux:text>{{ __('chips.no_bibs') }}</flux:text>
        @elseif ($missing->isEmpty())
            <flux:callout variant="success" icon="check-circle" :heading="__('chips.all_have_chips')" />
        @else
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('chips.missing_chips', ['count' => $missing->count()])">
                <flux:callout.text class="font-mono">{{ $missing->implode(', ') }}</flux:callout.text>
            </flux:callout>
        @endif
    </section>

    <section class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading>{{ __('chips.import_title') }}</flux:heading>
        <flux:text>{{ __('chips.import_help') }}</flux:text>
        <flux:textarea wire:model="contents" rows="6" class="font-mono" :label="__('chips.contents')" placeholder="Startnummer;Chip&#10;501;E2003412..." />
        <flux:input type="file" wire:model="file" accept=".csv,.txt,.tsv" :label="__('chips.file')" />
        <div><flux:button wire:click="preview">{{ __('chips.preview') }}</flux:button></div>

        @if ($previewRows !== null)
            @php($columns = max(array_map('count', $previewRows ?: [[]])))
            <table class="text-sm">
                @if ($header)
                    <tr class="text-zinc-500">@foreach ($header as $cell)<th class="pe-4 text-left">{{ $cell }}</th>@endforeach</tr>
                @endif
                @foreach ($previewRows as $row)
                    <tr>@foreach ($row as $cell)<td class="pe-4 font-mono">{{ $cell }}</td>@endforeach</tr>
                @endforeach
            </table>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="chipColumn" :label="__('chips.chip_column')">
                    <option value="">–</option>
                    @for ($i = 0; $i < $columns; $i++)
                        <option value="{{ $i }}">{{ $header[$i] ?? __('chips.column', ['number' => $i + 1]) }}</option>
                    @endfor
                </flux:select>
                <flux:select wire:model="bibColumn" :label="__('chips.bib_column')">
                    <option value="">–</option>
                    @for ($i = 0; $i < $columns; $i++)
                        <option value="{{ $i }}">{{ $header[$i] ?? __('chips.column', ['number' => $i + 1]) }}</option>
                    @endfor
                </flux:select>
            </div>
            <div><flux:button variant="primary" wire:click="import">{{ __('chips.import') }}</flux:button></div>
        @endif
    </section>

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('chips.bibs_title') }}</flux:heading>
        <flux:text>{{ __('chips.bibs_help') }}</flux:text>
        @foreach ($races as ['race' => $race, 'withoutBib' => $withoutBib])
            <div wire:key="bibs-{{ $race->id }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div class="flex-1">
                    <div class="font-semibold">{{ $race->name }}</div>
                    <div class="text-sm text-zinc-500">{{ __('chips.without_bib', ['count' => $withoutBib]) }}</div>
                </div>
                <flux:input wire:model="firstBib.{{ $race->id }}" type="number" min="1" size="sm" :label="__('chips.first_bib')" class="w-32" />
                <flux:button size="sm" wire:click="assignBibs({{ $race->id }})">{{ __('chips.assign') }}</flux:button>
            </div>
        @endforeach
    </section>
</div>
