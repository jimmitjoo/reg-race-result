---
name: Löpning.se Anmälan
description: Registration, timing and results for clubs' races, inside Löpning.se's design system "Löparens verktyg".
colors:
  ground: "#eef0ec"
  surface: "#ffffff"
  contour: "#d9ded6"
  ink: "#18201c"
  ink-soft: "#4f5a53"
  moss: "#1f5c46"
  moss-dark: "#174a38"
  moss-light: "#2f7a5d"
  moss-dark-mode: "#82b59b"
  peach: "#ffc6a3"
  peach-light: "#ffe6cf"
  peach-ink: "#4a3226"
  peach-deep: "#3a2416"
  clay: "#c0612f"
  danger: "#b42318"
  danger-surface: "#fdecea"
  brand: "#1f5c46"
  brand-contrast: "#ffffff"
  brand-surface: "#eef5f1"
typography:
  display:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "38px"
    fontWeight: 400
    lineHeight: "42px"
    letterSpacing: "-0.02em"
  display-sm:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "52px"
    fontWeight: 400
    lineHeight: "56px"
    letterSpacing: "-0.02em"
  section:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "21px"
    fontWeight: 500
  section-sm:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "26px"
    fontWeight: 500
  lead:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 400
  lead-sm:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "19px"
    fontWeight: 400
  body-public:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 400
  button:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 600
  small:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
  small-xs:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
  headline:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 500
  title:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
  body:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
  label-mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace"
    fontFeature: "tnum"
rounded:
  card: "28px"
  panel: "18px"
  field: "12px"
components:
  button-primary:
    backgroundColor: "{colors.moss}"
    textColor: "{colors.surface}"
  button-primary-hover:
    backgroundColor: "{colors.moss-dark}"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.surface}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
  auth-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.panel}"
  badge-waiting:
    backgroundColor: "{colors.peach-light}"
    textColor: "{colors.peach-ink}"
  organizer-accent:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.brand-contrast}"
  button-pill-primary:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.brand-contrast}"
    typography: "{typography.button}"
    rounded: "9999px"
    height: "44px"
    padding: "0 24px"
  button-pill-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.button}"
    rounded: "9999px"
    height: "44px"
    padding: "0 20px"
  button-pill-pay:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.brand-contrast}"
    typography: "{typography.button}"
    rounded: "9999px"
    height: "48px"
    width: "100%"
  card-public:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "20px"
  class-choice:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "9999px"
    height: "44px"
    padding: "6px 16px"
  class-choice-checked:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.brand-contrast}"
  step-marker:
    backgroundColor: "{colors.brand}"
    textColor: "{colors.brand-contrast}"
    typography: "{typography.button}"
    rounded: "9999px"
    size: "32px"
  chip-not-open:
    backgroundColor: "{colors.peach-light}"
    textColor: "{colors.peach-deep}"
    typography: "{typography.small}"
    rounded: "9999px"
    padding: "4px 12px"
  error-summary:
    backgroundColor: "{colors.danger-surface}"
    textColor: "{colors.danger}"
    rounded: "{rounded.card}"
    padding: "20px 24px"
---

# Design System: Löpning.se Anmälan

## Overview

**Creative North Star: "Löparens verktyg"**

This app does not have a visual world of its own. It inherits Löpning.se's established design system, "Löparens verktyg" (`DESIGN-SYSTEM.md` in AudienceGrowth/Lopning), and extends it for registration, timing and results. A calm green-gray ground carries white surfaces and ink text. Moss green does things (buttons, chosen states, links, focus). Peach warms and points things out. Everything is set in one typeface, Hanken Grotesk, and the forms are rounded and soft, with green-tinted shadows. When Löpning.se's document and this file disagree on a platform token, Löpning.se wins and this file should be updated to match.

The system has two layers, both in `resources/css/app.css`. The **platform layer** is Löpning.se's tokens, copied over and mapped onto Flux and Tailwind. Flux's zinc and Tailwind's gray and neutral all resolve to the green-gray neutrals, green resolves to moss, amber to a peach scale, and Flux's accent is moss. The **organizer theme layer** (`--color-brand`, `--color-brand-contrast`, `--color-brand-surface`) reads per-organizer `--brand*` variables. Until an organizer sets them, they fall back to moss. Views take color only from these names. `tests/Feature/DesignTokensTest.php` fails on hex values or other Tailwind palettes in views (PDFs and mails are exempt because they cannot read CSS variables).

Usability comes first. A runner on a phone, a volunteer who hasn't logged in for a year and an official outdoors in the cold all have to manage without help. That is why the system is dense but legible, keeps text contrast at WCAG 2.2 AA everywhere, and has large targets and a visible focus ring on everything.

The public Löpning.se frame (#118) has shipped: the race page with registration, the confirmation page, the start list, results and the terms page now carry Löpning.se's page type ramp, white cards at the card radius, pill buttons and pill choices, and they act in the organizer's brand color. The parts that are planned but not yet built are recorded only as decisions, and their visuals are left open: the organizer theme UI that fills `--brand*` (#112), page blocks for organizer and race pages (#115, #116), and the admin redesign that brings Flux's components onto Löpning.se's shapes (#123).

**Key Characteristics:**
- Inherited, not invented: the Löpning.se tokens are the platform, and organizers theme on top.
- Light green-gray ground, white surfaces, ink text. There is no glass, blur or decorative gradient.
- Moss for action, peach for "something waits for a person", red only for errors and deleting.
- One typeface (Hanken Grotesk, self-hosted, variable 100–900), with tabular numerals wherever times, places and money line up.
- Organizer pages put the organizer first, inside a quiet Löpning.se frame. The admin keeps Löpning.se's identity and adds the organizer's logo and accent.
- Dark mode is the admin's only. Löpning.se's own pages are light.

## Colors

A restrained green-gray world with one acting color (moss) and one warm pointing color (peach), plus a slot where each organizer's own accent goes.

### Primary
- **Moss** (moss): primary buttons, chosen states, links, the focus ring, form caret and accent color. It reaches 7.8:1 on white. Its full 50–950 scale lives in the stylesheet, and Tailwind's green points to it.
- **Deep Moss** (moss-dark): hover on moss buttons.
- **Light Moss** (moss-light): lighter touches on moss.
- **Night Moss** (moss-dark-mode, moss 300): the accent in dark mode, so links and focus stay readable on dark surfaces. Text on a moss-300 fill is moss 950.

### Secondary
- **Peach Light** (peach-light): peach boxes, the text selection background, and the waiting badge.
- **Peach** (peach): hover on peach surfaces, badges.
- **Peach Ink / Peach Deep** (peach-ink, peach-deep): text on peach. Peach Deep is also the selection text color.
- **Clay** (clay): a warm accent, used sparingly. It is amber 600 in the mapped scale.

### Tertiary
- **Organizer Brand** (brand, brand-contrast, brand-surface): the organizer's accent, the text that sits on it, and a quiet tinted surface. Each is `var(--brand*, …)` and falls back to moss, white and moss 50, so an untouched page already looks right. Organizers will set these per organizer and upplaga (#112).

### Neutral
- **Ground** (ground): the page background on Löpning.se surfaces and the auth layouts, and the browser `theme-color`.
- **White** (surface): cards, panels, fields, and buttons on the ground.
- **Contour** (contour): the default border color for every element, plus field edges and dividers.
- **Ink** (ink): body text and headings, and the wordmark on light backgrounds.
- **Ink Soft** (ink-soft): secondary text, descriptions and labels (7.2:1 on white).
- **Green-gray neutrals** (zinc 50–950): Flux draws in zinc, and zinc, gray and neutral are all this one ramp. Zinc 100 equals ground, 600 equals ink-soft and 900 equals ink.
- **Danger** (danger, danger-surface): error callouts and delete buttons.

### Named Rules
**The Moss Does Things Rule.** Moss marks what can be acted on or what is chosen. It is not used as decoration.

**The Waiting Peach Rule.** In the admin, peach (Flux's amber) means that something waits for a person, such as an unpaid registration or an upplaga that needs Stripe before it can open. Don't use peach for anything else there. On public pages peach is for tips, calls to action and pointing something out, such as a race that is not open yet.

**The Red Means Stop Rule.** Red is for errors and deleting, nothing else. A status that is not an error gets a neutral or moss badge.

**The Contrast Floor Rule.** Secondary text is ink-soft. Nothing lighter than ink-soft sits on white or ground. Every organizer theme is checked against WCAG 2.2 AA (4.5:1 for text), and the system enforces this, not the organizer.

**The Tokens Only Rule.** Views use token names only. Hex values and other Tailwind palettes in a view are a test failure.

**The Theme Layer Rule (#112).** An organizer's theme, and an upplaga's on top of it, sets `--brand`, `--brand-contrast` and `--brand-surface` (and the matching `--color-brand*`) in the public pages' head, through `App\Support\Theme`. The picked colour is never used as it is: it is darkened, keeping hue and saturation, until white text on it reaches 4.5:1 and it stands out on the ground at 3:1 (tested over the whole colour wheel), and the organizer is told which tone is used. The accent colour only tints `--brand-surface`. A page without a theme writes no CSS at all and looks as Löpning.se made it.

## Typography

**Body Font:** Hanken Grotesk (with ui-sans-serif, system-ui, sans-serif), self-hosted from `public/fonts/hanken-grotesk` as one variable latin file that covers å, ä and ö, preloaded in the head, `font-display: swap`.
**Label/Mono Font:** the platform monospace, for bib numbers, chip IDs and finish times.

**Character:** a single friendly grotesk does everything. Weight comes from size, not from bold.

**Theme fonts (#112):** an organizer can choose one of seven curated pairs, self-hosted as latin WOFF2 in `public/fonts/theme` (OFL 1.1): Löpning.se (Hanken Grotesk), Modern (Inter), Sportig (Barlow Condensed with Barlow), Klassisk (Source Serif 4 with Source Sans 3), Varm (Fraunces with Work Sans), Rak (Archivo) and Vänlig (Nunito). The pair sets `--font-sans` for body text and `--font-display` for `h1`–`h3` on public pages; only the chosen pair's faces are loaded.

### Hierarchy
Two ramps share one typeface. Public pages (Löpning.se's own ramp, #118) set sizes in exact pixels. The admin uses Flux's sizes.

**Public pages**
- **Display** (400, 38px / 42px, 52px / 56px from sm, -0.02em, balanced): the one page title (h1): the race name, Startlista, Resultat, the confirmation heading, the terms title. It is large but light, so the weight comes from size.
- **Section** (500, 21px, 26px from sm): step headings and other h2s. At 21px fixed it is also the race card and class card heading and the price on a race card. The sum in the pay step is 26px.
- **Reading heading** (500, 19px): h2 on long reading pages (terms).
- **Lead** (400, 17px, 19px from sm, ink-soft): the line under the page title (date · place, or what happens next). At 17px it is also reading body text (terms, `leading-relaxed`), confirmation values, the chosen class, and empty-state boxes.
- **Body and controls** (400, 15px): table text, help text, labels in definition lists, the organizer's name above the title, class names (500) in choices. Buttons are 15px semibold (600).
- **Small** (400, 14px or 13px, ink-soft): price-step notes, the club under a name, footer links (14px); table column heads, start times inside a class pill, "Uppdateras automatiskt" and the frame bar label (13px).

**Admin (Flux)**
- **Headline** (500, 1.5rem): page headings (Flux heading `xl`).
- **Title** (500, 1rem): section headings (Flux heading `lg`).
- **Body** (400, 0.875rem): Flux's default text, form labels and table text. Descriptions use ink-soft.
- **Label Mono** (monospace, tabular): bibs, chips and times in tables and race-day views. The public tables use tabular numerals in the body face instead.

### Named Rules
**The Light Headline Rule.** On public pages, headings are large and light (weight 400 for the page title, 500 for sections). Weight comes from size, never from bold display type.

**The Columns Line Up Rule.** Every table uses tabular numerals, so times, places and money line up.

**The Swedish Formats Rule.** Dates, times and money are written only through `App\Support\Format` and `App\Support\Money`: "torsdag 31 december 2026", "31 dec 2026", "kl 10.00", "kl 10.03.30" (seconds only when they matter, as in wave starts), and "300 kr" (öre only when present). Classes sort naturally (P5, P7, P9, P11).

## Layout

**Public frame (#118).** Every public page sits on the ground, always light, inside a quiet Löpning.se frame. At the top is a thin bar 48px high: the wordmark at 16px high in ink, plus a 13px ink-soft label, over a contour/70 rule. At the bottom is a footer over the same rule, with "powered by", the organizer's terms and platform contact in 14px ink-soft underlined links. A skip link ("Till innehållet") appears as a white pill with the raised shadow on focus. Content is `max-w-[1100px]` with 16px / 24px side padding (from sm), 32px / 48px top padding and 64px bottom padding. Reading pages (confirmation, terms) narrow to `max-w-[720px]`.

**Page header.** The organizer's name or the parent race (15px ink-soft, a link back when there is one) sits above the display title, followed by the lead line (date · city) and a row of pill buttons. This is real data, not an invented kicker.

**Registration page.** One form in three numbered steps (choose, who runs, pay). From `lg` it is a two-column grid, `minmax(0,1fr) 340px`, with the pay step sticky in the right column. On phones, once a class is chosen, a fixed bottom bar (white/95 over a contour rule) holds the pay button until the pay step is in view. The gaps are 48px between page blocks, 40px between steps, 16px inside a step, and 8px between pills.

**Admin.** The admin is a Flux sidebar layout: a sticky sidebar, collapsible on small screens, next to the content. The auth layouts center one column (`max-w-sm` or `max-w-md`) on the ground with 24–40px padding. Spacing comes from `gap-*`: 8px within groups, 16px between fields, 24–32px between sections.

Targets are at least 44px high (`h-11`), and the main pay action is 48px (`h-12`). Layouts must work at 320px width and 200% zoom.

## Elevation & Depth

Depth is soft and green-tinted, never gray or black. Surfaces sit on the ground with a faint moss-tinted shadow, links lift a step on hover, and menus and dialogs are raised. Public pages lift white cards off the ground. Most admin surfaces today are flat, with contour borders. The exact values are in the sidecar.

### Shadow Vocabulary
- **Rest** (`box-shadow: 0 6px 20px rgb(31 92 70 / 0.08)`): cards and panels at rest, and white pill buttons on the ground. Every public card and the auth card use it.
- **Hover** (`box-shadow: 0 12px 28px rgb(31 92 70 / 0.14)`): white pill buttons and link cards on hover.
- **Raised** (`box-shadow: 0 18px 40px rgb(31 92 70 / 0.16)`): menus, dialogs, and the skip link when it shows.

### Named Rules
**The Green Shadow Rule.** Shadows are tinted with moss. Don't use hard offset shadows, glass or blur.

## Shapes

The form language is rounded and soft, with three named radii and the full pill: **card** (28px) for every public card (race cards, the who-runs and pay steps, start list and result classes, the confirmation, terms, peach boxes and the error summary); **panel** (18px) for admin surfaces and the auth card; **field** (12px) for admin inputs; and **pill** (`--radius-pill`, fully rounded) for buttons, class and gender choices, step markers and the "not open" chip on public pages. Choices are outlined with a 1.5px contour border. All borders default to contour. On public pages (`.lp-public`) Flux's text inputs and selects take the pill too: 48px high, a 1.5px contour border, 20px side padding, 16px text (no zoom on focus in iOS), a leading icon inset into the curve in ink-soft, and a single ink-soft chevron on selects. Flux's admin components still render their own radii (8px), and bringing them onto field and pill is the admin redesign's job (#123). An organizer's theme (#112) can choose another shape: **rounded** (card 16px, panel 12px, field 10px, pill 12px) or **straight** (4px throughout). Public views therefore use `rounded-pill`, not `rounded-full`, except for the round step markers.

## Components

Flux provides the components. The design system reaches them through tokens: accent, zinc, green and amber are all remapped, so Flux's default variants already speak Löpning.se colors.

### Public pages (#118)
Everything that acts on a public page is drawn in the organizer's brand (`bg-brand` / `text-brand-contrast`, moss by default). Everything else is ink on white or ground. Public pages are always light.

- **Pill button, primary:** brand fill, brand-contrast text, 44px high, 24px side padding, 15px semibold. Hover is 90% opacity. The pay button is the same at 48px high and full width, and says the price ("Betala 300 kr").
- **Pill button, secondary:** white with ink text and the rest shadow, 44px high, 20px side padding. On hover it lifts to the hover shadow. Used for Startlista, Resultat, earlier upplagor and "back to the race".
- **Card:** white at the card radius with the rest shadow on the ground, 20px padding (28px from sm, 32px on terms). An empty or not-yet state is a quieter white/60 box at the same radius, with no shadow.
- **Class choice:** a native radio hidden behind a pill label at least 44px high, white with a 1.5px contour border. It shows the class name (15px, 500) over the start time (13px, 80% opacity). Hover darkens the border to ink-soft. When checked it takes the brand fill and brand-contrast text. The focus ring is a 3px brand outline at a 3px offset. Disabled is 60% opacity. Gender uses the same pill, split evenly in two.
- **Step marker:** a 32px brand circle holding the step number (15px semibold, `aria-hidden`), placed before the step's h2.
- **Table in a card:** a visually hidden caption, `th scope="col"` heads at 13px ink-soft weight 500, rows divided by contour, 15px text, tabular numbers. Secondary columns collapse under the name on phones. A linked start-list row (`:target`) is highlighted in peach-light.
- **"Not open" chip:** a peach-light pill with peach-deep 14px medium text ("Öppnar snart", "Stängd"), in place of the price on a race card. Peach on public pages points things out. The same pairing at the card radius makes the "results not public yet" box.
- **Error summary:** a danger-surface box at the card radius with danger text: a semibold heading and a bulleted list of messages at 15px. It is `role="alert"`, and it takes focus and scrolls into view after a failed submit. The field errors below it are 15px medium danger text.
- **Frame:** see Layout. The wordmark links to löpning.se.

### Buttons
- **Primary:** moss with white text, and deep moss on hover. In dark mode it is moss 300 with moss 950 text.
- **Ghost:** transparent with ink text and a faint neutral hover, for secondary actions in lists.
- **Danger:** red, only for deleting, and it always asks first.
- **Focus:** a 3px accent outline at a 3px offset, global on every focusable element.

### Chips
- **Style:** Flux badges. Green (moss) for done or good, amber (peach) for "waits for a person", zinc for neutral states, and red only for errors.

### Cards / Containers
- **Corner Style:** panel radius (18px) for the auth card, card radius (28px) on public pages.
- **Background:** white on ground. In dark mode it is zinc 950 with a zinc 800 border.
- **Shadow Strategy:** rest shadow (see Elevation & Depth).
- **Border:** contour.
- **Internal Padding:** 40px horizontal by 32px vertical on the auth card.

### Inputs / Fields
- **Style:** Flux inputs with a contour border on white, the label tight above the control, and an 8px gap; help text goes below the control and optional fields say "(valfritt)" in the label. On public pages the inputs are 48px pills (see Shapes).
- **Focus:** the border turns accent, with a 3px accent ring at 25% opacity instead of the outline.
- **Error:** danger text from Flux's error slot.

### Navigation
- **Sidebar:** Flux navlist (outline variant) with outline icons, grouped under one admin heading, and the current page marked by Flux's current state. The Löpning.se wordmark sits at the top in ink (white in dark mode).

### Löpning.se wordmark
Löpning.se's wordmark is an inline SVG drawn in `currentColor`, with `role="img"` and `aria-label="Löpning.se"`. It takes ink on light surfaces and white in dark mode. It appears in the auth layouts and the sidebar. In the admin, the organizer's logo will sit next to it (#112, #123).

## Do's and Don'ts

### Do:
- **Do** take every color from a token (ground, contour, ink, ink-soft, moss, peach, danger, brand), and never from a hex value or another palette in a view.
- **Do** use ink-soft for secondary text on white and ground.
- **Do** keep the global focus ring (3px accent, 3px offset) visible on everything that can take focus.
- **Do** put the organizer first on organizer pages, with their logo, colors and pictures, inside a quiet Löpning.se frame built from blocks and a theme, not free layout.
- **Do** keep Löpning.se's own identity in the admin, adding the organizer's logo and accent.
- **Do** write dates, times and money through Format and Money: "torsdag 31 december 2026", "kl 10.00", "300 kr".
- **Do** write warm, plain Swedish: say du, use short sentences, and leave out technical words, hype and exclamation marks.
- **Do** draw everything that acts on a public page in the organizer's brand (brand / brand-contrast), and everything else in ink on white or ground.
- **Do** set public page titles in the display role (38px / 52px, weight 400, -0.02em) and step headings in the section role (21px / 26px, weight 500).
- **Do** keep targets at least 44px high on race-day views, and keep every page working at 320px width and 200% zoom.

### Don't:
- **Don't** put text lighter than ink-soft on white or ground. Zinc 400 and 500 text on white is below the system's floor.
- **Don't** use peach in the admin for anything except what waits for a person.
- **Don't** use red for anything except errors and deleting.
- **Don't** give Löpning.se's own pages or organizer pages a dark mode. Dark mode belongs to the admin.
- **Don't** let an organizer theme ship a brand and contrast pair below WCAG 2.2 AA.
- **Don't** use glass, blur or gradients as decoration, or the old style's rose, blue, Figtree and Trirong.
