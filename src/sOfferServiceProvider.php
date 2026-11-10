<?php namespace Seiger\sOffers;

use EvolutionCMS\ServiceProvider;
use Event;

/**
 * Register the package, manager translations and CLI-discoverable migrations.
 */
class sOfferServiceProvider extends ServiceProvider
{
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
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add plugins to Evo
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');

        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add module to Evo. Module ID is md5('sOfferModule').
            $lang = 'en';
            if (isset($_SESSION['mgrUsrConfigSet']['manager_language'])) {
                $lang = $_SESSION['mgrUsrConfigSet']['manager_language'];
            } else {
                if (is_file(evo()->getSiteCacheFilePath())) {
                    $siteCache = file_get_contents(evo()->getSiteCacheFilePath());
                    preg_match('@\$c\[\'manager_language\'\]="\w+@i', $siteCache, $matches);
                    if (count($matches)) {
                        $lang = str_replace('$c[\'manager_language\']="', '', $matches[0]);
                    }
                }
            }
            $lang = include_once dirname(__DIR__) . '/lang/' . $lang . '/global.php';
            $this->app->registerModule($lang['offers'], dirname(__DIR__) . '/module/sOfferModule.php', $lang['offers_icon']);
        }
    }
}
