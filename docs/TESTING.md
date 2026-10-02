# Testing

Two layers, both run locally without a web server or MySQL.

1. `php bin/check.php` lints every PHP file on PHP 7.4 and the newest 8.x and runs the static checks from CLAUDE.md.
2. `php tests/smoke.php` loads a real local WordPress (SQLite) and exercises the plugin: capabilities, settings, seed import, all emails, the signup form, the exam metabox, the settings page and all nine DOCX documents. Any PHP warning, notice or deprecation raised from plugin files fails the test. It wipes the plugin's data in that WordPress, so never point it at a real site.

Run both on both PHP versions before a release:

```
.tools\php74\php.exe bin\check.php
.tools\php74\php.exe tests\smoke.php
.tools\php85\php.exe tests\smoke.php
```

## Built-in documents must not change by accident

`tests/docs-snapshot.php` writes `word/document.xml` of all 9 built-in documents for every exam type in the seed and byte-compares two such snapshots:

```
git worktree add <tmp>\old <previous commit>
cmd /c rmdir .tools\wordpress\wp-content\plugins\ocean-egzaminy
New-Item -ItemType Junction -Path .tools\wordpress\wp-content\plugins\ocean-egzaminy -Target <tmp>\old\ocean-egzaminy
.tools\php74\php.exe tests\docs-snapshot.php .tools\wordpress seed\ocean-wiedzy.json <tmp>\snap-old
(relink the junction to .\ocean-egzaminy)
.tools\php74\php.exe tests\docs-snapshot.php .tools\wordpress seed\ocean-wiedzy.json <tmp>\snap-new
.tools\php74\php.exe tests\docs-snapshot.php compare <tmp>\snap-old <tmp>\snap-new
git worktree remove --force <tmp>\old
```

Phases 3 and 4 were verified this way: 45 of 45 files identical to 1.1.0, on PHP 7.4 and 8.5.

## Opening generated files in Word

`OE_SMOKE_KEEP=<dir>` makes `tests/smoke.php` keep a copy of every generated file (built-in documents, filled templates, the sample template). Open them in Word to confirm Word accepts them; on 2026-10-02 all of them opened without repair prompts (checked through Word COM, read-only). Creating or saving documents through Word COM hangs on this machine, so Word-authored template fixtures could not be produced automatically; the smoke test builds Word-style XML instead (split runs, proofErr, bookmarks, hyperlinks, w14:paraId).

## Local tools (`.tools/`, git-ignored)

Rebuild on a new machine (Windows, PowerShell):

1. PHP: unzip `php-7.4.33-nts-Win32-vc15-x64.zip` (windows.php.net archives) to `.tools/php74` and the newest `php-8.x-nts-Win32-vs17-x64.zip` to `.tools/php85`.
2. `php.ini` in each folder:
   ```
   extension_dir="<absolute path to that folder>\ext"
   extension=mbstring
   extension=pdo_sqlite
   extension=sqlite3
   extension=openssl
   extension=fileinfo
   memory_limit=512M
   ```
   PHP 8.x also needs `extension=zip` (7.4 has it built in).
3. PHP 7.4 ships SQLite 3.31, the SQLite plugin needs 3.37+. Copy `libsqlite3.dll` from `.tools/php85` over the one in `.tools/php74` (keep the original as `libsqlite3.dll.orig`).
4. WordPress: unzip https://wordpress.org/latest.zip to `.tools/wordpress`, unzip the `sqlite-database-integration` plugin into its `wp-content/plugins`, copy that plugin's `db.copy` to `wp-content/db.php` and replace `{SQLITE_IMPLEMENTATION_FOLDER_PATH}` with the plugin's absolute path (forward slashes) and `{SQLITE_PLUGIN}` with `sqlite-database-integration/load.php`. Create `wp-content/database/`.
5. `wp-config.php` with `DB_DIR` = `wp-content/database/`, `DB_FILE` = `oe-test.sqlite`, `WP_DEBUG` on, `WP_DEBUG_DISPLAY` off.
6. Link the plugin: `New-Item -ItemType Junction -Path .tools\wordpress\wp-content\plugins\ocean-egzaminy -Target <repo>\ocean-egzaminy`.
7. `.tools\php74\php.exe tests\wp-install.php`, then run the smoke test twice (the first run activates the plugin).

WP-CLI does not work with this SQLite setup, which is why `tests/wp-install.php` exists.
