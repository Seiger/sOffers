<?php
/**
 * Manager, feature and model checks using a fake CMS and SQLite :memory:.
 *
 * Pass an installed Illuminate vendor directory. No CMS bootstrap, live database,
 * migration runner, HTTP or application-specific Composer autoload files are used.
 */

namespace EvolutionCMS {
    /** Provider base double: module registration only, no CMS boot. @since 1.1.0 */
    class ServiceProvider
    {
        protected $app;
        /** Retain the registration recorder. @since 1.1.0 */
        public function __construct($app) { $this->app = $app; }
        /** Do not execute package plugins in this isolated test. @since 1.1.0 */
        protected function loadPluginsFrom($path) {}
    }
}

namespace Seiger\sOffers {
    function realpath($path)
    {
        if (!empty($GLOBALS['outsideLanguage']) && substr($path, -14) === '/uk/global.php') {
            return $GLOBALS['outsideLanguage'];
        }
        return \realpath($path);
    }
    // Simulate missing/unreadable translations without changing package files.
    function is_readable($path)
    {
        return empty($GLOBALS['unreadable'][$path]) && \is_readable($path);
    }
}

namespace {
    $vendor = isset($argv[1]) ? realpath($argv[1]) : false;
    if ($vendor === false || !is_file($vendor . '/composer/autoload_psr4.php')) {
        fwrite(STDERR, "Usage: php tests/p1-regression.php /path/to/cms/core/vendor\n");
        exit(1);
    }
    $prefixes = ['Seiger\\sOffers\\' => [dirname(__DIR__) . '/src']] + require $vendor . '/composer/autoload_psr4.php';
    spl_autoload_register(function ($class) use ($prefixes) {
        foreach ($prefixes as $prefix => $directories) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) { continue; }
            foreach ($directories as $directory) {
                $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) { require $file; return; }
            }
        }
    });
    require_once $vendor . '/illuminate/collections/helpers.php';
    require_once $vendor . '/illuminate/support/helpers.php';
    foreach (['collections', 'support'] as $package) {
        if (is_file($vendor . '/illuminate/' . $package . '/functions.php')) {
            require_once $vendor . '/illuminate/' . $package . '/functions.php';
        }
    }
    foreach (['php84', 'php85'] as $polyfill) {
        $bootstrap = $vendor . '/symfony/polyfill-' . $polyfill . '/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
    }

    use Illuminate\Container\Container;
    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Database\Eloquent\MassAssignmentException;
    use Illuminate\Events\Dispatcher;
    use Illuminate\Routing\Router;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Facade;
    use Illuminate\Support\Facades\Schema;
    use Seiger\sOffers\Models\sOFeature;
    use Seiger\sOffers\Models\sOffer;
    use Seiger\sOffers\Controllers\sOfferController;
    use Seiger\sOffers\sOfferServiceProvider;

    /** Test facade for the optional sLang service. @since 1.1.0 */
    class P1LanguageFacade extends Facade
    {
        /** Resolve the same container binding as the sLang facade. @since 1.1.0 */
        protected static function getFacadeAccessor() { return 'sLang'; }
    }

    /** Catalog fixture with regional codes and invalid identifiers. @since 1.1.0 */
    class P1LanguageCatalog
    {
        /** Return catalog entries keyed by language code. @since 1.1.0 */
        public function langList()
        {
            return array_fill_keys(['uk', 'en', 'pt-br', 'es-mx', 'fr', 'de', 'fid', 'alias', 'uk`'], []);
        }
    }

    /** Fake manager identity, role permissions and CMS language configuration. @since 1.1.0 */
    class P1Cms
    {
        public $permissions = ['exec_module', 'soffers', 'soffers_features', 'soffers_settings', 'soffers_delete'];
        public $config = [];
        public $cachePath = '';
        /** Resolve only the fixture configuration. @since 1.1.0 */
        public function getConfig($key, $default = null) { return $this->config[$key] ?? $default; }
        /** Check registered services in the isolated Laravel container. @since 1.1.0 */
        public function bound($key) { return Facade::getFacadeApplication()->bound($key); }
        /** Resolve the module ID from the same container as the provider. @since 1.1.0 */
        public function make($key) { return Facade::getFacadeApplication()->make($key); }
        /** Supply an authenticated fake manager. @since 1.1.0 */
        public function getLoginUserID($context) { return $context === 'mgr' ? 1 : 0; }
        /** Require the same explicit manager context as production. @since 1.1.0 */
        public function hasPermission($key, $context) { return $context === 'mgr' && in_array($key, $this->permissions, true); }
        /** Return only the disposable cache fixture path. @since 1.1.0 */
        public function getSiteCacheFilePath() { return $this->cachePath; }
        /** Capture a native manager alert without rendering or exiting the test. @since 1.1.0 */
        public function webAlertAndQuit($message) { throw new RuntimeException($message, http_response_code()); }
    }
    /** Request double for POST/CSRF preconditions, without HTTP dispatch. @since 1.1.0 */
    class P1Request
    {
        public $method = 'post';
        /** Supply the known session token only. @since 1.1.0 */
        public function input($key) { return $key === '_token' ? 'test-token' : null; }
        /** Compare the fixture method. @since 1.1.0 */
        public function isMethod($method) { return $this->method === $method; }
        /** No CSRF header is needed in these form fixtures. @since 1.1.0 */
        public function header($key) { return null; }
    }
    /** Module-registration recorder for the actual provider method. @since 1.1.0 */
    class P1App extends Container
    {
        public $modules = [];
        /** Capture registration and return the label-based ID used by Evo. @since 1.1.0 */
        public function registerModule($name, $path, $icon)
        {
            $this->modules[] = [$name, $path, $icon];
            return md5($name);
        }
    }
    function evo() { return $GLOBALS['cms']; }
    function request() { return $GLOBALS['request']; }
    function __($key) { return $key; }
    function csrf_token() { return $_SESSION['_token']; }
    function check($condition, $message)
    {
        $GLOBALS['checks']++;
        if (!$condition) { throw new LogicException($message); }
    }
    function rejects($callback, $class = InvalidArgumentException::class, $status = null)
    {
        try { $callback(); } catch (Throwable $exception) {
            check($exception instanceof $class, 'Unexpected exception: ' . get_class($exception) . ': ' . $exception->getMessage());
            if ($status !== null) { check($exception->getCode() === $status, 'Wrong denial status.'); }
            return;
        }
        throw new LogicException('Expected rejection.');
    }
    function inputRows($rows)
    {
        global $controller;
        $input = ['fid' => [], 'alias' => [], 'badge' => [], 'color' => []];
        foreach ($controller->langList() as $code) { $input[$code] = []; }
        foreach ($rows as $row) {
            foreach ($input as $field => $_) { $input[$field][] = $row[$field] ?? ''; }
        }
        return $input;
    }
    function snapshot()
    {
        return [DB::table('s_o_features')->orderBy('fid')->get()->toJson(), DB::table('s_offer_features')->orderBy('feature')->get()->toJson()];
    }

    $checks = 0;
    $GLOBALS['cms'] = new P1Cms();
    $GLOBALS['request'] = new P1Request();
    $_SESSION = ['_token' => 'test-token'];
    define('IN_MANAGER_MODE', true);
    define('EVO_MANAGER_URL', 'https://example.invalid/manager/');

    $container = new P1App();
    $capsule = new Capsule($container);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'p1_']);
    $capsule->setEventDispatcher(new Dispatcher($container));
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    Facade::setFacadeApplication($container);
    $controller = new sOfferController();
    $container->instance('db', $capsule->getDatabaseManager());
    $container->bind('db.schema', function () use ($capsule) { return $capsule->getConnection()->getSchemaBuilder(); });
    $router = new Router(new Dispatcher($container), $container);
    $container->instance('router', $router);
    require dirname(__DIR__) . '/src/Http/routes.php';
    $routes = $router->getRoutes()->getRoutes();
    check(count($routes) === 1 && $routes[0]->uri() === 'soffer' && $routes[0]->methods() === ['GET', 'HEAD'],
        'The manager route set contains an unexpected route.');
    check($routes[0]->getActionName() === sOfferController::class . '@index' && in_array('mgr', $routes[0]->middleware(), true),
        'The manager route lost its controller action or middleware.');
    $redirect = $controller->index();
    $target = parse_url($redirect->getTargetUrl());
    parse_str($target['query'], $query);
    check($redirect->getStatusCode() === 302 && $target['host'] === 'example.invalid' && $target['path'] === '/manager/index.php'
        && $query['a'] === '112' && $query['_token'] === 'test-token', 'The manager entry did not redirect to the file module.');
    $cms->permissions = array_values(array_diff($cms->permissions, ['soffers']));
    rejects(function () use ($controller) { $controller->index(); }, RuntimeException::class, 403);
    $cms->permissions[] = 'soffers';

    Schema::create('s_o_features', function (Blueprint $table) {
        $table->increments('fid'); $table->integer('position')->default(0);
        $table->string('alias'); $table->string('badge')->default('');
        $table->string('color', 100)->default(''); $table->string('base')->default(''); $table->timestamps();
    });
    Schema::create('s_offer_features', function (Blueprint $table) { $table->integer('offer'); $table->integer('feature'); });

    $cms->config = ['s_lang_config' => 'uk,en', 's_lang_default' => 'uk'];
    check($controller->langList() === ['base'] && $controller->langDefault() === 'base', 'Stale settings enabled languages without sLang.');
    $baseColumns = Schema::getColumnListing('s_o_features');
    $controller->setModifyTables();
    check(Schema::getColumnListing('s_o_features') === $baseColumns, 'Schema changed without sLang.');

    if (!class_exists(\Seiger\sLang\Facades\sLang::class)) {
        class_alias(P1LanguageFacade::class, \Seiger\sLang\Facades\sLang::class);
    }
    check($controller->langList() === ['base'] && $controller->langDefault() === 'base', 'An unregistered sLang service changed the fallback.');
    $container->instance('sLang', new P1LanguageCatalog());
    foreach ([['', 'base', ['base']], [' UK, en ', 'uk', ['uk', 'en']], ['es-mx', 'es-mx', ['es-mx']]] as [$codes, $default, $expected]) {
        $cms->config = ['s_lang_config' => $codes, 's_lang_default' => $default];
        check($controller->langList() === $expected && $controller->langDefault() === $default, 'Validated language settings differ.');
    }
    $invalid = array_map(function ($codes) {
        return [$codes, 'en'];
    }, ['en,fid', 'en,alias', 'created_at', 'en,', 'en,en', 'en,nl', 'en,uk`', 'en,uk\'']);
    $invalid[] = [[], 'en'];
    $invalid[] = ['en', 'uk'];
    foreach ($invalid as [$codes, $default]) {
        $cms->config = ['s_lang_config' => $codes, 's_lang_default' => $default];
        rejects(function () use ($controller) { $controller->langList(); });
    }
    $cms->config = ['s_lang_config' => 'uk,en,pt-br', 's_lang_default' => 'uk'];
    $controller->setModifyTables();
    check(Schema::hasColumns('s_o_features', ['uk', 'en', 'pt-br']), 'Allowed language columns were not added.');
    $columns = Schema::getColumnListing('s_o_features');
    $controller->setModifyTables();
    check(Schema::getColumnListing('s_o_features') === $columns, 'Repeated schema sync changed columns.');
    $cms->config['s_lang_config'] = 'uk,alias';
    rejects(function () use ($controller) { $controller->setModifyTables(); });
    check(Schema::getColumnListing('s_o_features') === $columns, 'Invalid code reached DDL.');
    $cms->config = ['s_lang_config' => 'fr', 's_lang_default' => 'fr'];
    $controller->setModifyTables();
    check(Schema::hasColumn('s_o_features', 'fr'), 'A sole non-base default language did not receive its column.');
    $cms->config = ['s_lang_config' => 'uk,en,pt-br', 's_lang_default' => 'uk'];
    $cms->permissions = ['exec_module', 'soffers', 'soffers_features'];
    rejects(function () use ($controller) { $controller->setModifyTables(); }, RuntimeException::class, 403);
    $cms->permissions[] = 'soffers_settings'; $cms->permissions[] = 'soffers_delete';
    $request->method = 'get';
    rejects(function () use ($controller) { $controller->setModifyTables(); }, RuntimeException::class, 405);
    $request->method = 'post';

    // Compile MySQL DDL without creating a MySQL PDO connection or executing SQL.
    $mysql = new Illuminate\Database\MySqlConnection(function () { throw new LogicException('No MySQL connection is allowed.'); }, '', 'p1_');
    $mysql->setSchemaGrammar(new Illuminate\Database\Schema\Grammars\MySqlGrammar($mysql));
    $blueprint = new Blueprint($mysql, 's_o_features', function (Blueprint $table) { $table->string('pt-br', 255)->default(''); });
    $sql = implode(' ', $blueprint->toSql());
    check(strpos($sql, '`p1_s_o_features`') !== false && strpos($sql, '`pt-br`') !== false, 'MySQL identifiers/prefix are not quoted.');
    check(stripos($sql, 'comment') === false, 'A dynamic SQL comment was generated.');

    $first = ['alias' => 'First', 'badge' => 'A', 'color' => 'red', 'uk' => 'Перша', 'en' => 'First', 'pt-br' => 'Primeira'];
    $second = ['alias' => 'Second', 'badge' => 'B', 'color' => 'blue', 'uk' => 'Друга', 'en' => 'Second', 'pt-br' => 'Segunda'];
    $controller->saveFeatures(inputRows([$first, $second]), []);
    $ids = sOFeature::orderBy('fid')->pluck('fid')->all();
    check(count($ids) === 2, 'New features were not saved.');
    DB::table('s_offer_features')->insert(['offer' => 7, 'feature' => $ids[0]]);
    $first['fid'] = $ids[0]; $first['alias'] = 'Renamed'; $first['uk'] = 'Нова назва';
    $controller->saveFeatures(inputRows([$first]), []);
    check(sOFeature::find($ids[0])->alias === 'renamed' && sOFeature::find($ids[0])->base === 'Нова назва', 'Rename/base mirror failed.');
    check(sOFeature::find($ids[1])->alias === 'second', 'Omitted feature was changed or deleted.');
    check(DB::table('s_offer_features')->where('feature', $ids[0])->exists(), 'Rename removed relations.');
    $second['fid'] = $ids[1];
    $controller->saveFeatures(inputRows([$second, $first]), []);
    check(sOFeature::find($ids[1])->position === 0 && sOFeature::find($ids[0])->position === 1, 'Reordering changed identity or ignored server positions.');
    $before = snapshot();
    $controller->saveFeatures([], []);
    check(snapshot() === $before, 'Empty input deleted the feature set.');

    $good = inputRows([$first]);
    $bad = [];
    foreach (['position', 'created_at', 'updated_at', 'exists', 'unknown'] as $field) { $input = $good; $input[$field] = ['extra']; $bad[] = $input; }
    $input = $good; unset($input['badge']); $bad[] = $input;
    $input = $good; $input['en'] = []; $bad[] = $input;
    $input = $good; $input['en'][0] = []; $bad[] = $input;
    $input = $good; $input['fid'][0] = '1x'; $bad[] = $input;
    $input = $good; $input['color'][0] = str_repeat('a', 101); $bad[] = $input;
    $input = $good; $input['uk'][0] = ''; $bad[] = $input;
    $input = $good; $input['fid'][0] = 999; $bad[] = $input;
    $input = $good; $input['alias'][0] = 'second'; $bad[] = $input;
    $bad[] = inputRows([$first, $first]);
    $duplicate = $second; $duplicate['alias'] = 'renamed'; $bad[] = inputRows([$first, $duplicate]);
    $unknown = $second; $unknown['fid'] = 999; $unknown['alias'] = 'unknown';
    $bad[] = inputRows([$first, $unknown]);
    $bad[] = inputRows(array_fill(0, 251, $first));
    foreach ($bad as $input) {
        rejects(function () use ($controller, $input) { $controller->saveFeatures($input, []); });
        check(snapshot() === $before, 'Invalid input changed persisted rows or relations.');
    }
    rejects(function () use ($controller, $good, $ids) { $controller->saveFeatures($good, [$ids[0]]); });
    rejects(function () use ($controller) { $controller->saveFeatures([], [999]); });
    rejects(function () use ($controller) { $controller->saveFeatures([], [0]); });
    rejects(function () use ($controller, $ids) { $controller->saveFeatures([], [$ids[0], $ids[0]]); });
    $cms->permissions = array_values(array_diff($cms->permissions, ['soffers_delete']));
    rejects(function () use ($controller, $ids) { $controller->saveFeatures([], [$ids[0]]); }, RuntimeException::class, 403);
    check(snapshot() === $before, 'Denied deletion changed data.');
    $cms->permissions[] = 'soffers_delete';

    $second['fid'] = $ids[1]; $second['alias'] = 'Second edited';
    $first['alias'] = 'Renamed edited';
    sOFeature::saving(function ($feature) use ($ids) { return (int)$feature->fid !== $ids[1]; });
    rejects(function () use ($controller, $first, $second) { $controller->saveFeatures(inputRows([$first, $second]), []); }, RuntimeException::class);
    check(snapshot() === $before, 'Cancelled second save left a partial first update.');
    sOFeature::flushEventListeners();
    sOFeature::deleting(function () { return false; });
    rejects(function () use ($controller, $ids) { $controller->saveFeatures([], [$ids[0]]); }, RuntimeException::class);
    check(snapshot() === $before, 'Cancelled deletion did not restore pivot cleanup.');
    sOFeature::flushEventListeners();
    $controller->saveFeatures([], [$ids[0]]);
    check(sOFeature::find($ids[0]) === null && !DB::table('s_offer_features')->where('feature', $ids[0])->exists(), 'Explicit deletion left a feature or its relations.');
    check(sOFeature::find($ids[1]) !== null, 'Explicit deletion removed an unrelated feature.');
    $cms->config = ['s_lang_config' => 'de', 's_lang_default' => 'de'];
    $before = snapshot();
    rejects(function () use ($controller) { $controller->saveFeatures(inputRows([['alias' => 'neu', 'de' => 'Neu']]), []); });
    check(snapshot() === $before && !Schema::hasColumn('s_o_features', 'de'), 'A feature save created a column or partially saved before schema readiness.');

    Schema::create('s_offers', function (Blueprint $table) {
        $table->increments('id');
        $table->string('alias');
        $table->boolean('published')->default(false);
        $table->text('tmplvars')->nullable();
        $table->timestamps();
    });
    $offer = new sOffer();
    rejects(function () use ($offer) { $offer->fill(['unexpected' => 'extra']); }, MassAssignmentException::class);
    rejects(function () { sOffer::withoutGlobalScopes()->create(['alias' => 'mass-assigned', 'published' => true]); },
        MassAssignmentException::class);
    check(DB::table('s_offers')->count() === 0, 'Rejected mass assignment created an offer.');
    $offer->alias = 'explicit-offer';
    $offer->published = false;
    $offer->save();
    $offer->tmplvars = '{"colour":"blue"}';
    $offer->save();
    $offer->features()->sync([$ids[1]]);
    rejects(function () use ($offer) { $offer->update(['published' => true]); }, MassAssignmentException::class);
    $storedOffer = DB::table('s_offers')->where('id', $offer->id)->first();
    check($storedOffer->alias === 'explicit-offer' && (int)$storedOffer->published === 0
        && $storedOffer->tmplvars === '{"colour":"blue"}', 'The assignment policy changed explicit writes or accepted a bulk update.');
    check(DB::table('s_offer_features')->where('offer', $offer->id)->where('feature', $ids[1])->exists(),
        'The assignment policy interrupted the feature relation sync.');

    $cache = tempnam(sys_get_temp_dir(), 'soffers-cache-');
    if ($cache === false) { throw new RuntimeException('Cannot create the cache fixture.'); }
    $cms->cachePath = $cache;
    try {
        $app = $container;
        $provider = new sOfferServiceProvider($app);
        $registeredModule = function ($locale) use ($app, $provider) {
            $_SESSION['mgrUsrConfigSet']['manager_language'] = $locale;
            $app->modules = [];
            $provider->register();
            return $app->modules[0];
        };
        $expectedModule = function (string $locale): array {
            $labels = require dirname(__DIR__) . '/lang/' . $locale . '/global.php';
            return [$labels['offers'], dirname(__DIR__) . '/module/sOfferModule.php', $labels['offers_icon']];
        };
        $english = $expectedModule('en');
        $ukrainian = $expectedModule('uk');
        foreach (['en' => $english, 'uk' => $ukrainian, 'fr' => $english] as $locale => $expected) {
            check($registeredModule($locale) === $expected, 'Unexpected registered module for ' . $locale);
            $controller = new sOfferController();
            parse_str(parse_url($controller->index()->getTargetUrl(), PHP_URL_QUERY), $query);
            check($query['id'] === md5($expected[0]), 'Redirect did not use the registered ID for ' . $locale);
        }
        file_put_contents($cache, '<?php $c["manager_language"]="uk";');
        check($registeredModule(null) === $ukrainian, 'Cache locale was not read as a literal.');
        check($registeredModule('en') === $english, 'Session locale did not take precedence.');
        foreach (['../uk', 'uk/global.php', 'fr', '', [], true] as $locale) {
            check($registeredModule($locale) === $english, 'Unsupported session locale did not fall back.');
        }
        check($registeredModule('uk') === $ukrainian && $registeredModule('uk') === $ukrainian, 'Repeated loading did not return the same labels.');
        if (file_put_contents($cache, "<?php return ['offers' => 'Outside fixture', 'offers_icon' => 'test-icon'];") === false) {
            throw new RuntimeException('Cannot write the outside-path fixture.');
        }
        $outsideLanguage = realpath($cache);
        check($registeredModule('uk') === $english, 'A resolved path outside package lang was not rejected.');
        $outsideLanguage = false;
        file_put_contents($cache, "<?php \$c['manager_language'] = 'ru';");
        check($registeredModule(null) === $expectedModule('ru'), 'Single-quoted cache locale failed.');
        file_put_contents($cache, '<?php $c["manager_language"]="../uk";');
        check($registeredModule(null) === $english, 'Unsupported cache locale did not fall back.');
        $unreadable[realpath(dirname(__DIR__) . '/lang/uk/global.php')] = true;
        check($registeredModule('uk') === $english, 'Unreadable localized file did not fall back.');
        $unreadable[realpath(dirname(__DIR__) . '/lang/en/global.php')] = true;
        rejects(function () use ($registeredModule) { $registeredModule('uk'); }, RuntimeException::class);
        $unreadable = [];
        $_SESSION['mgrUsrConfigSet']['manager_language'] = 'uk';
        $app->modules = [];
        $provider->register(); $provider->register();
        check(count($app->modules) === 2 && $app->modules[0] === $app->modules[1], 'Repeated provider registration changed labels or failed.');
        check($app->modules[0] === $ukrainian, 'Provider did not use allowlisted manager labels.');
    } finally {
        // Remove only the exact temporary cache fixture created by this test.
        unlink($cache);
    }

    echo 'PASS: ' . $checks . " manager, feature and model checks (fake CMS, SQLite :memory:)\n";
}
