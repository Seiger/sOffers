# sOffers for Evolution CMS 3
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/soffer?label=version)](https://packagist.org/packages/seiger/soffer)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/soffer)
[![License](https://img.shields.io/packagist/l/seiger/soffer)](https://packagist.org/packages/seiger/soffer)
[![Issues](https://img.shields.io/github/issues/Seiger/soffer)](https://github.com/Seiger/soffer/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/soffer)](https://packagist.org/packages/seiger/soffer)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/soffer)](https://packagist.org/packages/seiger/soffer)

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