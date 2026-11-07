<?php

use App\Mail\FormAnswered;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Organizer;
use App\Models\User;
use App\Support\Page;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

// The organizer's own forms, shown on its page, and what becomes of what people send.
function formOrganizer(): array
{
    $organizer = Organizer::factory()->create(['slug' => 'hogby-if', 'name' => 'Högby IF']);
    $user = User::factory()->create(['organizer_id' => $organizer->id, 'email' => 'kansli@hogbyif.se']);

    return [$organizer, $user];
}

it('builds a form that starts as a contact form, and saves every change', function () {
    [$organizer, $user] = formOrganizer();
    $this->actingAs($user);

    Volt::test('forms.index')->set('name', 'Funktionärer')->call('create')->assertRedirect();
    $form = $organizer->forms()->sole();
    expect(array_column($form->fields, 'type'))->toBe(['text', 'email', 'textarea']);

    Volt::test('forms.edit', ['form' => $form])
        ->call('addField', 'select')->set('fields.3.label', 'Vilket lopp?')->set('fields.3.options', '5 km, 10 km')
        ->call('moveField', 3, -3)->call('removeField', 3)
        ->set('action', 'store')->set('fields.0.label', '')->assertHasErrors('fields.0.label');

    expect($form->fresh())->action->toBe('store')
        ->and(array_column($form->fresh()->fields, 'label'))->toBe(['Vilket lopp?', __('forms.default.name'), __('forms.default.email')]);
});

it('keeps another club out of a form', function () {
    [$organizer] = formOrganizer();
    $form = $organizer->forms()->create(['name' => 'Kontakt', 'fields' => Form::contactFields()]);
    $this->actingAs(User::factory()->create());

    Volt::test('forms.edit', ['form' => $form])->assertNotFound();
});

it('shows the chosen form on the page, and emails and keeps what is sent as the form says', function () {
    Mail::fake();
    [$organizer] = formOrganizer();
    $form = $organizer->forms()->create(['name' => 'Kontakt', 'fields' => Form::contactFields(), 'action' => 'both']);
    [$name, $email, $message] = array_column($form->fields, 'id');
    $organizer->update(['page' => [array_merge(Page::block('contact'), ['form_id' => (string) $form->id])]]);

    $this->get('/hogby-if')->assertSeeLivewire('public.form')->assertSee(__('forms.default.message'));

    Volt::test('public.form', ['formId' => $form->id])
        ->call('send')->assertHasErrors(["answers.{$name}", "answers.{$email}", "answers.{$message}"])
        ->set("answers.{$name}", 'Anna')->set("answers.{$email}", 'anna@example.com')->set("answers.{$message}", 'Finns det dusch?')
        ->call('send')->assertHasNoErrors()->assertSet('sent', true)->assertSee(__('forms.thanks_default'));

    expect($form->responses()->sole()->answers)->toBe([
        ['label' => __('forms.default.name'), 'value' => 'Anna'],
        ['label' => __('forms.default.email'), 'value' => 'anna@example.com'],
        ['label' => __('forms.default.message'), 'value' => 'Finns det dusch?'],
    ]);
    Mail::assertQueued(FormAnswered::class, fn ($mail) => $mail->hasTo('kansli@hogbyif.se') && $mail->hasReplyTo('anna@example.com'));

    // Only emailed: nothing is kept.
    $form->update(['action' => 'email', 'recipient' => 'info@hogbyif.se']);
    Volt::test('public.form', ['formId' => $form->id])->set("answers.{$name}", 'Bo')->set("answers.{$email}", 'bo@example.com')->set("answers.{$message}", 'Hej')->call('send');
    expect($form->responses()->count())->toBe(1);
    Mail::assertQueued(FormAnswered::class, fn ($mail) => $mail->hasTo('info@hogbyif.se'));
});

it('thanks and ignores a robot, stops a flood, and cannot be sent from the editor', function () {
    Mail::fake();
    [$organizer] = formOrganizer();
    $form = $organizer->forms()->create(['name' => 'Kontakt', 'fields' => [Form::field('text', 'Namn', true)]]);
    $id = $form->fields[0]['id'];

    Volt::test('public.form', ['formId' => $form->id])->set('website', 'http://spam.example')->set("answers.{$id}", 'Bot')->call('send')->assertSet('sent', true);
    expect(FormResponse::count())->toBe(0);

    foreach (range(1, 5) as $n) {
        Volt::test('public.form', ['formId' => $form->id])->set("answers.{$id}", "Anna {$n}")->call('send')->assertSet('sent', true);
    }
    Volt::test('public.form', ['formId' => $form->id])->set("answers.{$id}", 'Anna 6')->call('send')->assertHasErrors('form')->assertSet('sent', false);
    expect(FormResponse::count())->toBe(5);

    Volt::test('public.form', ['formId' => $form->id, 'disabled' => true])->call('send')->assertForbidden();
});

it('will not publish a Formulär block without a form', function () {
    [$organizer, $user] = formOrganizer();
    $this->actingAs($user);
    $form = $organizer->forms()->create(['name' => 'Kontakt', 'fields' => Form::contactFields()]);

    $editor = Volt::test('page.edit')->call('add', 'form', 1)->assertSee(__('page.problem.no_form'))->call('publish');
    expect($organizer->fresh()->page)->toBeNull();

    $editor->set('blocks.1.form_id', (string) $form->id)->call('publish');
    expect($organizer->fresh()->page[1]['form_id'])->toBe((string) $form->id);
});

it('deletes kept answers after the retention time', function () {
    [$organizer] = formOrganizer();
    $form = $organizer->forms()->create(['name' => 'Kontakt', 'fields' => Form::contactFields()]);
    $form->responses()->create(['answers' => [], 'created_at' => now()->subMonths(13)]);
    $form->responses()->create(['answers' => []]);

    $this->artisan('schedule:test', ['--name' => 'forms:prune-responses'])->assertSuccessful();
    expect($form->responses()->count())->toBe(1);
});
