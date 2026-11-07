{{-- A picture and what it shows, for those who cannot see it. --}}
<div class="flex flex-col gap-3">
    <flux:heading size="sm">{{ __('page.image') }}</flux:heading>
    <div class="flex flex-wrap items-center gap-3">
        @if ($block['image'])
            <img src="{{ Storage::disk('public')->url($block['image']) }}" alt="" class="h-16 w-24 rounded-field object-cover">
            <flux:button size="sm" wire:click="removeImage('{{ $block['id'] }}')">{{ __('page.remove_image') }}</flux:button>
        @endif
        <input type="file" wire:model="upload" accept="image/png,image/jpeg,image/webp" class="text-sm" aria-label="{{ __('page.image_upload') }}">
        <span wire:loading wire:target="upload" class="text-sm text-ink-soft">{{ __('page.uploading') }}</span>
    </div>
    <flux:error name="upload" />
    @if ($block['image'])
        <flux:input wire:model.live.debounce.400ms="{{ $field('alt') }}" :label="__('page.alt')" :description="__('page.alt_help')" />
    @endif
</div>
