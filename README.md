# sOffers for Evolution CMS 3
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/soffers?label=version)](https://packagist.org/packages/seiger/soffers)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/soffers)
[![License](https://img.shields.io/packagist/l/seiger/soffers)](https://packagist.org/packages/seiger/soffers)
[![Issues](https://img.shields.io/github/issues/Seiger/soffers)](https://github.com/Seiger/soffer/soffers)
[![Stars](https://img.shields.io/packagist/stars/Seiger/soffers)](https://packagist.org/packages/seiger/soffers)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/soffers)](https://packagist.org/packages/seiger/soffers)
![Made in Ukraine](https://img.shields.io/badge/made_in-Ukraine-ffd700.svg?labelColor=0057b7)

**sOffers** Module for records management in the Evolution CMS admin panel.

## Requirements

Install sOffers in an existing Evolution CMS installation. The Composer constraints
are Evolution CMS `^3.5.7` and PHP `^8.3`. The Evolution CMS 3.5.7 baseline provides
the manager APIs and Illuminate 12 components used by this package. sLang is optional.

## Install by Artisan package installer

Run these commands from the Evolution CMS `core/` directory:

```console
php artisan package:installrequire seiger/soffers "*"
```

The installer adds the dependency to `core/custom/composer.json`. Evolution CMS
discovers `sOfferServiceProvider` from the package's `extra.laravel.providers` metadata.

Publish the configuration and apply the package migrations:

```console
php artisan vendor:publish --provider="Seiger\sOffers\sOfferServiceProvider"
php artisan migrate
```

## Configure

The published `core/custom/config/cms/settings/sOffer.php` returns the offer field
definitions. The manager settings form updates this file.

## Manager permissions

The package permission migration adds the following permissions to the shared
**Seiger packages** group (`seiger_packages`), also used by sTask. Apply the package
migrations through the normal CMS upgrade process before opening the updated module.
Only administrator role `1` receives the initial grants. Assign other manager roles
explicitly and refresh their manager sessions after changing permissions.

Every module action requires an authenticated manager, `exec_module`, `soffers`,
and the CMS file-module access checks. These are module-wide action permissions;
the package does not define per-offer access groups.

The manager GET `/soffer` entry redirects to the registered file module. Offer
editing, deletion and translation forms submit to file-module actions. The obsolete
upload, gallery, delete and translation route declarations have been removed.

| Permission | Allowed operation |
| --- | --- |
| `soffers` | Open sOffers and read its offers/features |
| `soffers_create` | Create offers |
| `soffers_save` | Edit offers, translations and TV values |
| `soffers_delete` | Delete offers or explicitly selected features |
| `soffers_publish` | Change publication state or publication date, including on creation |
| `soffers_features` | Create/edit/reorder features; explicit deletion also requires `soffers_delete` |
| `soffers_settings` | View/save settings and explicitly update feature language columns |

All mutating module branches require POST and the CMS manager-session CSRF token.
Opening the features tab is read-only; its separate **Update feature language columns**
button performs the settings-authorized schema update through Schema Builder.

Settings definitions keep their existing PHP-array format and use `var_export` plus
same-directory temporary files, a writer lock and atomic rename. The settings directory
must be writable by PHP; existing file permission bits are preserved. A resource ID must
be `0` (site root) or an existing, non-deleted CMS resource; omitting it preserves the
current resource setting. The builder writes the value with bindings.

The form accepts up to 250 field definitions with matching `key`, `name` and `type`
arrays. Normalized keys must be nonempty, unique and at most 255 ASCII characters;
names allow up to 255 Unicode characters. Supported types are `Text`, `Textarea`
and `RichText`. Malformed input is rejected before configuration, database or cache writes.

Database rollback requires a transactional `system_settings` table. File/database
coordination restores the previous file on caught failures; it cannot provide a
distributed transaction across a process crash or a lost database commit acknowledgement.
The configuration directory must remain trusted and unavailable for writes by other users.

The `sOffer` model guards all attributes from Eloquent mass assignment. The module
sets individual offer and TV attributes after checking the action permission.

## Feature editing and languages

Existing features are updated by `fid`. Renaming or reordering preserves IDs and offer
relations. Omitted rows and an empty `features` array preserve stored records. Only
positive IDs explicitly submitted in `deleted_features[]` delete features; the form
records these IDs when an existing row is removed. Deleting also removes its rows
from `s_offer_features` inside the same transaction. Both tables must support transactions.

The server accepts `fid`, `alias`, `badge`, `color` and configured language fields.
Unknown/system fields, mismatched arrays, duplicate IDs/aliases and stale IDs are
rejected before writes. Each save accepts up to 250 rows and 250 deletion IDs.
Text fields allow 255 Unicode characters; color allows 100. Aliases are slug-normalized
and must remain unique; collisions are rejected. Position follows submitted row order,
and `base` mirrors the default-language text. An explicit deletion needs both
`soffers_features` and `soffers_delete`.

With sLang available, `s_lang_config` selects codes from `sLang::langList()`.
Codes are trimmed and lowercased. Unknown codes, invalid identifiers, duplicates,
empty segments and a default outside the configured list are rejected.
Without sLang, sOffers uses `base` even if multilingual settings remain in the CMS.
Correct invalid language configuration through the CMS language settings before
opening sOffers.

The explicit language-column action creates only missing allowed columns with empty
defaults and preserves existing values. It includes a sole non-base default language.
If the default-language value is empty, its form field shows the existing `base` text
so older base-only feature data can be retained when editing.
Feature saves require the configured columns to exist and never run DDL themselves.
Schema changes have the target database's DDL/locking behavior; a failed schema update
may need to be retried. Concurrent edits still depend on the target transaction isolation;
per-record version checking is not part of this change.

Manager labels use a separate fixed `en/uk/ru` file map. The manager session takes
precedence over a literal locale read from the CMS cache. Unknown values fall back
to English; cache PHP is never evaluated. Resolved translation paths must stay within
the package language directory. Missing/invalid localized labels use English, and an
unavailable English fallback raises a controlled exception. The package language
directory must remain trusted.

## Isolated regression checks

Run from the package directory with PHP CLI; no Composer install or CMS bootstrap is needed:

```console
php tests/access-regression.php
php tests/permissions-regression.php
```

The suites use CMS/DB doubles and disposable config files. Permission registration is
tested in memory; no CMS migration runner or real database is used. These checks do
not replace integration testing on the target CMS/filesystem.

If an installed Illuminate vendor directory is available, compile and syntax-check
the Blade templates without rendering them or bootstrapping the CMS:

```console
php tests/compile-views.php /path/to/cms/core/vendor
```

The manager, feature and model checks use a fake CMS and SQLite `:memory:`
with installed Illuminate components:

```console
php tests/p1-regression.php /path/to/cms/core/vendor
```
