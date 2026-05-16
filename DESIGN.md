---
version: alpha
name: Memory Vault
description: A serious, technical encrypted personal vault. Stark black-and-white palette with a single orange accent. Clean lines, flat surfaces, and precise geometry.
colors:
  background: "#FFFFFF"
  foreground: "#171717"
  primary: "#F97316"
  primary-hover: "#EA580C"
  primary-light: "#FFF7ED"
  primary-glow: "rgba(249, 115, 22, 0.15)"
  muted: "#525252"
  border: "#E5E5E5"
  border-strong: "#D4D4D4"
  surface: "#FAFAFA"
  success: "#16A34A"
  warn: "#CA8A04"
  danger: "#DC2626"
  danger-subtle: "rgba(220, 38, 38, 0.06)"
  ring: "rgba(249, 115, 22, 0.25)"
typography:
  headline-xl:
    fontFamily: Inter
    fontSize: 1.875rem
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 1.5rem
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 1.125rem
    fontWeight: 600
    lineHeight: 1.3
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
  sm: 4px
  md: 8px
  lg: 8px
  xl: 8px
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
    rounded: "{rounded.md}"
    padding: 20px
  card-hover:
    borderColor: "{colors.border-strong}"
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.md}"
    padding: "10px 16px"
    typography: "{typography.label-md}"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    borderColor: "{colors.primary-hover}"
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
    backgroundColor: "{colors.background}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.md}"
    padding: "10px 12px"
    typography: "{typography.body-md}"
  tag:
    backgroundColor: "{colors.background}"
    textColor: "{colors.muted}"
    rounded: "{rounded.sm}"
    padding: "4px 8px"
    typography: "{typography.label-sm}"
  nav-item:
    backgroundColor: "transparent"
    textColor: "{colors.muted}"
    rounded: "0px"
    padding: "10px 12px"
    typography: "{typography.label-md}"
  nav-item-active:
    backgroundColor: "{colors.primary-light}"
    textColor: "{colors.primary-hover}"
    borderLeft: "2px solid {colors.primary}"
---

## Overview

Memory Vault is a serious, technical encrypted personal vault. The visual identity is stark and precise: pure black and white with a single vivid orange accent. Every surface is flat, every corner is sharp, and every interaction is deliberate. The design communicates security, precision, and zero ornamentation.

The philosophy is "form follows function." There are no decorative gradients, no blurred orbs, no playful hover lifts. Whitespace is generous. Borders are thin and gray. The orange accent is reserved exclusively for primary actions, active navigation, and focus states.

## Colors

The palette is strictly monochromatic with one functional accent.

- **Background ({colors.background}):** Pure white. Maximum contrast for content.
- **Foreground ({colors.foreground}):** Near-black for all text. Heavy and authoritative.
- **Primary ({colors.primary}):** Vivid orange — the only color allowed for CTAs, active states, and focus rings.
- **Primary Hover ({colors.primary-hover}):** Deeper orange for pressed and hover states.
- **Primary Light ({colors.primary-light}):** Very pale orange tint for active navigation backgrounds.
- **Muted ({colors.muted}):** Neutral gray for captions, descriptions, and secondary text.
- **Border ({colors.border}):** Light gray for dividing lines, card borders, and input outlines.
- **Border Strong ({colors.border-strong}):** Slightly darker gray for hovered card borders.
- **Surface ({colors.surface}):** Off-white for card backgrounds, creating a subtle elevation without shadow.
- **Success ({colors.success}):** Emerald green for functional positive states only.
- **Danger ({colors.danger}):** Red for destructive actions and errors only.

## Typography

All text is set in **Inter** with system fallbacks. Headlines are bold and tightly tracked. Body text is neutral and readable. Labels are crisp and medium-weight.

- **Headline XL ({typography.headline-xl.fontSize}):** Page titles. Weight 700, tight tracking.
- **Headline LG ({typography.headline-lg.fontSize}):** Section headers and card titles.
- **Headline MD ({typography.headline-md.fontSize}):** Sub-sections and form labels.
- **Body MD ({typography.body-md.fontSize}):** Standard paragraphs.
- **Body SM ({typography.body-sm.fontSize}):** Metadata and compact text.
- **Label MD ({typography.label-md.fontSize}):** Buttons and navigation. Weight 500.
- **Label SM ({typography.label-sm.fontSize}):** Tags and chips.
- **Label XS ({typography.label-xs.fontSize}):** Eyebrow labels and table headers. Uppercase, wide tracking.

## Layout & Spacing

A single-column fluid layout with a max-width of 1200px. Content is centered and top-biased. The layout is grid-like and rigid.

- **Page padding:** {spacing.md} on mobile, {spacing.lg} on desktop.
- **Section gap:** {spacing.lg} between major sections.
- **Card internal padding:** {spacing.md} to {spacing.lg}.
- **Sidebar width:** 256px on desktop (fixed left, 1px right border).
- **Grid:** 1-column mobile, 2-column tablet, 3-column desktop for card grids.

## Elevation & Depth

Elevation is expressed almost exclusively through borders, not shadows. The design is intentionally flat.

- **Flat:** Default state. No shadow.
- **Card:** {colors.surface} background with a 1px {colors.border} border and {rounded.md} corners.
- **Card Hover:** Border darkens to {colors.border-strong}. No lift, no shadow change.
- **Button Primary Hover:** Background darkens to {colors.primary-hover}. No shadow.
- **Modal / Drawer:** 1px left border for the mobile slide-out drawer. No blur, no backdrop dim beyond black at 50% opacity.
- **Focus Ring ({colors.ring}):** A 3px orange glow for accessible focus states.

## Shapes

Corners are sharp and consistent. The aesthetic is geometric and technical.

- **Small ({rounded.sm}):** Tags and tiny controls.
- **Medium ({rounded.md}):** Buttons, inputs, cards, and navigation items. The default radius.
- **Full ({rounded.full}):** Tags when used as pills (rare).

## Components

### Card
The fundamental building block. A flat {colors.surface} surface with a 1px {colors.border} border and {rounded.md} corners. No shadow. On hover, the border darkens to {colors.border-strong}.

### Button Primary
Solid orange fill with white text. {rounded.md} corners. No border, no shadow. On hover, background shifts to {colors.primary-hover}. Used sparingly — one per screen.

### Button Secondary
Transparent background with a 1px {colors.border} outline and black text. {rounded.md} corners. On hover, fills with {colors.background} and darkens the border.

### Button Danger
Transparent background with a subtle red-tinted border. On hover, fills with {colors.danger-subtle}. Used for delete and destructive actions.

### Button Ghost
No border by default. Gray text. On hover, reveals a background and border. Used for icon-only actions.

### Input
White background, 1px {colors.border} border, {rounded.md} corners. Focus state shifts the border to {colors.primary} and adds a 3px orange ring.

### Tag / Chip
Small rectangular pill with a gray border and muted text. {rounded.sm} corners. Functional, not decorative.

### Navigation Item
Horizontal row with an icon and label. Default: muted text, transparent background, 2px transparent left border. Active: orange left border, pale orange background tint, orange text. No filled pills.

### Table
Headers use {typography.label-xs} (uppercase, tracked). Rows have a subtle hover background. The table is wrapped in a bordered card.

## Do's and Don'ts

- Do use the orange accent sparingly — one primary action and one active element per screen.
- Do rely on borders and whitespace for separation, not shadows.
- Do use sentence-case for all headings and labels.
- Do keep corners at {rounded.md} (8px) for consistency.
- Do use the `vault-card`, `vault-btn-primary`, `vault-input`, and `vault-tag` utility classes.
- Don't use underlines on hover for links or buttons.
- Don't use decorative gradients, blurred orbs, or background patterns.
- Don't use shadows as the primary elevation mechanism.
- Don't add more than three type sizes on a single screen.
- Don't invent colors outside the defined palette.
- Don't use hover lifts, scale transforms, or playful animations.
