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
---

# Design System: Löpning.se Anmälan

## Overview

**Creative North Star: "Löparens verktyg"**

This app does not have a visual world of its own. It inherits Löpning.se's established design system, "Löparens verktyg" (`DESIGN-SYSTEM.md` in AudienceGrowth/Lopning), and extends it for registration, timing and results. A calm green-gray ground carries white surfaces and ink text. Moss green does things (buttons, chosen states, links, focus). Peach warms and points things out. Everything is set in one typeface, Hanken Grotesk, and the forms are rounded and soft, with green-tinted shadows. When Löpning.se's document and this file disagree on a platform token, Löpning.se wins and this file should be updated to match.

The system has two layers, both in `resources/css/app.css`. The **platform layer** is Löpning.se's tokens, copied over and mapped onto Flux and Tailwind. Flux's zinc and Tailwind's gray and neutral all resolve to the green-gray neutrals, green resolves to moss, amber to a peach scale, and Flux's accent is moss. The **organizer theme layer** (`--color-brand`, `--color-brand-contrast`, `--color-brand-surface`) reads per-organizer `--brand*` variables. Until an organizer sets them, they fall back to moss. Views take color only from these names. `tests/Feature/DesignTokensTest.php` fails on hex values or other Tailwind palettes in views (PDFs and mails are exempt because they cannot read CSS variables).

Usability comes first. A runner on a phone, a volunteer who hasn't logged in for a year and an official outdoors in the cold all have to manage without help. That is why the system is dense but legible, keeps text contrast at WCAG 2.2 AA everywhere, and has large targets and a visible focus ring on everything.

The parts that are planned but not yet built are recorded only as decisions, and their visuals are left open: the organizer theme UI that fills `--brand*` (#112), page blocks for organizer and race pages (#115, #116), the public Löpning.se frame around organizer pages (#118), and the admin redesign that brings Flux's components onto Löpning.se's shapes (#123).

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

**The Waiting Peach Rule.** In the admin, peach (Flux's amber) means that something waits for a person, such as an unpaid registration or an upplaga that needs Stripe before it can open. Don't use peach for anything else there. On public pages peach is for tips and calls to action.

**The Red Means Stop Rule.** Red is for errors and deleting, nothing else. A status that is not an error gets a neutral or moss badge.

**The Contrast Floor Rule.** Secondary text is ink-soft. Nothing lighter than ink-soft sits on white or ground. Every organizer theme is checked against WCAG 2.2 AA (4.5:1 for text), and the system enforces this, not the organizer.

**The Tokens Only Rule.** Views use token names only. Hex values and other Tailwind palettes in a view are a test failure.

## Typography

**Body Font:** Hanken Grotesk (with ui-sans-serif, system-ui, sans-serif), self-hosted from `public/fonts/hanken-grotesk` as one variable latin file that covers å, ä and ö, preloaded in the head, `font-display: swap`.
**Label/Mono Font:** the platform monospace, for bib numbers, chip IDs and finish times.

**Character:** a single friendly grotesk does everything. Weight comes from size, not from bold.

### Hierarchy
- **Headline** (500, 1.5rem): page headings in the admin and on public pages (Flux heading `xl`).
- **Title** (500, 1rem): section headings (Flux heading `lg`).
- **Body** (400, 0.875rem): Flux's default text, form labels and table text. Descriptions use ink-soft.
- **Label Mono** (monospace, tabular): bibs, chips and times in tables and race-day views.

Löpning.se's own page ramp (38/52px light h1 with -0.02em tracking) belongs to its public pages. The public frame (#118) will bring it here, and until then this app does not ship it.

### Named Rules
**The Columns Line Up Rule.** Every table uses tabular numerals, so times, places and money line up.

**The Swedish Formats Rule.** Dates, times and money are written only through `App\Support\Format` and `App\Support\Money`: "torsdag 31 december 2026", "31 dec 2026", "kl 10.00", "kl 10.03.30" (seconds only when they matter, as in wave starts), and "300 kr" (öre only when present). Classes sort naturally (P5, P7, P9, P11).

## Layout

The admin is a Flux sidebar layout: a sticky sidebar, collapsible on small screens, next to the content. The auth layouts center one column (`max-w-sm` or `max-w-md`) on the ground with 24–40px padding. Public pages are currently one reading column (`max-w-2xl`, 16px side padding, 40px vertical padding), and forms switch to two columns from `sm`. Spacing comes from `gap-*` between elements: 8px within groups, 16px between fields, 24–32px between sections. Targets on race-day views are at least 44px high (`h-11`). Layouts must work at 320px width and 200% zoom.

The Löpning.se page widths (1440 / 1100 / 720–760px) arrive with the public frame (#118) and are not recorded as this app's layout yet.

## Elevation & Depth

Depth is soft and green-tinted, never gray or black. Surfaces sit on the ground with a faint moss-tinted shadow, links lift a step on hover, and menus and dialogs are raised. Most admin surfaces today are flat, with contour borders. The exact values are in the sidecar.

### Shadow Vocabulary
- **Rest** (`box-shadow: 0 6px 20px rgb(31 92 70 / 0.08)`): cards and panels at rest. The auth card uses it.
- **Hover** (`box-shadow: 0 12px 28px rgb(31 92 70 / 0.14)`): link cards on hover.
- **Raised** (`box-shadow: 0 18px 40px rgb(31 92 70 / 0.16)`): menus and dialogs.

### Named Rules
**The Green Shadow Rule.** Shadows are tinted with moss. Don't use hard offset shadows, glass or blur.

## Shapes

The form language is rounded and soft, with three named radii: **card** (28px) for Löpning.se cards and peach or moss panels, **panel** (18px) for admin surfaces and the auth card, and **field** (12px) for inputs in the admin. Löpning.se also makes buttons, chips and search fields fully rounded. All borders default to contour. Today Flux's components still render their own radii, and only the auth card uses a named radius. Bringing buttons and fields onto these shapes is the admin redesign's job (#123).

## Components

Flux provides the components. The design system reaches them through tokens: accent, zinc, green and amber are all remapped, so Flux's default variants already speak Löpning.se colors.

### Buttons
- **Primary:** moss with white text, and deep moss on hover. In dark mode it is moss 300 with moss 950 text.
- **Ghost:** transparent with ink text and a faint neutral hover, for secondary actions in lists.
- **Danger:** red, only for deleting, and it always asks first.
- **Focus:** a 3px accent outline at a 3px offset, global on every focusable element.

### Chips
- **Style:** Flux badges. Green (moss) for done or good, amber (peach) for "waits for a person", zinc for neutral states, and red only for errors.

### Cards / Containers
- **Corner Style:** panel radius (18px) for the auth card.
- **Background:** white on ground. In dark mode it is zinc 950 with a zinc 800 border.
- **Shadow Strategy:** rest shadow (see Elevation & Depth).
- **Border:** contour.
- **Internal Padding:** 40px horizontal by 32px vertical on the auth card.

### Inputs / Fields
- **Style:** Flux inputs with a contour border on white, the label tight above the control, and an 8px gap.
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
- **Do** keep targets at least 44px high on race-day views, and keep every page working at 320px width and 200% zoom.

### Don't:
- **Don't** put text lighter than ink-soft on white or ground. Zinc 400 and 500 text on white is below the system's floor.
- **Don't** use peach in the admin for anything except what waits for a person.
- **Don't** use red for anything except errors and deleting.
- **Don't** give Löpning.se's own pages or organizer pages a dark mode. Dark mode belongs to the admin.
- **Don't** let an organizer theme ship a brand and contrast pair below WCAG 2.2 AA.
- **Don't** use glass, blur or gradients as decoration, or the old style's rose, blue, Figtree and Trirong.
