<?php namespace Seiger\sOffers;

use EvolutionCMS\ServiceProvider;
use Event;
use RuntimeException;

/**
 * Register the package, manager translations and CLI-discoverable migrations.
 */
class sOfferServiceProvider extends ServiceProvider
{
    private const MANAGER_LANGUAGE_FILES = ['en' => 'en/global.php', 'uk' => 'uk/global.php', 'ru' => 'ru/global.php'];

    /**
     * Load migrations in every context and expose manager routes, views and assets.
     *
     * The permission lexicon uses the shared Seiger group key also used by sTask.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sOffers');
        $this->registerManagerPermissionLexicon();

        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            include(__DIR__ . '/Http/routes.php');

            // Views
            $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sOffers');

            // For use config
            $this->publishes([
                dirname(__DIR__) . '/config/sOffersAlias.php' => config_path('app/aliases/sOffers.php', true),
                dirname(__DIR__) . '/config/sOffer.php' => config_path('cms/settings/sOffer.php', true),
                dirname(__DIR__) . '/images/noimage.png' => public_path('assets/images/noimage.png', true),
            ]);
        }

        $this->app->singleton(sOffers::class);
        $this->app->alias(sOffers::class, 'sOffers');
    }

    /**
     * Bridge package labels into the manager role editor's legacy lexicon.
     *
     * Reuse sTask's seiger_packages key so both packages appear in the same group.
     * Registration is deferred until ManagerTheme resolves, as in sTask.
     *
     * @return void
     * @since 1.1.0
     */
    protected function registerManagerPermissionLexicon(): void
    {
        if (!defined('IN_MANAGER_MODE') || !IN_MANAGER_MODE) {
            return;
        }
        $this->app->afterResolving('ManagerTheme', function ($managerTheme): void {
            $managerTheme->setLexicon('seiger_packages', __('sOffers::global.permissions_group'));
            foreach (['access', 'create', 'save', 'delete', 'publish', 'features', 'settings'] as $action) {
                $key = 'sOffers::global.permission_' . $action;
                $managerTheme->setLexicon($key, __($key));
            }
        });
    }

    /**
     * Register package plugins and the manager module with allowlisted labels.
     *
     * Locale values select fixed package files. The CMS cache is read as text.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evo
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');

        // Only Manager
        if (IN_MANAGER_MODE) {
            $lang = $this->loadManagerLanguage(
                $_SESSION['mgrUsrConfigSet']['manager_language'] ?? null,
                evo()->getSiteCacheFilePath()
            );
            $this->app->registerModule($lang['offers'], dirname(__DIR__) . '/module/sOfferModule.php', $lang['offers_icon']);
        }
    }

    /**
     * Resolve the manager labels from the session locale or the cached locale.
     *
     * An unsupported locale or invalid localized file falls back to English.
     * The cache is read as text and the translation file is loaded on each
     * registration so it always returns its labels.
     *
     * @param mixed $sessionLocale Manager-session language, or null to consult cache.
     * @param string $cachePath Cache path supplied by the CMS.
     * @return array<string, mixed> Labels including string offers and offers_icon.
     * @throws RuntimeException When no valid English fallback exists.
     * @since 1.1.0
     */
    private function loadManagerLanguage($sessionLocale, string $cachePath): array
    {
        $locale = $sessionLocale;
        if ($locale === null && is_file($cachePath) && is_readable($cachePath)) {
            $cache = @file_get_contents($cachePath, false, null, 0, 1048576);
            if (is_string($cache) && preg_match('/\$c\[([\'"])manager_language\1\]\s*=\s*([\'"])([a-z-]+)\2\s*;/i', $cache, $matches)) {
                $locale = $matches[3];
            }
        }
        $locale = is_string($locale) ? strtolower(trim($locale)) : 'en';
        $locale = isset(self::MANAGER_LANGUAGE_FILES[$locale]) ? $locale : 'en';
        $labels = $this->readManagerLanguage($locale);
        if ($labels === null && $locale !== 'en') {
            $labels = $this->readManagerLanguage('en');
        }
        if ($labels === null) {
            throw new RuntimeException('The sOffers English manager labels are missing or invalid.');
        }

        return $labels;
    }

    /**
     * Load an allowlisted translation whose resolved path stays inside package lang.
     *
     * @param string $locale Internal allowlisted locale.
     * @return array<string, mixed>|null Valid labels or an unavailable or invalid file.
     * @since 1.1.0
     */
    private function readManagerLanguage(string $locale): ?array
    {
        $root = realpath(dirname(__DIR__) . '/lang');
        if ($root === false || !isset(self::MANAGER_LANGUAGE_FILES[$locale])) {
            return null;
        }
        $path = realpath($root . '/' . self::MANAGER_LANGUAGE_FILES[$locale]);
        if ($path === false || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0
            || !is_file($path) || !is_readable($path)
        ) {
            return null;
        }
        try {
            $labels = require $path;
        } catch (\Throwable $exception) {
            return null;
        }
        if (!is_array($labels) || !isset($labels['offers'], $labels['offers_icon'])
            || !is_string($labels['offers']) || $labels['offers'] === ''
            || !is_string($labels['offers_icon']) || $labels['offers_icon'] === ''
        ) {
            return null;
        }

        return $labels;
    }
}
