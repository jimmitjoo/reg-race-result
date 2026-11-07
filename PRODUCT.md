# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Runners** who register for a race, alone or with their family, mostly on a phone and often on the way somewhere. They come from a club site, a poster, Google or Löpning.se's race calendar, and later look up the start list, their result and their receipt.
- **Organizers**: a race's club, most often a volunteer who is not technical and works in the admin a few times a year, then intensively in race week. They set up each year's upplaga, open registration, follow sign-ups and money, and (planned, #128) build their own pages.
- **Officials on race day**, outdoors with a tablet or phone, in sun or darkness, often cold and under time pressure: late registration at the table, chips, timing, missing runners and prizes.
- **The platform** (Löpning.se, Audience Growth AB) runs the service, takes a platform fee, and lets clubs in (`organizer:create`, self-registration).

## Product Purpose

Registration, timing and results for clubs' races. A club sets up an arrangemang once and a new upplaga each year, opens registration with payment straight to its own Stripe account, and on race day runs late registration, chip timing, results, and the export to the Swedish athletics federation in the same system. Success is a runner signed up and paid in a couple of minutes on a phone, and a volunteer who can run the club's race without help.

## Positioning

- **Seen on Löpning.se:** a race here is part of Löpning.se, Sweden's running site, and reaches its runners through the race calendar (#71).
- **Built by a club, for clubs:** it grew out of Högby IF's own races (20 arrangemang, about 43,000 registrations since 2016 in their old system) and is made for volunteers, not event agencies.

## Operating Context

- **Yearly cycle:** an arrangemang comes back every year; Ny upplaga copies races, classes and prices (#84). Registration opens months ahead, with price steps by date, and peaks in the last weeks.
- **Race day:** late registration and Swish at the table, chip readers (RFIDServer, #2–#7), manual finish times, results published live and as PDF, and the federation file (SFIF).
- **Money:** Stripe Checkout as direct charges on the club's connected account (Accounts v2) with a platform fee; free races and on-site Swish; invoicing for schools and companies planned outside Stripe (#85).
- **Data from the old system:** `php artisan import:flexit` brings Högby IF's arrangemang and registrations over from a Flexit SQL Server backup (#14).
- **Environments:** Laravel 12, Livewire 3 / Volt and Flux on SQLite locally; production and staging per #29.

## Capabilities and Constraints

- **Terminology (Swedish, used in the interface):** *arrangör* (the club), *arrangemang* (Sylvesterloppet, every year), *upplaga* (Sylvesterloppet 2026), *lopp* (a start with its own price: Lilla Sylvesterloppet), *klass* (10 km Kvinnor), *lagklass* (a registration is a whole team).
- **Registration** per lopp with price steps, age groups counted from the birth date, teams, and (planned) several participants per order (#109), campaign codes (#85) and consent for mailings (#110).
- **Privacy:** no personnummer is ever asked for; email and phone are deleted 12 months after the race; a runner can hide their name in public lists (#13). Registration data never leaves the organizer it belongs to.
- **Accessibility and law:** WCAG 2.2 AA throughout; the European Accessibility Act applies to registration with payment.
- **Decided 2026-11-07 (#128):** organizers can design their own pages and each race's page with images and video, within frames: blocks in any order and a theme per organizer, no free layout or HTML. Organizer pages put the organizer first, with Löpning.se as a quiet frame; the admin has its own Löpning.se identity with the organizer's logo and accent.
- **Undecided:** whether organizers get custom domains (#43), and which payment providers come after Swish.

## Brand Commitments

- **Part of Löpning.se:** the platform frame, the admin and anything that is not an organizer's own page belong to Löpning.se's design system ("Löparens verktyg", `DESIGN-SYSTEM.md` in AudienceGrowth/Lopning), extended here, not reinvented.
- **Voice:** warm and plain Swedish, like a knowledgeable club friend. Say *du*, use short sentences, avoid technical words and hype, and let some joy about the races show without exclamation marks.
- **Organizers' own brands** come first on their pages: their logo, colours and pictures, inside frames that keep every page accessible.

## Evidence on Hand

- Högby IF's data from the old system: 20 arrangemang, 171 upplagor and 43,269 paid registrations from 2016 to 2026, imported locally from the Flexit backup (not to be published as a claim about users of this product).
- Usage facts from that data: about a third of registrations came several to one order; one-time campaign codes were common (5,914 created).
- **Absent:** no testimonials, user counts, satisfaction scores or other clubs' results. Do not invent any.

## Product Principles

1. **Usability first.** A runner is signed up and paid in under two minutes on a phone; a volunteer who has not logged in for a year finds their way without help; an official at the table needs two minutes of instruction.
2. **Simple beats complete.** The fewest steps that are still safe; undo rather than confirm; good defaults so a page looks right before anyone edits it.
3. **The club's race, Löpning.se's trust.** The organizer's identity leads on their pages; the platform guarantees payment, privacy, accessibility and correctness underneath.
4. **True data, never typed twice.** Dates, prices, start times and results come from the system, so pages, mails and schema.org cannot drift from what is true.
5. **Swedish first.** Swedish words, formats ("lördag 31 december", "kl 10.00", "300 kr") and races.

## Accessibility & Inclusion

WCAG 2.2 AA on every page and in every organizer theme, guaranteed by the system rather than by each organizer: contrast is enforced in themes, alt text is required before publishing, video plays only on request and needs captions or a summary, and race-day views work outdoors with large targets. Test with VoiceOver and NVDA, at 200 % zoom and 320 px width.
