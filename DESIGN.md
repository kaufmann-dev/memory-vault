---
version: alpha
name: Memory Vault
description: A calm, confident, and modern encrypted personal vault. Deep slate typography on a cool gray canvas, with vibrant cobalt accents and soft layered shadows.
colors:
  background: "#F6F7F9"
  foreground: "#0F172A"
  primary: "#2563EB"
  primary-hover: "#1D4ED8"
  primary-light: "#EFF6FF"
  primary-glow: "rgba(37, 99, 235, 0.12)"
  muted: "#64748B"
  border: "#E2E8F0"
  border-strong: "#CBD5E1"
  surface: "#FFFFFF"
  success: "#16A34A"
  warn: "#CA8A04"
  danger: "#DC2626"
  danger-subtle: "rgba(220, 38, 38, 0.06)"
  ring: "rgba(37, 99, 235, 0.2)"
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 2.25rem
    fontWeight: 800
    lineHeight: 1.1
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 1.875rem
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 1.25rem
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: -0.01em
  body-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: 0em
  body-sm:
    fontFamily: Inter
    fontSize: 0.8125rem
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: 0em
  label-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 500
    lineHeight: 1.25
    letterSpacing: 0em
  label-sm:
    fontFamily: Inter
    fontSize: 0.75rem
    fontWeight: 500
    lineHeight: 1rem
    letterSpacing: 0.02em
  label-xs:
    fontFamily: Inter
    fontSize: 0.6875rem
    fontWeight: 600
    lineHeight: 1rem
    letterSpacing: 0.05em
rounded:
  sm: 8px
  md: 10px
  lg: 16px
  xl: 20px
  full: 999px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  2xl: 48px
components:
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: 20px
  card-hover:
    shadow: "0 4px 12px rgba(0,0,0,0.05), 0 16px 48px rgba(0,0,0,0.05)"
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    typography: "{typography.label-md}"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    shadow: "0 4px 14px rgba(37, 99, 235, 0.3)"
  button-secondary:
    backgroundColor: "transparent"
    textColor: "{colors.foreground}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    typography: "{typography.label-md}"
  button-danger:
    backgroundColor: "transparent"
    textColor: "{colors.danger}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    typography: "{typography.label-md}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
    typography: "{typography.body-md}"
  tag:
    backgroundColor: "{colors.background}"
    textColor: "{colors.muted}"
    rounded: "{rounded.full}"
    padding: "4px 10px"
    typography: "{typography.label-sm}"
  nav-item:
    backgroundColor: "transparent"
    textColor: "{colors.muted}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
    typography: "{typography.label-md}"
  nav-item-active:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    shadow: "0 1px 3px rgba(37, 99, 235, 0.25)"
---

## Overview

Memory Vault is a calm, confident, and modern encrypted personal vault. The visual identity balances deep slate typography on a cool gray canvas with vibrant cobalt accents. Every surface feels intentional — cards float with soft layered shadows, interactive elements respond with subtle lift, and the overall experience reads as premium without being ornate.

The design philosophy is "content-first, chrome-second." Whitespace is the primary separator. One accent element per screen. No underlines on hover. No pure black or pure white backgrounds.

## Colors

The palette is rooted in cool slate neutrals with a single energetic cobalt accent.

- **Background ({colors.background}):** Cool gray canvas. Softer than pure white, providing gentle contrast for floating cards.
- **Foreground ({colors.foreground}):** Deep slate for headlines and body text. Richer than pure black.
- **Primary ({colors.primary}):** Cobalt blue — the sole driver for CTAs, active navigation, links, and focus rings.
- **Primary Hover ({colors.primary-hover}):** A deeper cobalt for button hover and pressed states.
- **Primary Light ({colors.primary-light}):** Very pale blue for selection highlights and subtle gradient backgrounds.
- **Muted ({colors.muted}):** Slate-500 for captions, descriptions, and secondary metadata.
- **Border ({colors.border}):** Soft slate-200 for input borders and dividers.
- **Border Strong ({colors.border-strong}):** Slightly stronger for hover states on secondary buttons.
- **Surface ({colors.surface}):** Pure white for cards, modals, and elevated containers.
- **Success ({colors.success}):** Emerald green for positive feedback.
- **Warn ({colors.warn}):** Amber for cautionary states.
- **Danger ({colors.danger}):** Red for destructive actions and errors.

## Typography

All text is set in **Inter** with `-apple-system` and `system-ui` fallbacks. Headlines are tightly tracked and heavily weighted for confidence. Body text is relaxed and readable.

- **Headline XL ({typography.headline-xl.fontSize}):** Page heroes and vault titles. Weight 800, tight tracking.
- **Headline LG ({typography.headline-lg.fontSize}):** Section titles and card headers. Weight 800.
- **Headline MD ({typography.headline-md.fontSize}):** Sub-sections and form labels. Weight 600.
- **Body MD ({typography.body-md.fontSize}):** Standard paragraphs and descriptions.
- **Body SM ({typography.body-sm.fontSize}):** Compact metadata and captions.
- **Label MD ({typography.label-md.fontSize}):** Buttons and navigation links. Weight 500.
- **Label SM ({typography.label-sm.fontSize}):** Tags and chips. Weight 500.
- **Label XS ({typography.label-xs.fontSize}):** Eyebrow labels and table headers. Uppercase, wide tracking, weight 600.

## Layout & Spacing

A single-column fluid layout with a max-width of 1200px. Content is centered and top-biased.

- **Page padding:** {spacing.md} on mobile, {spacing.lg} on desktop.
- **Section gap:** {spacing.lg} between major sections.
- **Card internal padding:** {spacing.md} to {spacing.lg}.
- **Sidebar width:** 256px on desktop (fixed left).
- **Grid:** 1-column mobile, 2-column tablet, 3-column desktop for card grids.

## Elevation & Depth

Elevation is expressed through layered soft shadows rather than borders. This creates a modern, floating aesthetic.

- **Flat:** Default state. No shadow.
- **Card ({components.card.shadow}):** Subtle ambient shadow for all surface cards.
- **Card Hover ({components.card-hover.shadow}):** Stronger shadow with a 2px upward lift on interactive cards.
- **Button Primary Hover ({components.button-primary-hover.shadow}):** Colored drop shadow matching the primary accent.
- **Modal / Drawer:** Large directional shadow (`-4px 0 24px rgba(0,0,0,0.08)`) for the mobile slide-out drawer.
- **Focus Ring ({colors.ring}):** A soft 3px cobalt glow replaces traditional outlines.

## Shapes

All corners are generously rounded for a friendly, modern feel.

- **Small ({rounded.sm}):** Checkboxes and tiny controls.
- **Medium ({rounded.md}):** Buttons, inputs, and navigation items.
- **Large ({rounded.lg}):** Cards, tables, and modals.
- **Extra Large ({rounded.xl}):** Hero banners and login cards.
- **Full ({rounded.full}):** Tags, pills, and progress bars.

## Components

### Card
The fundamental building block. A white surface with {rounded.lg} corners and a layered soft shadow. Interactive cards gain lift and a stronger shadow on hover.

### Button Primary
Solid cobalt fill with white text. {rounded.md} corners. On hover, deepens to {colors.primary-hover} and casts a colored shadow. Never use a border.

### Button Secondary
Transparent background with a {colors.border} outline. {rounded.md} corners. On hover, fills with {colors.background} and the border darkens to {colors.border-strong}.

### Button Danger
Transparent background with a subtle red-tinted border. On hover, fills with {colors.danger-subtle}. Used for delete and destructive actions.

### Button Ghost
No border by default. Used for icon-only actions and close buttons. On hover, reveals a background and border.

### Input
White background, {colors.border} border, {rounded.md} corners. Focus state shifts the border to {colors.primary} and adds a 3px ring glow. Placeholder text uses a lighter slate.

### Tag / Chip
Pill-shaped ({rounded.full}) with a soft gray background and muted text. Used for diary tags and metadata.

### Navigation Item
Horizontal row with an icon and label. Default state is muted text. Active state is a filled cobalt pill with white text and a subtle shadow. No left-border accents.

### Table
Headers use {typography.label-xs} (uppercase, tracked). Rows have a subtle hover background. The table itself is wrapped in a card with hidden overflow.

### Hero Banner
A large card with a decorative blurred gradient orb in the corner (using {colors.primary} at low opacity). Contains the page welcome message.

## Do's and Don'ts

- Do let whitespace do the work. Avoid cramped layouts.
- Do use the primary color sparingly — one hero element and one CTA per screen.
- Do use sentence-case for all headings and labels.
- Do add hover lift and shadow transitions to all interactive cards and buttons.
- Do use the `vault-card`, `vault-btn-primary`, `vault-input`, and `vault-tag` utility classes for consistency.
- Don't use underlines on hover for links or buttons.
- Don't use pure black or pure white for backgrounds.
- Don't use flat borders as the primary elevation mechanism — rely on shadow.
- Don't add more than three type sizes on a single screen.
- Don't invent hex values outside the defined palette.
