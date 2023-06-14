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