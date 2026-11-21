# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Students of one LSPU BSINFO class section (e.g. BSINFO-2A). The section is the organization: its creator is
the President, who appoints a Vice President, a Secretary and a Treasurer; everyone else is a Member.
They use AcadSync mostly on phones between classes, and on laptops. Officers review requests, run
activities, take attendance and keep dues; members follow their tasks, schedule, groups and activities.

## Product Purpose

One place for a class section's academic and organizational life: tasks and deadlines, the class schedule,
study groups and checklists, membership and officers, activities with RSVP and attendance, competitions and
section projects, announcements, and dues and payments. Success is a section that never misses a deadline,
a class change or a fee because the information was scattered.

It is also a school project: the LSPU OOP final project (Scenario 1, Student Organization Management System)
and the DSA project (seven hand-written data structures and the required sorts and searches), so the code
and the "How AcadSync works" page must show those clearly.

## Positioning

Built around one real section and its roles: permissions follow the office (President, VP, Secretary,
Treasurer, Member) and member status (Active, Inactive, Alumni), and every list, queue and check is a
hand-written data structure the class can inspect live.

## Operating Context

- Phones first (floating bottom nav, 360 to 430 px), laptops second (side rail, 1280 px and up).
- Runs on PHP's built-in server or XAMPP with SQLite; must work offline (no CDN; Tailwind is a built file).
- Demo accounts and seed data are used for grading and the YouTube demo.

## Capabilities and Constraints

- Plain HTML + Tailwind (built with the Tailwind CLI) + vanilla JS in `Frontend/`; PHP 8 JSON endpoints in `api/`.
- Pages: login, sign-up, section, home, schedule, week, subject, add-subject, task, task-form, my-tasks,
  classmates (and groups), activities, records, dues, queue, notifications, search, account, "How it works".
- Copy is plain, sentence-case language.

## Brand Commitments

- Name: AcadSync. Font: Plus Jakarta Sans (from the team's Figma file).
- 2026-11-21, from the owner: the visual design follows a reference shot of an education app
  (white and light-grey screens, very rounded cards, orange, periwinkle and yellow accents, dark charcoal
  cards and nav pill, bold headings, 3D trophy and graduation-cap illustrations, sparkle decorations),
  "copied but in an academic theme". Layouts stay as they are; only the design changes.
- Illustrations are hand-drawn SVG with a 3D look (owner's choice); the existing 3D cap badge
  (`Frontend/assets/badge-cap-*.png`) stays.

## Evidence on Hand

- Seeded demo section BSINFO-2A with real-looking classmates, subjects, tasks, activities, dues and
  announcements (`backend/seed.php`). README screenshots in `docs/screenshots/`.
- No real testimonials, logos of partners, or usage numbers exist; none may be invented.

## Product Principles

1. The section's real roles decide what each person sees and can do.
2. Deadlines and money are never ambiguous: what is due, by when, and who has paid.
3. Every data structure is visible and explainable.
4. Fast on a phone between classes: the next thing to do is on the first screen.

## Accessibility & Inclusion

Text meets WCAG AA contrast (secondary text at least 4.5:1 on every ground and tint); reduced motion is
respected; works at 360 px width without sideways scroll.
