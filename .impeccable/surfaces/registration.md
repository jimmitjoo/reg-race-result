# Surface: public race page and registration (#118)

Routes: `/{organizer}/{arrangemang|upplaga}` (registration), `/start-list`, `/results`, the signed confirmation page, the terms page. Layout: `resources/views/components/layouts/public.blade.php`.

## Brief

- **Mode:** Operate, with a light Persuade opening. The visitor's success is a runner signed up and paid in under two minutes on a phone (PRODUCT.md principle 1).
- **Audience:** runners on a phone, often on the way somewhere; parents registering children; arriving from a club site, a poster, Google or Löpning.se.
- **Task:** choose a class, give who runs, check the sum, pay; afterwards find the start list, the result and the receipt.
- **Proof and content:** only system data (date, place, races, classes, start times, price now and next price step, status). No invented copy, no imagery until #113/#116 give organizers pictures.
- **Constraints:** WCAG 2.2 AA; Swedish formats; up to 20 participants per order, paid once (#109); datalist for clubs until an accessible combobox exists; Flux without Pro components.

## Direction contract

THESIS: the race page is the registration. Choosing is pressing a class on its race card, not reading a list and then scrolling to a select box; the dropdown of every class from every race is refused.
OWN-WORLD: Löpning.se's ground with white cards at the card radius and the rest shadow, ink type in Hanken Grotesk with large light headings, pill buttons and pill class choices; the organizer's brand colour (moss by default) is the only colour that acts.
STORY: the runner sees what, when and where at once, sees what it costs now and when it goes up, presses their class, fills in who runs, sees the sum and pays; the confirmation says what happens next.
FIRST VIEWPORT: a quiet Löpning.se bar; the organizer's name small above the race name at display size; date, place and status; then the first race card begins, its classes as pills, inside the first phone screen.
FORM: one page in three numbered steps (choose, who runs, pay) that open in order, plus a confirmation page; a fixed bottom button on phones until the steps are in view. The first participant's class is pressed on the race cards; each added participant gets a card of their own with the same pills, a race at a time, then the contact details once for the order.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.
