<?php
/**
 * Exercise actual module dispatch with manager/model doubles and no CMS bootstrap.
 *
 * Run with `php tests/access-regression.php`. This includes the settings suite.
 * Allowed model writes stop at a sentinel; no real ORM or database is loaded.
 */

namespace Seiger\sOffers\Models {
    /** In-memory offer lookup and a sentinel at the write boundary. @since 1.1.0 */
    class sOffer extends \stdClass
    {
        public static $stored;
        /** Return only the lookup methods used by the controller. @since 1.1.0 */
        public static function where(...$arguments) { return new \OfferQueryDouble(); }
        /** Stop before any persistence can occur. @since 1.1.0 */
        public function save() { throw new \WriteBoundary('offer'); }
    }

    /** In-memory feature set for replacement/deletion authorization. @since 1.1.0 */
    class sOFeature extends \stdClass
    {
        public static $rows = [];
        /** Return the fixture set without a database. @since 1.1.0 */
        public static function all() { return self::$rows; }
        /** Expose the alias lookup used by validateAlias. @since 1.1.0 */
        public static function where(...$arguments) { return new \OfferQueryDouble(); }
        /** Expose a read-only list for display-branch checks. @since 1.1.0 */
        public static function orderBy(...$arguments) { return new \OfferQueryDouble(); }
        /** Stop at the first attempted update. @since 1.1.0 */
        public function update() { throw new \WriteBoundary('feature'); }
        /** Stop at the first attempted deletion. @since 1.1.0 */
        public function delete() { throw new \WriteBoundary('delete feature'); }
    }
}

namespace EvolutionCMS\Models {
    /** Resource lookup double used only for settings validation. @since 1.1.0 */
    class SiteContent
    {
        /** Return a fixture that reports no matching resource. @since 1.1.0 */
        public static function whereKey($id) { return new self(); }
        /** Reject positive resource IDs in the missing-resource scenario. @since 1.1.0 */
        public function exists() { return false; }
    }
}

namespace {
    use Illuminate\Support\Facades\DB;
    use Seiger\sOffers\Models\sOffer;
    use Seiger\sOffers\Models\sOFeature;
    use Seiger\sOffers\Controllers\sOfferController;

    // Keep the settings suite output buffered while checking response statuses.
    ob_start();
    require __DIR__ . '/p0-regression.php';
    define('IN_MANAGER_MODE', true);
    $start = $checks;

    /** Test sentinel for the terminal CMS alert. @since 1.1.0 */
    class ManagerAlert extends RuntimeException {}
    /** Deliberate stop at a model-write boundary. @since 1.1.0 */
    class WriteBoundary extends RuntimeException {}

    /** Minimal request data, verb and header fixture. @since 1.1.0 */
    class RequestDouble
    {
        public $data = [];
        public $method = 'POST';
        public $headers = [];
        /** Read the same flat inputs as the module under test. @since 1.1.0 */
        public function input($key, $default = null) { return $this->data[$key] ?? $default; }
        /** Preserve explicit null for validation checks. @since 1.1.0 */
        public function has($key) { return array_key_exists($key, $this->data); }
        /** Match the feature-array presence branch. @since 1.1.0 */
        public function filled($key) { return isset($this->data[$key]) && $this->data[$key] !== []; }
        /** Compare verbs without dispatching a request. @since 1.1.0 */
        public function isMethod($method) { return strtoupper($method) === $this->method; }
        /** Supply only explicitly configured CSRF headers. @since 1.1.0 */
        public function header($key) { return $this->headers[$key] ?? null; }
        /** Support legacy request property reads. @since 1.1.0 */
        public function __get($key) { return $this->input($key); }
    }

    /** Manager authentication/permissions and side-effect counters. @since 1.1.0 */
    class CmsDouble
    {
        public $user = 1;
        public $permissions = [];
        public $effects = 0;
        /** Require the explicit manager context in every auth check. @since 1.1.0 */
        public function getLoginUserID($context) { check($context === 'mgr', 'Wrong login context.'); return $this->user; }
        /** Do not infer admin permissions or use frontend context. @since 1.1.0 */
        public function hasPermission($key, $context = 'mgr') { check($context === 'mgr', 'Wrong permission context.'); return in_array($key, $this->permissions, true); }
        /** Supply defaults used by the real controller. @since 1.1.0 */
        public function getConfig($key, $default = null) { return $default; }
        /** Count unexpected config writes. @since 1.1.0 */
        public function setConfig(...$arguments) { $this->effects++; }
        /** Count unexpected cache writes. @since 1.1.0 */
        public function clearCache(...$arguments) { $this->effects++; }
        /** Capture the status and HTML message at the native alert boundary. @since 1.1.0 */
        public function webAlertAndQuit($message) { throw new ManagerAlert($message, http_response_code()); }
    }

    /** Read-only query results for controller/model authorization checks. @since 1.1.0 */
    class OfferQueryDouble
    {
        /** Return stored state rather than request-selected flags. @since 1.1.0 */
        public function firstOrNew() { return clone sOffer::$stored; }
        /** Alias queries return an empty fixture collection. @since 1.1.0 */
        public function get(...$arguments) { return new self(); }
        /** Keep alias collection lookup read-only. @since 1.1.0 */
        public function pluck(...$arguments) { return $this; }
        /** No alias collisions in these authorization fixtures. @since 1.1.0 */
        public function toArray() { return []; }
    }

    /** Capture render data without loading Blade or executing a view. @since 1.1.0 */
    class View
    {
        public static $data;
        /** Return an empty response and retain module data for assertions. @since 1.1.0 */
        public static function make($name, $data) { self::$data = $data; return ''; }
    }

    function evo() { return $GLOBALS['cms']; }
    function request() { return $GLOBALS['request']; }
    function __($key) { return $key; }
    function csrf_token() { return $_SESSION['_token']; }

    function expectAlert($callback, $status, $message = null)
    {
        try { $callback(); } catch (ManagerAlert $exception) {
            check($exception->getCode() === $status, 'Unexpected denial status.');
            if ($message !== null) {
                check($exception->getMessage() === $message, 'Unexpected manager alert message.');
            }
            return;
        }
        throw new RuntimeException('Expected an access denial.');
    }

    function dispatchModule($action, $input = [])
    {
        request()->data = $input + ['get' => $action, '_token' => 'manager-token'];
        include dirname(__DIR__) . '/module/sOfferModule.php';
    }

    $GLOBALS['cms'] = new CmsDouble();
    $GLOBALS['request'] = new RequestDouble();
    $_SESSION = ['_token' => 'manager-token'];
    $base = ['exec_module', 'soffers'];
    $cms->permissions = $base;
    sOfferController::requirePermission('soffers');
    foreach (['soffers_create', 'soffers_save', 'soffers_delete', 'soffers_publish', 'soffers_features', 'soffers_settings'] as $permission) {
        expectAlert(function () use ($permission) { sOfferController::requirePermission($permission); }, 403);
        $cms->permissions = array_merge($base, [$permission]);
        sOfferController::requirePermission($permission);
        $cms->permissions = $base;
    }
    foreach ([[], ['soffers'], ['exec_module']] as $permissions) {
        $cms->permissions = $permissions;
        expectAlert(function () { dispatchModule('settingsSave'); }, 403);
    }
    $cms->permissions = $base;
    $cms->user = 0;
    expectAlert(function () { dispatchModule('settingsSave'); }, 403);
    $cms->user = 1;

    foreach (['offerSave', 'offerDelete', 'contentSave', 'tvsSave', 'featuresSave', 'featuresSchemaSave', 'settingsSave'] as $action) {
        $request->method = 'GET';
        expectAlert(function () use ($action) { dispatchModule($action); }, 405);
        $request->method = 'POST';
        expectAlert(function () use ($action) { dispatchModule($action, ['_token' => 'invalid']); }, 403);
    }
    foreach ([null, [], '', 'invalid'] as $token) {
        $request->data = ['_token' => $token];
        expectAlert(function () { sOfferController::requirePost(); }, 403);
    }
    $request->data = [];
    $request->headers = ['X-CSRF-TOKEN' => 'manager-token'];
    sOfferController::requirePost();
    $request->headers = [];
    foreach (['offerDelete', 'contentSave', 'tvsSave', 'featuresSave', 'featuresSchemaSave', 'settingsSave'] as $action) {
        expectAlert(function () use ($action) { dispatchModule($action); }, 403);
    }
    expectAlert(function () { dispatchModule([]); }, 400);
    expectAlert(function () { sOfferController::reject(422, 'Value <field> & "name"'); }, 422, 'Value &lt;field&gt; &amp; &quot;name&quot;');

    $cms->permissions = array_merge($base, ['soffers_settings']);
    $writes = DB::$writes;
    foreach ([['settings' => 'bad', 'parent' => '12'], ['settings' => [], 'parent' => "1'"], ['settings' => [], 'parent' => 12]] as $input) {
        expectAlert(function () use ($input) { dispatchModule('settingsSave', $input); }, 422);
    }
    check(DB::$writes === $writes && $cms->effects === 0, 'Invalid/denied settings had side effects.');

    sOffer::$stored = new sOffer();
    sOffer::$stored->exists = false;
    sOffer::$stored->published = 0;
    sOffer::$stored->published_at = null;
    $cms->permissions = array_merge($base, ['soffers_save']);
    expectAlert(function () { dispatchModule('offerSave'); }, 403);
    sOffer::$stored->exists = true;
    $cms->permissions = array_merge($base, ['soffers_create']);
    expectAlert(function () { dispatchModule('offerSave'); }, 403);
    $cms->permissions = array_merge($base, ['soffers_save']);
    expectAlert(function () { dispatchModule('offerSave', ['published' => 1]); }, 403);
    expectAlert(function () { dispatchModule('offerSave', ['published_at' => '2026-11-10 12:00:00']); }, 403);
    $cms->permissions[] = 'soffers_publish';
    try {
        dispatchModule('offerSave', ['published' => 1, 'price' => '12.50', 'alias' => 'title']);
        throw new RuntimeException('Authorized offer save did not reach its write boundary.');
    } catch (WriteBoundary $exception) { check($exception->getMessage() === 'offer', 'Wrong write boundary.'); }

    // Authorized callers still cannot send arbitrary model fields. Full persistence
    // and explicit-delete behavior are exercised with isolated SQLite in p1-regression.
    $cms->permissions = array_merge($base, ['soffers_features']);
    expectAlert(function () { dispatchModule('featuresSave', ['features' => ['updated_at' => ['changed']]]); }, 422,
        'Feature fields must match the editable field and language allowlist.');
    expectAlert(function () { dispatchModule('featuresSave', ['deleted_features' => [1]]); }, 403);

    // Display must stay read-only and must not require schema-update permission.
    $cms->permissions = $base;
    dispatchModule('features');
    check(View::$data['get'] === 'features' && $cms->effects === 0 && DB::$writes === $writes, 'Features display changed state.');
    echo 'PASS: ' . ($checks - $start) . " access/dispatch checks\n";
    ob_end_flush();
}
