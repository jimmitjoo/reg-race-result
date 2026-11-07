{{-- The chosen form in a card: to send on the page, to look at in the editor. --}}
@if ($form = $forms->get((int) $block['form_id']))
    <div class="rounded-card bg-white p-6 shadow-rest sm:p-8">
        <livewire:public.form :form-id="$form->id" :disabled="$editor" :key="'form-'.$block['id'].'-'.$form->id.'-'.$form->updated_at?->timestamp" />
    </div>
@elseif ($editor && $block['type'] === 'form')
    <p class="rounded-card border-2 border-dashed border-contour p-10 text-center text-ink-soft">{{ __('page.no_form_yet') }}</p>
@endif
