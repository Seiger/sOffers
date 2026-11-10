# sOffers for Evolution CMS 3
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/soffers?label=version)](https://packagist.org/packages/seiger/soffers)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/soffers)
[![License](https://img.shields.io/packagist/l/seiger/soffers)](https://packagist.org/packages/seiger/soffers)
[![Issues](https://img.shields.io/github/issues/Seiger/soffers)](https://github.com/Seiger/soffer/soffers)
[![Stars](https://img.shields.io/packagist/stars/Seiger/soffers)](https://packagist.org/packages/seiger/soffers)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/soffers)](https://packagist.org/packages/seiger/soffers)

**sOffers** Module for records management in the Evolution CMS admin panel.

## Install by artisan package installer

Run in you /core/ folder:

```console
php artisan package:installrequire seiger/soffers "*"
```

Generate the config file in **core/custom/config/cms/settings** with 
name **soffer.php** the file should return a 
comma-separated list of templates.

```console
php artisan vendor:publish --provider="Seiger\sOffers\sOfferServiceProvider"
```

Run make DB structure with command:

```console
php artisan migrate
```

## Configure

Templates for displaying gallery tabs are configured in the 

```console
core/custom/config/cms/settings/sOffers.php
```

## Manager permissions

The package permission migration adds the following permissions to the shared
**Seiger packages** group (`seiger_packages`), also used by sTask. Apply the package
migrations through the normal CMS upgrade process before opening the updated module.
Only administrator role `1` receives the initial grants. Assign other manager roles
explicitly and refresh their manager sessions after changing permissions.

Every module action requires an authenticated manager, `exec_module`, `soffers`,
and the CMS file-module access checks. These are module-wide action permissions;
the package does not define per-offer access groups.

| Permission | Allowed operation |
| --- | --- |
| `soffers` | Open sOffers and read its offers/features |
| `soffers_create` | Create offers |
| `soffers_save` | Edit offers, translations and TV values |
| `soffers_delete` | Delete offers or remove features during full-set replacement |
| `soffers_publish` | Change publication state or publication date, including on creation |
| `soffers_features` | Save the feature set; removing omitted rows also requires `soffers_delete` |
| `soffers_settings` | View/save settings and explicitly update feature language columns |

All mutating module branches require POST and the CMS manager-session CSRF token.
Opening the features tab is read-only; its separate **Update feature language columns**
button performs the settings-authorized schema update. The SQL-identifier validation
work tracked in audit item B-05 remains separate from these access checks.

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
