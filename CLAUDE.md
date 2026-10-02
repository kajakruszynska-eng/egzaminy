# CLAUDE.md

Guidance for Claude Code when working in this repository.

## What this project is

`ocean-egzaminy` is a WordPress plugin that manages Polish sailing and motorboat license exams (patenty zeglarskie) for an examining organization: it publishes exam dates, takes participant signups through a public form, lets staff approve participants, sends confirmation emails with payment details, and generates the official exam paperwork as DOCX files (pure PHP, no external office tools).

It was built for one organization (Fundacja Ocean Wiedzy, Katowice) and currently has that organization's data hardcoded in many places. The goal now is a lean, universal version that other organizations can install with their own data. See `docs/UNIVERSALIZATION.md` for the plan and the list of hardcoded spots.

Owner: K, freelance developer. She communicates in Polish, so talk to her in Polish. Code, comments and docs are in English except user-facing plugin strings, which stay Polish.

## Hard constraints

- Host runs PHP 7.4.33 (home.pl). No `match()`, no `fn()` arrow functions, no union types, no named arguments. The `??` operator is fine.
- No em dash character anywhere in code, comments, docs or replies.
- After a coding task, keep the follow-up short: what changed in one or two sentences.
- Plugin is delivered as a zip that K uploads in WP Admin. Zip layout: `ocean-egzaminy/ocean-egzaminy.php`, `ocean-egzaminy/includes/*.php`, `ocean-egzaminy/vendor/`.

## Layout

```
ocean-egzaminy/              plugin root
  ocean-egzaminy.php         bootstrap, requires every include
  includes/
    post-type.php            CPT oe_egzamin (exams), oe_zapis (signups), signup statuses
    admin-metabox.php        exam data metabox, shortcode metabox, save handler
    admin-columns.php        list table columns and bulk actions
    shortcode.php            [formularz_egzaminu id=""] and [lista_egzaminow]
    form-handler.php         public signup submission
    emails.php               confirmation and status emails
    export.php               CSV export of participants
    assets.php               front-end CSS/JS loading
    hide-meta.php            hides theme meta on exam posts
    miejsca-egzaminow.php    exam venues per exam type (teoria, praktyka)
    zadania-egzaminow.php    exam task definitions and deterministic drawing
    docx-builder.php         OE_Docx class, DOCX via ZipArchive
    generator.php            9 DOCX documents and the download handler
    access-guard.php         shared-password gate (to be removed, see plan)
  vendor/
docs/
  UNIVERSALIZATION.md        plan for multi-organization version
```

## Domain notes

- Five exam types: Sternik Motorowodny (SM), Zeglarz Jachtowy (ZJ), Jachtowy Sternik Morski (JSM), Motorowodny Sternik Morski (MSM), Licencja do holowania narciarza wodnego lub innych obiektow (LHN). Each has a ministry decision number (decyzja MSiT), its own venue lists and its own task sets.
- Nine documents per exam: zgloszenie, karty, arkusze, arkusz_wzor1, zaswiadczenia, zal1, zal2, zal3, protokol. Generated from approved participants only.
- Task drawing is deterministic: seeded by `crc32(imie + nazwisko)`, so regenerating a document gives the same tasks for the same person.
- File names: `RRRR_MM_DD_SKROT_MIASTO_<doc>.docx`.
- Not every venue serves every exam type (for example inland locations only serve SM, ZJ, MSM, LHN, not JSM).

## Before you finish any change

1. `php -l` on every PHP file (`for f in $(find . -name '*.php' -not -path './vendor/*'); do php -l "$f"; done`).
2. Check for duplicate function names: `grep -ho "^function [a-z_0-9]*" -r ocean-egzaminy/includes | sort | uniq -d` must print nothing.
3. Grep for `fn(` and `match(` and fix any hit that is real code (ignore `preg_match(`).
4. Check brace balance if you edited with scripts. Past fatal errors came from scripted edits that duplicated a function or truncated a file's end. Prefer small targeted edits over regenerating whole files.
5. Rebuild the zip from the plugin directory and confirm it contains `ocean-egzaminy/ocean-egzaminy.php`.

## Known quirks

- Exam metadata lives in post meta with the `_oe_` prefix (for example `_oe_nr_egzaminu`, `_oe_rodzaj_egzaminu`, `_oe_komisja`). Do not rename keys without a migration.
- Exam type names are used as array keys and compared as exact strings in at least five files. Changing a label breaks lookups.
- The site also runs other plugins and the Astra theme, so keep front-end CSS scoped under an `oe-` prefix.
