<?php

use App\Models\Organizer;
use App\Support\Page;
use App\Support\Theme;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

// The organizer's own page (#117), built from blocks the organizer arranges (#115, #116); the draft behind a signed preview link.
new #[Layout('components.layouts.public')] class extends Component {
    public Organizer $raceOrganizer;

    public bool $preview = false;

    public function mount(string $organizer): void
    {
        $this->raceOrganizer = Organizer::where('slug', $organizer)->firstOrFail();
        $this->preview = request()->boolean('forhandsvisning') && request()->hasValidSignature();
    }

    public function rendering($view): void
    {
        $view->title("{$this->raceOrganizer->name} – Löpning.se")->layoutData(['organizer' => $this->raceOrganizer]);
    }

    public function with(): array
    {
        return [
            'organizer' => $this->raceOrganizer,
            'editor' => false,
            'forms' => Page::forms($this->raceOrganizer),
            'blocks' => Page::for($this->raceOrganizer, $this->preview),
            'logo' => Theme::logoUrl(Theme::for($this->raceOrganizer, null, $this->preview)),
            ...Page::events($this->raceOrganizer),
        ];
    }
}; ?>

<div>
    @include('blocks.page')
</div>
