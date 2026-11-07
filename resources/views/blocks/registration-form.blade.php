{{-- The registration (#109): choose, fill in, pay. Inside the upplaga's page component, whose state it binds to. --}}
@use('App\Support\Format')
@use('App\Support\Money')
<form wire:submit="register" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start" novalidate>
    <div class="flex flex-col gap-10">
        <section aria-labelledby="steg-1" class="flex scroll-mt-6 flex-col gap-4" id="steg-1-section">
            <h2 id="steg-1" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
                <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">1</span>
                {{ __('registration.step_choose') }}
            </h2>

            @foreach ($races as ['race' => $race, 'price' => $racePrice, 'until' => $until, 'next' => $next, 'soon' => $soon])
                @php($available = $racePrice !== null && ! $soon)
                <fieldset wire:key="race-{{ $race->id }}" class="rounded-card bg-white p-5 shadow-rest sm:p-7" @disabled(! $available)>
                    <legend class="sr-only">{{ $race->name }}</legend>
                    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                        <h3 class="text-[21px] font-medium" aria-hidden="true">{{ $race->name }}</h3>
                        <div class="sm:text-right">
                            @if ($available)
                                <p class="text-[21px] font-medium tabular-nums">{{ Money::format($racePrice, $currency) }}</p>
                                @if ($next)
                                    <p class="text-[14px] text-ink-soft">{{ __('registration.price_then', ['date' => Format::shortDate($until), 'price' => Money::format($next->amount, $currency)]) }}</p>
                                @elseif ($until)
                                    <p class="text-[14px] text-ink-soft">{{ __('registration.open_until', ['date' => Format::shortDate($until)]) }}</p>
                                @endif
                            @else
                                <p class="rounded-pill bg-peach-light px-3 py-1 text-[14px] font-medium text-peach-deep">{{ $soon ? __('registration.opens_soon') : __('registration.closed') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="mt-5">
                        @include('livewire.public.class-choices', ['classes' => $race->raceClasses, 'name' => 'participants.0.raceClassId', 'key' => 'class', 'disabled' => ! $available])
                    </div>
                </fieldset>
            @endforeach
        </section>

        <section aria-labelledby="steg-2" class="flex flex-col gap-4">
                <h2 id="steg-2" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
                    <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">2</span>
                    {{ count($participants) > 1 ? __('registration.step_who_many') : ($lines[0]['class']?->team_size ? __('registration.step_team') : __('registration.step_who')) }}
                </h2>
                {{-- Only before anyone has a class: once there are several, removing the first must not hide the rest. --}}
                @if (! $lines[0]['class'] && count($participants) === 1)
                    <p class="rounded-card bg-white/60 px-5 py-4 text-[15px] text-ink-soft sm:px-7">{{ __('registration.who_after_class') }}</p>
                @else
                @foreach ($participants as $i => $participant)
                    @php($line = $lines[$i])
                    @php($teamSize = $line['class']?->team_size)
                    <div wire:key="deltagare-{{ $i }}" role="group" aria-labelledby="deltagare-{{ $i }}" class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7">
                        <div class="flex items-baseline justify-between gap-4">
                            <h3 id="deltagare-{{ $i }}" tabindex="-1" class="text-[17px] font-medium outline-none">{{ __('registration.participant_n', ['n' => $i + 1]) }}</h3>
                            @if (count($participants) > 1)
                                <button type="button" wire:click="removeParticipant({{ $i }})" class="text-[15px] text-ink-soft underline decoration-contour hover:text-ink">{{ __('registration.remove') }}<span class="sr-only"> {{ __('registration.participant_n', ['n' => $i + 1]) }}</span></button>
                            @endif
                        </div>

                        @if ($i === 0)
                            {{-- The first participant's class is the one pressed on the race cards above. --}}
                            @if ($line['class'])
                                <p class="-mt-3 text-[15px] text-ink-soft">{{ $line['class']->race?->name }} – {{ $line['class']->name }} · <a href="#steg-1" class="underline decoration-contour hover:text-ink">{{ __('registration.change_class') }}</a></p>
                            @else
                                <p class="-mt-3 text-[15px] text-ink-soft"><a href="#steg-1" class="underline decoration-contour hover:text-ink">{{ __('registration.choose_class_above') }}</a></p>
                            @endif
                        @else
                            {{-- The same pills as on the race cards, one race at a time: never one long list of every class. --}}
                            @php($raceId = (int) ($participant['raceId'] ?: $line['class']?->race_id))
                            @if ($races->count() > 1)
                                <fieldset class="flex flex-col">
                                    <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.race') }}</legend>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($races as ['race' => $race, 'price' => $racePrice, 'soon' => $soon])
                                            @php($available = $racePrice !== null && ! $soon)
                                            <label wire:key="lopp-{{ $i }}-{{ $race->id }}">
                                                <input type="radio" name="participants.{{ $i }}.raceId" value="{{ $race->id }}" wire:model.live="participants.{{ $i }}.raceId" @checked($raceId === $race->id) class="peer sr-only" @disabled(! $available)>
                                                <span class="flex min-h-11 cursor-pointer items-center gap-2 rounded-pill border-[1.5px] border-contour bg-white px-4 text-[15px] font-medium transition-colors peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand peer-disabled:cursor-not-allowed peer-disabled:opacity-60 hover:border-ink-soft">
                                                    {{ $race->name }}@if ($available)<span class="font-normal tabular-nums opacity-80">{{ Money::format($racePrice, $currency) }}</span>@endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @else
                                @php($raceId = $races->first()['race']->id)
                            @endif
                            @if ($raceId)
                                @php($raceRow = $races->firstWhere('race.id', $raceId))
                                <fieldset class="flex flex-col">
                                    <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.class') }}</legend>
                                    @include('livewire.public.class-choices', ['classes' => $raceRow['race']->raceClasses, 'name' => "participants.{$i}.raceClassId", 'key' => "klass-{$i}", 'disabled' => $raceRow['price'] === null || $raceRow['soon']])
                                </fieldset>
                            @endif
                        @endif
                        @error("participants.{$i}.raceClassId")<p class="-mt-2 text-[15px] font-medium text-danger">{{ $message }}</p>@enderror

                        @if ($teamSize)
                            <flux:input wire:model="participants.{{ $i }}.teamName" :label="__('registration.team_name')" />
                            <div class="grid gap-4 sm:grid-cols-2">
                                @for ($n = 0; $n < $teamSize; $n++)
                                    <flux:input wire:key="member-{{ $i }}-{{ $n }}" wire:model="participants.{{ $i }}.members.{{ $n }}" :label="__('registration.team_member_n', ['n' => $n + 1])" autocomplete="off" />
                                @endfor
                            </div>
                            <flux:text class="text-[15px]">{{ __('registration.team_help') }}</flux:text>
                            <h4 class="pt-2 text-[17px] font-medium">{{ __('registration.team_contact') }}</h4>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="participants.{{ $i }}.firstName" :label="__('registration.first_name')" :autocomplete="$i === 0 ? 'given-name' : 'off'" />
                            <flux:input wire:model="participants.{{ $i }}.lastName" :label="__('registration.last_name')" :autocomplete="$i === 0 ? 'family-name' : 'off'" />
                            <flux:input wire:model.live.blur="participants.{{ $i }}.birthDate" :label="__('registration.birth_date')" :description:trailing="__('registration.birth_date_help')" placeholder="ÅÅÅÅ-MM-DD" inputmode="numeric" :autocomplete="$i === 0 ? 'bday' : 'off'" maxlength="10" />
                            <fieldset class="flex flex-col">
                                <legend class="mb-2 text-sm leading-tight font-medium text-zinc-800">{{ __('registration.gender') }}</legend>
                                <div class="flex gap-2">
                                    @foreach (['K' => __('registration.gender_k'), 'M' => __('registration.gender_m')] as $value => $label)
                                        <label class="flex-1">
                                            <input type="radio" name="participants.{{ $i }}.gender" value="{{ $value }}" wire:model.live="participants.{{ $i }}.gender" class="peer sr-only">
                                            <span class="flex h-12 cursor-pointer items-center justify-center rounded-pill border-[1.5px] border-contour text-base font-medium peer-checked:border-brand peer-checked:bg-brand peer-checked:text-brand-contrast peer-focus-visible:outline-3 peer-focus-visible:outline-offset-3 peer-focus-visible:outline-brand hover:border-ink-soft">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error("participants.{$i}.gender")<p class="mt-2 text-sm font-medium text-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        </div>
                        @if ($line['ageGroup'])
                            <p class="text-[15px] text-ink-soft" aria-live="polite">{{ __('registration.your_age_group', ['group' => $line['ageGroup']]) }}</p>
                        @endif

                        <flux:input wire:model="participants.{{ $i }}.club" list="clubs" autocomplete="off" :label="__('registration.club_optional')" :description:trailing="__('registration.club_help')" />

                        {{-- A company's or club's code (#85), checked as soon as it is written. --}}
                        <div class="flex flex-col gap-2 sm:max-w-xs">
                            <flux:input wire:model.live.blur="participants.{{ $i }}.code" :label="__('registration.code_optional')" autocomplete="off" autocapitalize="characters" spellcheck="false" />
                            @if ($line['campaign'])
                                <p class="flex items-center gap-1.5 text-[15px] font-medium text-moss" aria-live="polite"><flux:icon.check-circle variant="mini" class="size-5" />{{ __('registration.code_applied', ['campaign' => $line['campaign']->name]) }}</p>
                            @elseif ($line['codeError'] && ! $errors->has("participants.{$i}.code"))
                                <p class="text-[15px] font-medium text-danger" aria-live="polite">{{ __("registration.{$line['codeError']}") }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
                <datalist id="clubs">
                    @foreach ($clubs as $name)
                        <option value="{{ $name }}">
                    @endforeach
                </datalist>

                @if (count($participants) < $max)
                    <button type="button" wire:click="addParticipant" class="inline-flex h-11 items-center gap-2 self-start rounded-pill bg-white px-5 text-[15px] font-semibold text-ink shadow-rest hover:shadow-hover">
                        <flux:icon.plus class="size-4" />{{ __('registration.add_participant') }}
                    </button>
                @endif

                <div role="group" aria-labelledby="kontakt" class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7">
                    <h3 id="kontakt" class="text-[17px] font-medium">{{ __('registration.contact') }}</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="email" type="email" :label="__('registration.email')" :description:trailing="__('registration.email_help')" autocomplete="email" />
                        <flux:input wire:model="phone" type="tel" inputmode="tel" :label="__('registration.phone_optional')" autocomplete="tel" />
                    </div>
                </div>
                @endif
        </section>
    </div>

    <aside aria-labelledby="steg-3" x-intersect:enter="summaryInView = true" x-intersect:leave="summaryInView = false" class="flex scroll-mt-6 flex-col gap-4 lg:sticky lg:top-6" id="steg-3-section">
        <h2 id="steg-3" class="flex scroll-mt-6 items-center gap-3 text-[21px] font-medium sm:text-[26px]">
            <span aria-hidden="true" class="grid size-8 place-items-center rounded-full bg-brand text-[15px] font-semibold text-brand-contrast">3</span>
            {{ __('registration.step_pay') }}
        </h2>
        <div class="flex flex-col gap-5 rounded-card bg-white p-5 shadow-rest sm:p-7" aria-live="polite">
            @if ($lines[0]['class'])
                <ul class="flex flex-col gap-3">
                    @foreach ($lines as $i => $line)
                        <li wire:key="rad-{{ $i }}" class="flex items-baseline justify-between gap-4">
                            <span class="flex flex-col">
                                <span class="text-[17px] font-medium">{{ trim($participants[$i]['firstName'].' '.$participants[$i]['lastName']) ?: __('registration.participant_n', ['n' => $i + 1]) }}</span>
                                <span class="text-[15px] text-ink-soft">{{ $line['class'] ? $line['class']->race?->name.' – '.$line['class']->name.', '.Format::time($line['class']->start_at->setTimezone($event->timezone)) : __('registration.no_class') }}</span>
                                @if ($line['campaign'])<span class="text-[14px] text-moss">{{ $line['campaign']->name }}</span>@endif
                            </span>
                            <span class="text-[17px] tabular-nums">{{ $line['price'] === null ? '–' : Money::format($line['price'], $currency) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="-mt-2 text-[15px] text-ink-soft">{{ Format::date($event->date) }}</p>
                <div class="flex items-baseline justify-between border-t border-contour pt-4">
                    <span class="text-[15px] text-ink-soft">{{ __('registration.total') }}</span>
                    <span class="text-[26px] font-medium tabular-nums">{{ Money::format($total, $currency) }}</span>
                </div>

                <div class="flex flex-col gap-3">
                    <flux:checkbox wire:model="acceptTerms" :label="__('privacy.accept_terms')" />
                    <p class="text-[14px] text-ink-soft">{{ __('registration.publication_notice') }}
                        <a href="{{ route('public.terms', $event->organizer->slug) }}" target="_blank" class="underline">{{ __('privacy.read_terms') }}<span class="sr-only"> {{ __('public.new_tab') }}</span></a>
                    </p>
                </div>

                @if ($errors->any())
                    {{-- Where the runner pressed pay; each line takes them to its field, naming the participant when there are several. --}}
                    <div role="alert" tabindex="-1" x-init="$el.focus()" class="rounded-[18px] bg-danger-surface px-5 py-4 text-danger outline-none">
                        <h3 class="text-[15px] font-semibold">{{ __('registration.errors_heading') }}</h3>
                        <ul class="mt-2 flex flex-col gap-1 text-[15px]">
                            {{-- In the order of the form: participant by participant, then contact and terms. --}}
                            @php($fields = ['raceClassId', 'teamName', 'members', 'firstName', 'lastName', 'birthDate', 'gender', 'club'])
                            @foreach (collect($errors->messages())->sortBy(fn ($m, $field) => preg_match('/^participants\.(\d+)\.(\w+)(?:\.(\d+))?/', $field, $p)
                                ? sprintf('%03d-%02d-%02d', $p[1], array_search($p[2], $fields), $p[3] ?? 0)
                                : '999-'.array_search($field, ['email', 'phone', 'acceptTerms'])) as $field => $messages)
                                @php($who = count($participants) > 1 && preg_match('/^participants\.(\d+)\./', $field, $m) ? __('registration.participant_n', ['n' => $m[1] + 1]).': ' : '')
                                <li>
                                    <a href="#" class="underline" @click.prevent="
                                        const field = [...document.querySelectorAll('input, select, textarea')]
                                            .find(el => [...el.attributes].some(a => a.name.startsWith('wire:model') && a.value === @js($field)))
                                            ?? document.querySelector('[name=' + CSS.escape(@js($field)) + ']');
                                        field?.scrollIntoView({ block: 'center' }); field?.focus();
                                    ">{{ $who.$messages[0] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-pill bg-brand px-6 text-[15px] font-semibold text-brand-contrast hover:opacity-90 disabled:opacity-60" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="register">{{ $invoiced ? __('registration.submit_invoice') : ($total ? __('registration.pay', ['price' => Money::format($total, $currency)]) : __('registration.submit_free')) }}</span>
                    <span wire:loading wire:target="register">{{ __('registration.working') }}</span>
                </button>
                @if ($invoiced)
                    <p class="text-[13px] text-ink-soft">{{ __('registration.invoiced_by', ['name' => $invoiced->invoice_name ?: $invoiced->name]) }}</p>
                @elseif ($total)
                    <p class="text-[13px] text-ink-soft">{{ __('registration.secure_payment') }}</p>
                @endif
            @else
                <p class="text-[15px] text-ink-soft">{{ $open ? __('registration.choose_first') : __('registration.not_open') }}</p>
            @endif
        </div>
    </aside>
</form>
