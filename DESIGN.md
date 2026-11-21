---
name: AcadSync
description: A class section's tasks, schedule, activities and dues, dressed as a bright academic study app.
colors:
  ink: "#1C1C21"
  canvas: "#F4F4F7"
  surface: "#FFFFFF"
  line: "#E9E9EF"
  muted: "#5E5E68"
  hint: "#666670"
  subtle: "#A9A9B4"
  charcoal-card: "#1E1E23"
  grey-soft: "#EDEDF2"
  periwinkle: "#A8A3F7"
  periwinkle-tint: "#ECEBFE"
  periwinkle-light: "#C9C6FB"
  periwinkle-deep: "#5F58D6"
  orange: "#F58D42"
  orange-tint: "#FDE7D6"
  orange-light: "#F9B886"
  orange-deep: "#B9561A"
  yellow: "#FFD34E"
  yellow-tint: "#FFF3C4"
  mint: "#BFE5D3"
  mint-tint: "#E4F4EC"
  success: "#23774F"
  danger: "#C8402F"
  danger-tint: "#FDE5E2"
typography:
  display:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "28px"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  stat:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 800
    lineHeight: 1
  headline:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 600
    lineHeight: 1.375
  body:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  caption:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
  label:
    fontFamily: "Plus Jakarta Sans, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 600
    letterSpacing: "0.05em"
rounded:
  field: "1rem"
  tile: "1rem"
  inner: "1.5rem"
  card: "1.75rem"
  sheet: "2.25rem"
  pill: "9999px"
spacing:
  gutter: "16px"
  card-sm: "16px"
  card: "20px"
  card-lg: "32px"
  stack: "20px"
components:
  button-dark:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.surface}"
    rounded: "{rounded.pill}"
    height: "48px"
    padding: "0 20px"
  button-light:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    height: "48px"
    padding: "0 20px"
  button-yellow:
    backgroundColor: "{colors.yellow}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    height: "48px"
    padding: "0 20px"
  button-canvas:
    backgroundColor: "#F1F1F5"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    height: "48px"
    padding: "0 20px"
  field:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    height: "48px"
    padding: "8px 16px"
  chip:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    padding: "4.8px 12px"
  chip-filter-active:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.surface}"
    rounded: "{rounded.pill}"
    padding: "7.2px 15.2px"
  card-white:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "{spacing.card-sm}"
  card-hero-periwinkle:
    backgroundColor: "{colors.periwinkle}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "{spacing.card}"
  card-hero-charcoal:
    backgroundColor: "{colors.charcoal-card}"
    textColor: "{colors.surface}"
    rounded: "{rounded.card}"
    padding: "{spacing.card}"
  card-tint-orange:
    backgroundColor: "{colors.orange-tint}"
    textColor: "{colors.ink}"
    rounded: "{rounded.inner}"
    padding: "{spacing.card-sm}"
  nav-pill:
    backgroundColor: "{colors.charcoal-card}"
    textColor: "#9D9DAA"
    rounded: "{rounded.pill}"
    height: "64px"
  nav-item-active:
    backgroundColor: "{colors.periwinkle}"
    textColor: "{colors.ink}"
    rounded: "{rounded.tile}"
    size: "48px"
  nav-fab:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.pill}"
    size: "52px"
---

# Design System: AcadSync

## Overview

**Creative North Star: "The Bright Study Desk"**

AcadSync reads like a cheerful education app laid over a class section's working screens. A cool, almost-white ground holds white cards with a hairline edge; a few saturated cards (periwinkle, orange, yellow, charcoal) carry each screen's weight and say "this is what matters now". Inside those strong cards live the world's two ornaments, thin white doodle loops and four-point sparkle stars, and the academic objects that break out of the corner: a 3D graduation-cap badge, a trophy, a book stack, a pencil, a diploma.

The system was restyled onto frozen layouts (owner decision, 2026-11-21: "do not change the layout, just the design"). Every page keeps its composition; what changed is colour, radius, type weight, ornament and illustration. Density is phone-first: one column of large, very rounded cards with 16-20px gaps, a floating charcoal nav pill at the bottom, and a charcoal side rail on laptops. It deliberately replaces the earlier warm sand-and-pastel skin, which is no longer current.

The tone is bold and friendly rather than corporate: extra-bold numerals, pill-shaped everything, circular arrow actions, and colour used as a flag, not as wallpaper.

**Key Characteristics:**
- Cool grey ground (#F4F4F7), white hairline cards, near-black ink.
- Saturated periwinkle, orange, yellow and charcoal cards mark the cards that matter; soft tints mark secondary cards.
- 28px card corners, pill buttons, chips and nav.
- Plus Jakarta Sans throughout, extra-bold (800) for numbers and hero titles.
- Doodle loops and sparkle stars inside strong cards; hand-drawn 3D-look academic illustrations on heroes and empty states.

## Colors

A cool neutral field with four saturated card colours and their tints; semantic colours (mint, red) are reserved for state.

### Primary
- **Study Periwinkle** (periwinkle): the member hero card, the active nav item, the AS rail mark, calendar ranges and the lead subject tone. Its light step is the text-selection colour; its deep step (periwinkle-deep) is the focus ring, caret and icon colour on periwinkle tints. Strong cards use a 150deg gradient from #B9B5FA through the base to #9B95F2.
- **Periwinkle Tint** (periwinkle-tint): soft stat cards, icon tiles, Reporting chips, empty-state icon tiles.

### Secondary
- **Marker Orange** (orange): the login hero field, peach subject and dues-owed heroes, the nav badge, calendar task dots, account avatars. Strong cards use a 160deg gradient #FAC195 to #F7A262 to the base. Its light step (orange-light) is the label colour on charcoal cards; its deep step colours icons on orange tints.
- **Orange Tint** (orange-tint): Quiz and Exam chips, the "Add task" quick action, soft cards.

### Tertiary
- **Highlighter Yellow** (yellow): the "Due today" stat card, the yellow button, sparkle stars on charcoal cards, the deadline icon disc, group-work badges. Tint (yellow-tint) marks Project chips, group-work strips and "date not set" notes.

### Neutral
- **Ink** (ink): all body text, the dark button, active filter chips, selected day pills and calendar days, checkbox accent.
- **Charcoal Card** (charcoal-card): the officer hero, deadline and class-code cards (with a #2A2A31 to base gradient), the nav pill and rail, toasts.
- **Cool Ground** (canvas): page background, field fill, inset panels and drawer rows.
- **Surface White** (surface): cards, sheets, the login form sheet, header icon buttons.
- **Hairline** (line): 1px card edges (as a shadow ring), dividers, the sheet grab handle.
- **Reference Grey** (grey-soft): the fourth subject tone and the Activity / Softcopy chips.
- **Muted** (muted): secondary text on white, the ground and the tints. **Hint** (hint): placeholders and done checklist text. **Subtle** (subtle): icons and borders only, never text.

### Semantic
- **Mint** (mint, mint-tint) with **Success Green** (success): done chips, "All paid", the active-section chip, completed checklists and the task-history icon.
- **Alert Red** (danger, danger-tint): due dots, the unread bell dot, the officer "urgent today" card, error boxes, invalid fields.

### Named Rules
**The Flag Not Wallpaper Rule.** A screen gets one or two strong-fill cards (its hero and at most one stat); everything else is white or a soft tint. If three saturated cards sit in one viewport, one of them should be a tint.

**The Mint Means Done Rule.** Mint is for success states only. A subject stored as `mint` renders as the Reference Grey (grey-soft card, #EDEDF2 chip, ink bar and icon), never green.

**The Muted Ground Rule.** Muted text (#5E5E68) sits only on white, the #F4F4F7 ground and the tints. On periwinkle, orange and yellow fills use ink; on charcoal use white at 60-70% opacity or orange-light.

## Typography

**Display Font:** Plus Jakarta Sans (with system-ui, sans-serif)
**Body Font:** Plus Jakarta Sans (same family)

**Character:** One geometric-humanist sans at many weights. Extra-bold numerals and hero titles give the reference app's chunky, confident voice; regular-weight body keeps the working screens calm. The face is self-hosted as a variable font (weights 200-800, latin and latin-ext subsets, OFL) so the app works offline.

### Hierarchy
- **Display** (800, 28px, 1.15, tight tracking; 36-48px on laptops): hero card titles ("3 tasks due this week", subject and task names) and the login headline.
- **Stat** (800, 2.25rem, line-height 1): the big numbers on stat cards and the dues balance.
- **Headline** (700, 1.25rem, tight tracking; 28px on laptops): page titles in the header and section headings ("My subjects", "Upcoming deadlines").
- **Title** (600, 15px, 1.375): card and list item titles, announcement titles.
- **Body** (400, 0.875rem, 1.43): descriptions, announcement bodies, helper text.
- **Caption** (500, 0.75rem): metadata, stat sublabels, chip text.
- **Label** (600-700, 11px, 0.05em tracking, uppercase): see the owner override below.

### Named Rules
**The Heavy Number Rule.** Counts, balances and dates that answer "how much / how many / when" are set at 800. Weight, not colour, makes the number the first thing read.

**Owner override: uppercase labels above headings.** The small uppercase labels above headings ("WELCOME BACK", "WAITING FOR YOU", "THIS WEEK", "COLLECTED SO FAR", "DEADLINE", "YOUR DUES") are kept by explicit owner decision on 2026-11-21 ("Keep them all"), because the frozen layouts and the reference both carry them. This overrides the craft floor's eyebrow ban for the existing screens; it is recorded as an owner override, not as part of the system's own vocabulary, so new surfaces do not add new ones by default.

## Layout

Phone-first single column inside a centred 28rem (max-w-md) container with a 16px gutter; it widens to 72rem (max-w-6xl) with 32px gutters on laptops, offset 96px from the left for the side rail. Pages stack cards with 16-20px gaps (24px on laptops). Home uses a two-column grid on laptops (fluid main column plus a 20rem side column). Stat cards sit two-up on phones and stack in the side column on laptops; subject and quick-action cards are two-up on phones, four-up on laptops. Bottom padding of 128px on phones keeps content clear of the floating nav. The login and sign-up pages are the one place the world goes full-bleed: a coloured hero field above (phones) or beside (laptops) a white form sheet whose top corners round at 2.25rem as it overlaps the hero.

Breakpoints: 374px (smaller nav targets on 360px phones), 1023px / 1024px (phone bar vs. side rail). Nothing may scroll sideways at 360px.

## Elevation & Depth

A hybrid: the ground is flat, white cards lift with a hairline ring plus a very soft drop, and strong cards cast a coloured shadow in their own hue so they feel like painted plastic rather than paper. Illustrations get a soft cast drop-shadow that supplies their 3D lift. Floating chrome (nav pill, toasts) uses the deepest shadow.

### Shadow Vocabulary
- **Card** (`box-shadow: 0 0 0 1px rgba(28,28,33,.06), 0 6px 18px -12px rgba(28,28,33,.16)`): every white card and header icon button.
- **Strong tinted** (`box-shadow: 0 14px 28px -18px rgba(76,68,190,.55)` for periwinkle; the same geometry in orange rgba(185,86,26,.6) and yellow rgba(170,125,10,.55)): strong-fill cards.
- **Soft tinted** (`box-shadow: 0 6px 18px -14px <hue at .25-.35>`): tint cards.
- **Charcoal** (`box-shadow: 0 16px 30px -18px rgba(28,28,33,.7)`): charcoal cards.
- **Float** (`box-shadow: 0 18px 40px -14px rgba(28,28,33,.45)`): toasts and the cap badge on auth heroes. The nav pill uses `0 20px 40px -16px rgba(28,28,33,.6)`.
- **Illustration lift** (`filter: drop-shadow(0 10px 10px rgba(28,28,33,.22))`): every hand-drawn illustration.
- **Subject drawing extrusion** (`.subject-art`: three stacked 0-blur `drop-shadow(1.25px 1.25px 0 <side>)` plus `drop-shadow(2px 9px 8px <cast>)`; 2.25px steps on the 190px hero drawing): a light front face, a darker side down-right (lit from the top left) and an own-hue cast shadow. Face / side / cast per tone: periwinkle #D2CEFC / #9C96F2, orange #FAD3B4 / #EC9351, yellow #F8E08E / #DDAE24, grey #DADAE2 / #A3A3AF; on strong heroes a solid light face (#E9E7FE, #FFE6D3, #FFF4CC) over a deep side. Hover deepens the cast to `4px 14px 10px`.
- **Raised disc** (`.subject-icon`: `inset 0 1.5px 0 rgba(255,255,255,.9), inset 0 -2.5px 0 rgba(28,28,33,.08), 0 6px 10px -6px rgba(28,28,33,.35)`): the white icon disc on subject cards.

### Named Rules
**The Own-Hue Shadow Rule.** A coloured card's shadow is tinted with its own deep hue, never neutral grey.

## Shapes

Very rounded and pill-heavy. Main cards round at 28px; inner and small grid cards at 24px; icon tiles, fields and nav squares at 16px; sheets and the rail at 36px. Buttons, chips, the nav pill, day pills, calendar days, avatars and circular action discs are full pills or circles. No sharp corners appear anywhere. Borders are rare: edges come from a 1px shadow ring, not a stroke.

Ornament is part of the shape language:
- **Doodle loop:** a thin hand-drawn loop, dotted arc and small star masked into the bottom-right corner of a card in white at 60% (12% on charcoal). On the auth heroes a larger doodle field (loops, a dotted circle, dots and a small mortarboard) covers the whole hero at 40% white.
- **Sparkle stars:** a large and small four-point star at the top-right of a card; white on strong fills, yellow on charcoal, periwinkle (or orange on orange tint) on soft cards.

### Named Rules
**The Doodle On Heroes Rule.** The loop doodle lives on hero and charcoal cards only. Small tinted cards (stat cards, subject cards, quick actions) carry no doodle, as in the reference. (Known exception: the three Officers cards on the admin Home still carry it; see NEXT_STEPS recommendations.)

**The One Drawing Per Subject Rule.** A subject card's only ornament is its own subject drawing (`SUBJECT_DESIGNS` in `js/ui.js`): the same line art large in the corner (86px on cards, 190px on the subject hero, rotated −12°, behind the text) and small (18px) on the raised icon disc. Never a doodle or a sparkle on top of it.

## Components

### Buttons
Tactile and confident: tall pills that press in.
- **Shape:** full pill, 48px tall, 20px side padding, 700 weight at 15px.
- **Dark (primary):** ink fill, white text, a soft dark drop shadow; hover lifts to #2C2C33.
- **Light:** white with a hairline ring; used on coloured cards ("Review", "Edit subject").
- **Yellow:** yellow fill, ink text; hover #FFDB6E.
- **Canvas:** #F1F1F5 fill for low-emphasis actions; **Danger** uses the danger tint with ink text.
- **States:** press scales to 0.98; disabled drops to 45% opacity; focus shows the 2px periwinkle-deep ring offset 2px.
- **Circular action:** a 48px ink disc with a white arrow (on periwinkle heroes with a 4px white/40 ring) or a white disc on charcoal; pairs with a label ("View schedule").

### Chips
- **Style:** pill, 12px at 600, 4.8px by 12px padding; fill by meaning (white, a tint, ink with white text for "NOW", "Needs a group", relative-time badges).
- **Category chips:** Reporting periwinkle tint, Quiz and Exam orange tint, Project yellow tint, Activity reference grey; Done is mint tint with success text.
- **Filter chips:** white with a hairline ring; pressed state is ink with white text.

### Cards / Containers
- **Corner Style:** 28px for main cards, 24px for grid cards.
- **Background:** white for content cards; strong gradients (periwinkle, orange, yellow, charcoal) for heroes and a lead stat; tints for secondary stats, subjects and quick actions; danger tint for the officer's urgent card.
- **Shadow Strategy:** see Elevation & Depth (hairline card shadow on white, own-hue shadows on colour).
- **Border:** none; the shadow ring is the edge.
- **Internal Padding:** 16px on small cards, 20px on heroes, 28-32px on laptop heroes.
- **Stat card** (`.stat-card`, the reference's "Lessons 78"): a 2px coloured border (orange #F7A262 on white, or periwinkle #A8A3F7 on #ECEBFE), a coloured corner tab top-left holding the icon (#F9B886 / #C9C6FB), a 13px/600 label beside the tab, a 40px/800 number and a caption sublabel.
- **Subject card:** a soft tint of the subject's colour, the raised icon disc top-left with the subject drawing, a white "N due" / "All clear" chip top-right, the code as a 10px caption and the name at 600 (up to 3 lines), the extruded subject drawing in the bottom-right corner. Laid out two-up on phones, four-up on laptops; the admin's grid ends with a dashed **Add subject** tile (2px dashed #C9C9D3, white with a periwinkle border on hover, its + disc turns 90°).
- **Officer card** (admin Home): a soft tint per office (VP periwinkle, Secretary orange, Treasurer yellow), the office name, the holder's initials or a "Vacant" chip, a white pill select (`.rec-select`) and a one-line duty.

### Inputs / Fields
- **Style:** 48px tall, ground-grey fill (#F4F4F7), 16px radius, 1.5px transparent border, leading muted icon, 15px text, hint-coloured placeholder. On a grey panel the field turns white with a hairline ring.
- **Focus:** border shifts to #7D77EA and the fill turns white.
- **Error / Success:** red or success-green border on a white fill.
- **Code boxes:** 16px-radius white cells at 22px/800 uppercase for the six-character class code.

### Navigation
- **Phone:** a floating charcoal pill (64px tall, 16px from the edges, max 24rem) holding three items, a centred white + disc, and three items. Items are 44px rounded squares in #9D9DAA; hover goes white; the active item is a periwinkle rounded square with ink icon. The queue badge is an orange pill with ink 10px/800 numerals ringed in charcoal.
- **Laptop:** a charcoal rail (72px wide, 36px radius, floating 16px from the edges) with the periwinkle "AS" mark at the top, the same items and + disc, and the orange account avatar at the foot (yellow when active).
- **Header:** a muted caption over a 700 headline, with 44px white circular icon buttons (search, notifications with a red unread dot) and an orange initials avatar on phones.

### Day Pills and Calendar
Tall white pills (68px) with a hairline ring show the week; the selected day inverts to ink. Calendar days are circles; ranges fill periwinkle, the selected day is ink, a task day carries an orange dot (yellow when selected).

### Sheets and Drawers
White bottom sheets with 36px top corners and a grab handle on phones, centred 32px-radius dialogs on laptops; the account drawer slides from the right with 36px left corners. Backdrop ink at 45%. Sheets slide up 40px over 420ms on `cubic-bezier(.16,1,.3,1)` and leave faster (200ms, 48px down); reduced motion turns them into short fades.

### Motion (the `Motion` object at the end of `js/ui.js`; styles under "motion & interaction" in `css/app.css`)
One grammar: confident deceleration `cubic-bezier(.16,1,.3,1)`; feedback 100-150ms, state changes 200-300ms, overlays about 400ms; exits quicker than entrances. Content is visible without motion; Motion only adds classes and temporary elements.
- **The authored moment, the cap toss:** the graduation cap (`assets/illus/cap.svg`) is tossed up, spins and peaks with a small star burst. Plays in the intro (once per visit: the AS tile and wordmark rise, a tap or key skips), when a task is marked done, and when a task is requested or added, a request approved, a due or activity created, an announcement posted.
- **Acknowledging other saves:** every non-GET API call fires `acadsync:action`; a burst of four-point stars and dots in the palette comes from where you tapped; removals, cancellations and undo get a quiet grey ring instead.
- **Interaction:** ripples from the touch point on buttons, chips, nav items and coloured cards; coloured cards lean up to 5° toward the mouse (3° on wide cards) with a light glare and their illustration or subject drawing drifting; subject drawings straighten and lift on hover; sparkle stars twinkle on hover; a done check pops.
- **Arrival:** a page's cards rise in as a short list (stagger capped at 360ms), stat numbers count up, pages cross-fade (view transitions), toasts rise in with their icon drawn.
- **Reduced motion:** no travel, tilt, particles, intro or count-up; short fades only, and the toast still confirms every action.

### Academic Illustrations (signature)
Hand-drawn SVG objects with gradient shading for a 3D look: trophy (gold on a periwinkle stand), book stack, pencil, diploma, plus the pre-existing 3D graduation-cap badge (white and yellow PNG variants). They break out of the corner of hero cards (cap badge on the member hero, diploma on the officer hero) and stand in for the icon tile in empty states (56px): check and flag map to the trophy, book, notebook and calendar to the books, clipboard and inbox to the diploma, users to the pencil. On the auth heroes they cluster large (110-200px) around the headline on laptops and shrink to the cap badge plus one object on phones. Always decorative (`alt=""`), always with the illustration lift shadow.

## Do's and Don'ts

### Do:
- **Do** keep the ground #F4F4F7 and put content on white cards with the hairline card shadow.
- **Do** give each screen one strong-fill hero (periwinkle for members, charcoal for officers) and let tints carry the rest.
- **Do** round main cards at 28px and make every button, chip and nav control a pill or circle.
- **Do** set counts, balances and hero titles in Plus Jakarta Sans at 800.
- **Do** put the doodle loop and sparkle stars on hero and charcoal cards, and an academic illustration in the hero's corner or the empty state.
- **Do** use ink text on periwinkle, orange and yellow fills, and white or orange-light on charcoal.
- **Do** give every subject its drawing with depth (light face, darker extruded side, own-hue cast), and add a new drawing to both `SUBJECT_DESIGNS` (`js/ui.js`) and `DESIGNS` (`api/subjects.php`).
- **Do** save the cap toss for real milestones; everything else gets a burst or a ring.

### Don't:
- **Don't** bring back the warm sand ground (#F6F3EE), the pastel-only skin or the yellow nav glow; that look is retired.
- **Don't** use mint or success green for a subject colour; mint marks success only.
- **Don't** put muted (#5E5E68) text on the strong periwinkle, orange or yellow fills.
- **Don't** put the doodle loop on small tinted stat, subject or quick-action cards.
- **Don't** use subtle (#A9A9B4) for text; it is for icons and borders only.
- **Don't** change a page's layout to fit the look; the restyle is skin-only.
- **Don't** add new uppercase labels above headings on new surfaces; the existing ones are an owner override, not house vocabulary.
