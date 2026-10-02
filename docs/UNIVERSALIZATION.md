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
| Ministry decision numbers per exam type | one map in `oe_get_decyzje()` in `includes/emails.php` (merged in Phase 1), referenced in `admin-metabox.php` and `generator.php` |
| Exam type names as string keys | about 53 occurrences across `admin-metabox.php`, `emails.php`, `generator.php`, `miejsca-egzaminow.php`, `zadania-egzaminow.php` |
| Venue lists per exam type | `includes/miejsca-egzaminow.php` (one hardcoded array, includes the organization's own office as a venue) |
| Commission member roles | `includes/admin-metabox.php` (przewodniczacy, sekretarz, czlonek) |
| Legal wording (regulation citations, "Ministerstwo Sportu i Turystyki" as co-controller) | `includes/generator.php` (karty RODO block), Phase 4. The organization name in it and the form consent text come from settings since Phase 2. |

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
3. Data model for exam types and venues. Create exam type storage, migrate the five current types and their venues and tasks from the seed file, replace string-key lookups with exam type IDs, keep a migration for existing `_oe_rodzaj_egzaminu` meta.
4. Document generation. Today `generator.php` (about 820 lines) builds nine fixed paperwork forms in code. With arbitrary exam types there are two realistic routes: (a) DOCX templates with placeholders that each organization uploads and the plugin fills (flexible, more work for the organization), or (b) keep built-in layouts for the standard forms and add a template route only for custom exam types. Decide this with K before starting, it drives the size of the whole project.
5. Packaging for distribution. Version number, readme with screenshots, update mechanism (or a documented manual update), uninstall cleanup, text domain and translation file, license choice.

## Risks

- Existing data uses the old string-keyed exam types. A migration is required or running exams lose their type.
- The ministry paperwork layouts and legal citations are specific to Polish sailing licenses. Another country or sport needs different wording, so legal text must come from templates or settings, not code.
- The signup form shortcode calls `session_start()` while the page is already being output. It only works on hosts with `output_buffering` enabled. Replace the session with a query arg or a transient.
- Hosting is PHP 7.4 now and the newest PHP later. Decision (K, 2026-10-02): support both, so keep 7.4 syntax and avoid anything deprecated in PHP 8. `bin/check.php` lints on 7.4 and the newest 8.x.
