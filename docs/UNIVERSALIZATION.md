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
| Organization name, street address, KRS, NIP, REGON | `includes/generator.php` (document headers, about lines 165-167, 205-210, 269, 614), `includes/emails.php` (about lines 229-244), `includes/shortcode.php` (consent text, about line 197) |
| Bank account number and account owner default | `includes/emails.php` (about lines 34 and 247), `includes/admin-metabox.php` (about line 204) |
| Reply-To email address | `includes/emails.php` (about line 264) |
| Payment logo URL on the original WordPress host | `includes/emails.php` (lines 42 and 237) |
| City in document date lines ("Katowice, <date>") | `includes/generator.php` (about lines 205 and 269) |
| Ministry decision numbers per exam type | duplicated in `includes/admin-metabox.php` (3 places), `includes/emails.php`, and referenced in `generator.php` |
| Exam type names as string keys | about 53 occurrences across `admin-metabox.php`, `emails.php`, `generator.php`, `miejsca-egzaminow.php`, `zadania-egzaminow.php` |
| Venue lists per exam type | `includes/miejsca-egzaminow.php` (one hardcoded array, includes the organization's own office as a venue) |
| Commission member roles | `includes/admin-metabox.php` (przewodniczacy, sekretarz, czlonek) |
| Shared password gate | `includes/access-guard.php`, required first in `ocean-egzaminy.php` |
| Legal wording (data controller, regulation citations, consent text) | `includes/generator.php` (about lines 614-616), `includes/shortcode.php` |

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

1. Baseline cleanup. Remove `access-guard.php` and its require. Remove duplicate decision-number maps. Delete dead code. Add the pre-finish checks from CLAUDE.md as a script (`bin/check.sh`). Commit a clean baseline before any refactor.
2. Settings and capabilities. Add the settings page, replace every hardcoded organization string with a settings read, introduce the two capabilities, add a `bin/build-zip.sh`.
3. Data model for exam types and venues. Create exam type storage, migrate the five current types and their venues and tasks from the seed file, replace string-key lookups with exam type IDs, keep a migration for existing `_oe_rodzaj_egzaminu` meta.
4. Document generation. Today `generator.php` (about 820 lines) builds nine fixed paperwork forms in code. With arbitrary exam types there are two realistic routes: (a) DOCX templates with placeholders that each organization uploads and the plugin fills (flexible, more work for the organization), or (b) keep built-in layouts for the standard forms and add a template route only for custom exam types. Decide this with K before starting, it drives the size of the whole project.
5. Packaging for distribution. Version number, readme with screenshots, update mechanism (or a documented manual update), uninstall cleanup, text domain and translation file, license choice.

## Risks

- Existing data uses the old string-keyed exam types. A migration is required or running exams lose their type.
- The ministry paperwork layouts and legal citations are specific to Polish sailing licenses. Another country or sport needs different wording, so legal text must come from templates or settings, not code.
- Hosting is PHP 7.4. If distribution targets other hosts, test on 7.4 and on a current PHP version, and keep 7.4 syntax until K says otherwise.
