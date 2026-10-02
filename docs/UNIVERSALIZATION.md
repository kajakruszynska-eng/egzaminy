# Universalization plan

Goal: one lean plugin that any examining organization can install and configure with its own data, with no code edits.

## Decisions already made (by K)

1. Organization data (name, address, KRS, NIP, REGON, bank account, account owner, contact email, logo) is edited on a settings screen in WP Admin and stored in the database.
2. The shared password in `includes/access-guard.php` is removed. Access control uses WordPress capabilities and roles.
3. Exam types are fully configurable: an organization defines its own exam types, venues, tasks, decision numbers and documents. Not limited to the five sailing types.

## Still to decide

- Scope of the cleanup: tidy the current code first, or rewrite in a clean structure and use the old code only as reference. Recommendation: rewrite the data layer and settings first (small, high value), then migrate the generator.
- How documents are produced once exam types are configurable (see Phase 4).

## Hardcoded organization data found in the current code

Counts are from the audit on 2026-10-02. Values are intentionally not repeated here.

| Item | Where |
|---|---|
| Organization name, address, KRS, NIP, REGON, bank account and owner, Reply-To, BLIK logo URL, document city, consent text | **Moved to settings in Phase 2.** Also moved (missed by the first audit): contact phone, BLIK phone, bank name, email signature name and function, email header subtitle, default exam fee. |
| Plugin header `Author` | `ocean-egzaminy.php`, Phase 5 |
| Decision numbers, exam type names as keys, venue lists, task sets, card table layouts, answer keys, file name codes, parental consent and 25-question special cases | **Moved to exam types (CPT `oe_rodzaj`) in Phase 3.** The accusative organization name in the protocol ("powołana przez ...", missed by earlier audits because it was in capitals) is a setting since Phase 3. |
| Commission member roles | `includes/admin-metabox.php` (przewodniczacy, sekretarz, czlonek) |
| Legal wording (regulation citations, "Ministerstwo Sportu i Turystyki" as co-controller) | Stays in the built-in Polish forms (`includes/generator.php`) by design since Phase 4; other wording goes through a template. The organization name in it and the form consent text come from settings since Phase 2. |

## Target architecture

- `Settings` module: one options array `oe_settings` (organization profile, email sender, payment info, document city, logo attachment ID), with a WP Admin settings page and sanitization.
- `Exam types` as data, not code: a custom post type or a custom table `oe_exam_type` with fields: label, short code (used in file names), decision number, theory venues, practice venues, task set, which documents apply. Replaces the string-keyed arrays in five files.
- `Venues` per organization, editable in the UI (add, edit, assign to exam types and to theory or practice).
- `Commission` members as a reusable list in settings (name, role), selectable per exam, instead of retyping names on every exam.
- Capabilities: define `oe_manage_exams` and `oe_generate_documents`, grant them to administrator by default and let the organization assign them to other roles. Replace every `edit_posts` check with these.
- `Documents`: see Phase 4.
- Seed data: ship `seed/ocean-wiedzy.json` (current organization, venues, commission, decisions) and an importer, so K's own install can be loaded in one step and other organizations start from an empty or example profile.
- Internationalization: wrap strings in `__()` with a `ocean-egzaminy` text domain, Polish as the base language.

## Phases

1. Baseline cleanup. **Done 2026-10-02.** Removed `access-guard.php` and its require, merged the four decision-number maps into `oe_get_decyzje()` (`includes/emails.php`), deleted dead code (`oe_losuj_zadania` wrapper, no-op `update_post_metadata` filter, empty `oe_create_tables`, empty `vendor/`), replaced em dashes with hyphens. Checks and zip build are PHP scripts (`bin/check.php`, `bin/build-zip.php`) instead of shell scripts, so they run on Windows without bash. Until Phase 2, plugin screens and document downloads are gated only by `edit_posts`, which includes the Contributor and Author roles.
2. Settings and capabilities. **Done 2026-10-02 (version 1.1.0).** `includes/settings.php`: option `oe_settings`, page under Egzaminy > Ustawienia (`manage_options`), `oe_setting()` plus derived helpers, JSON export/import, admin notice while the organization name is empty. Logos are stored as URLs (media library picker), not attachment IDs. `includes/capabilities.php`: `oe_manage_exams` (both CPTs via mapped primitive caps, status changes, bulk actions, CSV) and `oe_generate_documents` (DOCX metabox and download); administrators get both through an `admin_init` upgrade routine (activation does not run on zip replacement), other roles are ticked on the settings page. K's data is in `seed/ocean-wiedzy.json`, which is not shipped in the zip; she imports it once after updating. Runtime test: `tests/smoke.php` (see `docs/TESTING.md`).
3. Data model for exam types and venues. **Done 2026-10-02 (version 1.2.0).** `includes/rodzaje.php`: CPT `oe_rodzaj` under Egzaminy > Typy egzaminów (`manage_options`) with code, decision number, theory and practice venues, exam card rows, task sections (name, draw min/max, tasks with `[zawsze]`/`[nigdy]` markers), parental consent flag, question count and answer key, all in meta `_oe_rodzaj_dane`. Exams store `_oe_rodzaj_id` plus the synced name in `_oe_rodzaj_egzaminu`; `oe_migruj_rodzaje_egzaminow()` links existing exams by name (runs after import and after saving a type; unmatched exams get an admin notice). `miejsca-egzaminow.php` and `zadania-egzaminow.php` are gone; their data was extracted by script into `seed/ocean-wiedzy.json`. Settings export/import now carries the types too. Regression check: all 9 documents for all 5 types were generated with the 1.1.0 code and the new code and are byte-identical (also across PHP 7.4 and 8.5).
   Since 1.3.2 the five standard types (names, codes, ministry tasks and draw rules, card rows, parental consent flag, question counts; no decision numbers, venues or answer keys) ship in `includes/rodzaje-standardowe.php` and are created automatically, once, on a site that has no exam types. K reported an empty type list after updating without importing the seed; the import still adds her organization data to these types by name.
   Decision: venues are a list per type (one per line), not shared venue records. The same place is spelled differently across types in the current data, so merging them needs K's judgement; shared venues can come later without breaking anything.
   Not done here: reusable commission members (target architecture) and commission roles remain hardcoded in the exam metabox.
4. Document generation. **Done 2026-10-02 (version 1.3.0), route (b) chosen by K.** Built-in layouts stay for the nine standard Polish forms (unchanged, byte-identical). Each exam type sets every document to built-in, its own DOCX template, or off, and can add extra template documents (`includes/szablony.php`, "Dokumenty" section of the type editor, templates picked from the media library). Template engine: `{placeholder}` replacement in the body, headers and footers; placeholders split by Word across runs are joined first; table rows with `{u.*}` repeat per approved participant and rows with `{k.*}` per commission member; "one copy per participant" repeats the whole body with page breaks; multi-line values become line breaks; clones drop duplicate bookmarks and w14 paragraph IDs; unknown placeholders stay visible. A sample template with every placeholder is downloadable from the type editor.
   Not done: template files are referenced by attachment ID, so the settings export does not carry them to another site (they must be uploaded again there; the import drops IDs that do not exist). The built-in forms keep their fixed legal wording (regulation citations, MSiT as co-controller); an organization that needs different wording switches that document to a template.
5. Packaging for distribution. Version number, readme with screenshots, update mechanism (or a documented manual update), uninstall cleanup, text domain and translation file, license choice.

## Risks

- Reordering tasks or sections in an exam type changes the deterministic draw for every participant of that type, including documents regenerated for past exams.
- The ministry paperwork layouts and legal citations are specific to Polish sailing licenses. Another country or sport needs different wording, so legal text must come from templates or settings, not code.
- Hosting is PHP 7.4 now and the newest PHP later. Decision (K, 2026-10-02): support both, so keep 7.4 syntax and avoid anything deprecated in PHP 8. `bin/check.php` lints on 7.4 and the newest 8.x.
