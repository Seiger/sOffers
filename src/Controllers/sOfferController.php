<?php namespace Seiger\sOffers\Controllers;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Seiger\sLang\Facades\sLang;
use Seiger\sOffers\Models\sOFeature;
use Seiger\sOffers\Models\sOffer;

/**
 * Render the manager module and manage features, translations and settings.
 *
 * Feature actions check manager permissions and settings writes coordinate
 * the config file with the resource setting.
 */
class sOfferController
{
    public $url;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->url = $this->moduleUrl();
        //Paginator::defaultView('pagination');
    }

    /**
     * Send authorized managers to the registered file module.
     *
     * The module processor applies the CMS access checks and prepares the
     * tabs and other view data for the manager page.
     *
     * @return RedirectResponse
     */
    public function index(): RedirectResponse
    {
        self::requirePermission('soffers');
        return new RedirectResponse(EVO_MANAGER_URL . $this->url);
    }

    /**
     * Require an authenticated manager, module execution, package access and an action permission.
     *
     * Use the explicit manager context so a frontend permission set cannot authorize
     * a manager write. Call this before queries that mutate data, files or caches.
     *
     * @param string $permission Existing CMS action permission.
     * @return void
     * @since 1.1.0
     */
    public static function requirePermission(string $permission): void
    {
        if (!evo()->getLoginUserID('mgr') || !evo()->hasPermission('exec_module', 'mgr')
            || !evo()->hasPermission('soffers', 'mgr')
            || !evo()->hasPermission($permission, 'mgr')
        ) {
            self::reject(403, 'You do not have permission to perform this offer action.');
        }
    }

    /**
     * Require POST and the CMS manager-session CSRF token for a mutating branch.
     *
     * Older hosts may not fail closed in their middleware. The token uses the same
     * session key and form/header contract as the CMS.
     *
     * @return void
     * @since 1.1.0
     */
    public static function requirePost(): void
    {
        if (!request()->isMethod('post')) {
            self::reject(405, 'Offer changes must be submitted using POST.');
        }
        $expected = $_SESSION['_token'] ?? null;
        $submitted = request()->input('_token');
        if (!is_string($submitted) || $submitted === '') {
            $submitted = request()->header('X-CSRF-TOKEN');
        }
        if (!is_string($expected) || $expected === '' || !is_string($submitted)
            || $submitted === '' || !hash_equals($expected, $submitted)
        ) {
            self::reject(403, 'CSRF token mismatch.');
        }
    }

    /**
     * Display a plain-text error through the CMS manager alert and end the request.
     *
     * File modules are evaluated inside the manager processor. The CMS alert
     * handles its output buffer and inserts the message into HTML.
     *
     * @param int $status HTTP status for the rejected request.
     * @param string $message Plain-text message for the manager.
     * @return never
     * @since 1.1.0
     */
    public static function reject(int $status, string $message): void
    {
        http_response_code($status);
        evo()->webAlertAndQuit(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        exit;
    }

    /**
     * Return the validated default used to mirror feature text into base.
     *
     * @return string
     */
    public function langDefault(): string
    {
        return $this->featureLanguages()['default'];
    }

    /**
     * Return configured feature languages validated against sLang, or base.
     *
     * @return array
     */
    public function langList(): array
    {
        return $this->featureLanguages()['codes'];
    }

    /**
     * Add missing feature translation columns through the explicit manager schema action.
     *
     * Requires sOffers settings permission and a token-protected POST even for direct callers.
     * Opening the features tab never invokes this DDL helper. Language identifiers
     * are validated against sLang and quoted by the schema builder.
     *
     * @return void
     */
    public function setModifyTables(): void
    {
        self::requirePermission('soffers_settings');
        self::requirePost();
        $codes = array_diff($this->featureLanguages()['codes'], ['base']);
        if ($codes === []) {
            return;
        }

        $columns = Schema::getColumnListing('s_o_features');
        $missing = array_values(array_diff($codes, $columns));
        if ($missing !== []) {
            Schema::table('s_o_features', function (Blueprint $table) use ($missing) {
                foreach ($missing as $code) {
                    $table->string($code, 255)->default('');
                }
            });
        }
    }

    /**
     * Apply a fully validated edit/deletion plan, preserving omitted records.
     *
     * Authorization, language-column readiness, IDs and final alias collisions are
     * checked before the first write. Transaction rollback includes pivot cleanup.
     *
     * @param mixed $input Raw feature form arrays.
     * @param mixed $deleted Explicit deletion IDs; omission never means delete-all.
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException When a model event cancels a save or deletion.
     * @since 1.1.0
     */
    public function saveFeatures($input, $deleted): void
    {
        self::requirePermission('soffers_features');
        self::requirePost();
        $languages = $this->featureLanguages();
        $plan = $this->normalizeFeatures($input, $deleted, $languages);
        if ($plan['deleted'] !== []) {
            self::requirePermission('soffers_delete');
        }
        if ($plan['rows'] === [] && $plan['deleted'] === []) {
            return;
        }
        if ($plan['rows'] !== [] && array_diff($languages['codes'], Schema::getColumnListing('s_o_features'))) {
            throw new InvalidArgumentException('Update feature language columns before saving translations.');
        }

        DB::transaction(function () use ($plan) {
            $existing = sOFeature::query()->lockForUpdate()->get()->keyBy('fid');
            $savedIds = array_column($plan['rows'], 'id');
            foreach (array_merge($savedIds, $plan['deleted']) as $id) {
                if ($id > 0 && !isset($existing[$id])) {
                    throw new InvalidArgumentException('A selected feature no longer exists.');
                }
            }
            $aliases = [];
            foreach ($existing as $id => $feature) {
                if (!in_array($id, $savedIds, true) && !in_array($id, $plan['deleted'], true)) {
                    $aliases[$feature->alias] = true;
                }
            }
            foreach ($plan['rows'] as $row) {
                if (isset($aliases[$row['values']['alias']])) {
                    throw new InvalidArgumentException('A feature alias is already in use.');
                }
                $aliases[$row['values']['alias']] = true;
            }

            foreach ($plan['rows'] as $row) {
                $feature = $row['id'] > 0 ? $existing[$row['id']] : new sOFeature();
                foreach ($row['values'] as $field => $value) {
                    $feature->{$field} = $value;
                }
                if ($feature->save() !== true) {
                    throw new RuntimeException('A feature save was cancelled.');
                }
            }
            if ($plan['deleted'] !== []) {
                DB::table('s_offer_features')->whereIn('feature', $plan['deleted'])->delete();
                foreach ($plan['deleted'] as $id) {
                    if ($existing[$id]->delete() !== true) {
                        throw new RuntimeException('A feature deletion was cancelled.');
                    }
                }
            }
        });
    }

    /**
     * Resolve the same validated language settings for forms, writes and DDL.
     *
     * When sLang is available, configured codes must belong to its catalog
     * and be suitable column identifiers. The default must be in the list.
     * Without sLang, only the base field is active.
     *
     * @return array{codes: list<string>, default: string}
     * @throws InvalidArgumentException
     * @since 1.1.0
     */
    private function featureLanguages(): array
    {
        if (!class_exists(sLang::class) || !evo()->bound('sLang')) {
            return ['codes' => ['base'], 'default' => 'base'];
        }

        $configured = evo()->getConfig('s_lang_config', '');
        $default = evo()->getConfig('s_lang_default', 'base');
        if (!is_string($configured) || !is_string($default)) {
            throw new InvalidArgumentException('Feature languages must be configured as strings.');
        }

        $codes = trim($configured) === '' ? ['base'] : array_map(function ($code) {
            return strtolower(trim($code));
        }, explode(',', $configured));
        $default = strtolower(trim($default));
        $catalog = array_change_key_case(sLang::langList(), CASE_LOWER);
        foreach ($codes as $code) {
            if ($code === 'base') {
                continue;
            }
            if (!array_key_exists($code, $catalog) || $code === 'fid' || strlen($code) > 63
                || !preg_match('/\A[a-z]{2,3}(?:-[a-z0-9]{1,8})*\z/', $code)
            ) {
                throw new InvalidArgumentException('The feature language list contains an unsupported code.');
            }
        }
        if (count($codes) !== count(array_unique($codes)) || !in_array($default, $codes, true)) {
            throw new InvalidArgumentException('Feature languages must be unique and include the default language.');
        }

        return ['codes' => $codes, 'default' => $default];
    }

    /**
     * Validate form arrays into a field-limited edit plan without persistence.
     *
     * IDs are selectors only. Position and base are computed on the server; the
     * client may supply only editable fields and configured language columns.
     *
     * @param mixed $input Parallel arrays of feature rows; an empty array is a no-op.
     * @param mixed $deleted Explicit list of positive feature IDs to delete.
     * @param array{codes: list<string>, default: string} $languages Validated language contract.
     * @return array{rows: list<array{id: int, values: array<string, string|int>}>, deleted: list<int>}
     * @throws InvalidArgumentException
     * @since 1.1.0
     */
    private function normalizeFeatures($input, $deleted, array $languages): array
    {
        if (!is_array($input) || !is_array($deleted) || count($deleted) > 250) {
            throw new InvalidArgumentException('Features and deletion IDs must be arrays of at most 250 rows.');
        }
        $deletedIds = [];
        foreach ($deleted as $id) {
            $id = $this->featureId($id);
            if ($id === 0 || in_array($id, $deletedIds, true)) {
                throw new InvalidArgumentException('Deletion IDs must be positive and unique.');
            }
            $deletedIds[] = $id;
        }
        if ($input === []) {
            return ['rows' => [], 'deleted' => $deletedIds];
        }

        $limits = array_merge(['alias' => 255, 'badge' => 255, 'color' => 100], array_fill_keys($languages['codes'], 255));
        $fields = array_merge(['fid'], array_keys($limits));
        if (array_diff(array_keys($input), $fields) || array_diff($fields, array_keys($input))) {
            throw new InvalidArgumentException('Feature fields must match the editable field and language allowlist.');
        }
        $indexes = null;
        foreach ($fields as $field) {
            if (!is_array($input[$field])) {
                throw new InvalidArgumentException('Each feature field must be an array.');
            }
            $keys = array_keys($input[$field]);
            if ($indexes !== null && $keys !== $indexes) {
                throw new InvalidArgumentException('Feature field indexes must match.');
            }
            $indexes = $keys;
        }
        if (count($indexes) > 250) {
            throw new InvalidArgumentException('A feature save cannot exceed 250 rows.');
        }

        $rows = [];
        $ids = [];
        $aliases = [];
        foreach ($indexes as $index) {
            $id = $this->featureId($input['fid'][$index]);
            if (($id > 0 && in_array($id, $ids, true)) || in_array($id, $deletedIds, true)) {
                throw new InvalidArgumentException('A feature ID cannot occur twice or be edited and deleted together.');
            }
            $ids[] = $id;
            $values = [];
            foreach ($limits as $field => $limit) {
                $value = $input[$field][$index];
                if (!is_string($value) || preg_match('//u', $value) !== 1 || Str::length($value) > $limit) {
                    throw new InvalidArgumentException('A feature value has an invalid type, encoding or length.');
                }
                $values[$field] = $value;
            }
            $values['base'] = $values[$languages['default']];
            if (trim($values['base']) === '') {
                throw new InvalidArgumentException('A feature requires text in the default language.');
            }
            $alias = Str::slug(trim($values['alias']) !== '' ? $values['alias'] : $values['base']);
            if ($alias === '' || strlen($alias) > 255 || isset($aliases[$alias])) {
                throw new InvalidArgumentException('Feature aliases must be nonempty and unique after normalization.');
            }
            $aliases[$alias] = true;
            $values['alias'] = $alias;
            $values['position'] = count($rows);
            $rows[] = ['id' => $id, 'values' => $values];
        }

        return ['rows' => $rows, 'deleted' => $deletedIds];
    }

    /**
     * Parse an ID selector without accepting floats, arrays or numeric prefixes.
     *
     * @param mixed $value Empty string or zero for a new row; otherwise a positive integer.
     * @return int
     * @throws InvalidArgumentException
     * @since 1.1.0
     */
    private function featureId($value): int
    {
        return $value === '' ? 0 : $this->parseResourceId($value);
    }

    /**
     * Normalize the three parallel arrays submitted by the settings form.
     *
     * Reject malformed rows and duplicate normalized keys before any write. Names
     * remain plain text until serialization.
     *
     * @param mixed $input Raw settings payload; an empty array clears all definitions.
     * @return array<string, array{key: string, name: string, type: string}>
     * @throws InvalidArgumentException
     * @since 1.1.0
     */
    public function normalizeSettings($input): array
    {
        if (!is_array($input)) {
            throw new InvalidArgumentException('Settings must be an array.');
        }
        if ($input === []) {
            return [];
        }
        if (array_diff(array_keys($input), ['key', 'name', 'type'])
            || !isset($input['key'], $input['name'], $input['type'])
            || !is_array($input['key']) || !is_array($input['name']) || !is_array($input['type'])
        ) {
            throw new InvalidArgumentException('Settings require key, name and type arrays.');
        }

        $indexes = array_keys($input['key']);
        if ($indexes !== array_keys($input['name']) || $indexes !== array_keys($input['type'])
            || count($indexes) > 250
        ) {
            throw new InvalidArgumentException('Settings rows must match and cannot exceed 250.');
        }

        $settings = [];
        foreach ($input['key'] as $index => $key) {
            $name = $input['name'][$index];
            $type = $input['type'][$index];
            if (!is_string($key) || !is_string($name) || !is_string($type)
                || strlen($key) > 1024 || preg_match('//u', $key) !== 1
                || preg_match('//u', $name) !== 1 || Str::length($name) > 255
                || !in_array($type, ['Text', 'Textarea', 'RichText'], true)
            ) {
                throw new InvalidArgumentException('A setting has an invalid key, name or type.');
            }

            $key = Str::lower(Str::slug($key));
            if (strlen($key) > 255 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $key)
                || isset($settings[$key])
            ) {
                throw new InvalidArgumentException('Setting keys must be nonempty and unique after normalization.');
            }
            $settings[$key] = ['key' => $key, 'name' => $name, 'type' => $type];
        }

        return $settings;
    }

    /**
     * Parse the optional CMS resource identifier without coercing other input types.
     *
     * Zero keeps the existing site-root convention. Positive IDs still require an
     * existence check by the caller before persisting either settings destination.
     *
     * @param mixed $input Submitted parent value.
     * @return int
     * @throws InvalidArgumentException
     * @since 1.1.0
     */
    public function parseResourceId($input): int
    {
        if ((!is_int($input) && !is_string($input))
            || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$input)
        ) {
            throw new InvalidArgumentException('The resource must be a nonnegative integer.');
        }
        $resource = filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($resource === false) {
            throw new InvalidArgumentException('The resource ID is out of range.');
        }

        return $resource;
    }

    /**
     * Save validated definitions by atomic rename, using bindings for the resource.
     *
     * The destination is supplied by trusted module code, never by the request.
     * Readers keep seeing a complete PHP array. The optional system setting is
     * written inside a database transaction. Rename or commit failures restore the
     * previous file. Cache updates belong to the caller after successful return.
     *
     * @param string $path Fixed CMS custom-config destination.
     * @param array<string, array{key: string, name: string, type: string}> $settings Normalized rows.
     * @param int|null $resource Validated resource ID, or null to leave it unchanged.
     * @return void
     * @throws RuntimeException When locking, writing or restoring the config fails.
     * @since 1.1.0
     */
    public function saveSettings(string $path, array $settings, ?int $resource): void
    {
        if (is_link($path) || is_link($path . '.lock')) {
            throw new RuntimeException('The settings file and lock must not be symbolic links.');
        }
        $lock = @fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open the settings lock.');
        }

        $temporary = null;
        $backup = null;
        $replaced = false;
        $keepBackup = false;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock the settings file.');
            }
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new RuntimeException('The settings file must not be a symbolic link.');
            }
            $exists = file_exists($path);
            $permissions = $exists ? @fileperms($path) : 0644;
            if ($permissions === false) {
                throw new RuntimeException('Cannot read settings permissions.');
            }
            if ($exists) {
                $previous = @file_get_contents($path);
                if ($previous === false) {
                    throw new RuntimeException('Cannot read the previous settings file.');
                }
                $backup = $this->temporarySettingsFile($path, $previous, $permissions & 0777);
            }
            $temporary = $this->temporarySettingsFile(
                $path,
                '<?php return ' . var_export($settings, true) . ";\n",
                $permissions & 0777
            );

            $write = function () use ($path, $temporary, $resource, &$replaced) {
                if ($resource !== null) {
                    DB::table('system_settings')->updateOrInsert(
                        ['setting_name' => 's_offers_resource'],
                        ['setting_value' => (string)$resource]
                    );
                }
                if (!@rename($temporary, $path)) {
                    throw new RuntimeException('Cannot replace the settings file.');
                }
                $replaced = true;
            };

            try {
                if ($resource === null) {
                    $write();
                } else {
                    DB::transaction($write);
                }
            } catch (Throwable $exception) {
                if ($replaced) {
                    $restored = $backup !== null ? @rename($backup, $path) : @unlink($path);
                    if (!$restored) {
                        $keepBackup = true;
                        throw new RuntimeException('Settings save failed and the previous config could not be restored.', 0, $exception);
                    }
                }
                throw $exception;
            }
        } finally {
            clearstatcache(true, $path);
            if ($replaced && function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }
            if (!$keepBackup && $backup !== null && is_file($backup)) {
                @unlink($backup);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Prepare a complete replacement in the destination directory with its permissions.
     *
     * Temporary files in the destination directory allow an atomic rename on
     * supported filesystems. Partial writes are removed.
     *
     * @param string $path Trusted target path, used only to select its directory.
     * @param string $contents Complete serialized config or rollback copy.
     * @param int $permissions Existing config permission bits, or the default for a new file.
     * @return string Prepared temporary path.
     * @throws RuntimeException
     * @since 1.1.0
     */
    private function temporarySettingsFile(string $path, string $contents, int $permissions): string
    {
        $directory = realpath(dirname($path));
        if ($directory === false) {
            throw new RuntimeException('The settings directory does not exist.');
        }
        $temporary = @tempnam($directory, '.soffer-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create a temporary settings file.');
        }
        try {
            if (realpath(dirname($temporary)) !== $directory
                || @file_put_contents($temporary, $contents) !== strlen($contents)
                || !@chmod($temporary, $permissions)
            ) {
                throw new RuntimeException('Cannot prepare the settings file.');
            }
        } catch (Throwable $exception) {
            @unlink($temporary);
            throw $exception;
        }

        return $temporary;
    }

    /**
     * Generate offer list aliases
     *
     * @return void
     */
    public function setOfferListing(): void
    {
        $offerListing = [];
        $offers = sOffer::select('id', 'alias', 'parent')->wherePublished(1)->get();

        if ($offers) {
            foreach ($offers as $offer) {
                $parent = '';
                if ((int)$offer->parent > 0) {
                    $parent = UrlProcessor::makeUrl($offer->parent);
                    $parent = ltrim($parent, '/');
                }
                $offerListing[$parent.$offer->alias] = $offer->id;
            }
        }

        Cache::forever('offerListing', $offerListing);
    }

    /**
     * Connecting the visual editor to the required fields
     *
     * @param string $ids List of id fields separated by commas
     * @param string $height Window height
     * @param string $editor Which editor to use TinyMCE5, Codemirror
     * @return string
     */
    public function textEditor(string $ids, string $height = '500px', string $editor = ''): string
    {
        if (!trim($editor)) {
            $editor = evo()->getConfig('which_editor', 'TinyMCE5');
        }
        $elements = [];
        $ids = explode(",", $ids);

        foreach ($ids as $id) {
            $elements[] = trim($id);
        }

        return implode("", evo()->invokeEvent('OnRichTextEditorInit', [
            'editor' => $editor,
            'elements' => $elements,
            'height' => $height,
            'contentType' => 'htmlmixed'
        ]));
    }

    /**
     * Build the registered file-module URL with the CMS token required by action 112.
     *
     * @return string
     */
    protected function moduleUrl(): string
    {
        return 'index.php?a=112&id=' . md5(__('sOffers::global.offers')) . '&_token=' . rawurlencode(csrf_token());
    }

    /**
     * Price validation
     *
     * @param mixed $price
     * @return float
     */
    public function validatePrice(mixed $price): float
    {
        $validPrice = 0.00;
        $price = str_replace(',', '.', $price);

        if (is_int($price) || is_numeric($price)) {
            $price = floatval($price);
            $validPrice = floatval(number_format($price, 2, '.', ''));
        } elseif (is_float($price)) {
            $validPrice = floatval(number_format($price, 2, '.', ''));
        }

        return $validPrice;
    }

    /**
     * Alias validation
     *
     * @param $data
     * @param string $table
     * @return string
     */
    public function validateAlias($string = '', $id = 0, $key = 'offer'): string
    {
        if (trim($string)) {
            $alias = Str::slug(trim($string), '-');
        } else {
            $alias = $id;
        }

        switch ($key) {
            default :
                $aliases = sOffer::where('s_offers.id', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
            case "feature" :
                $aliases = sOFeature::where('s_o_features.fid', '<>', $id)->get('alias')->pluck('alias')->toArray();
                break;
        }

        if (in_array($alias, $aliases)) {
            $cnt = 1;
            $tempAlias = $alias;
            while (in_array($tempAlias, $aliases)) {
                $tempAlias = $alias . $cnt;
                $cnt++;
            }
            $alias = $tempAlias;
        }
        return $alias;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'title.required' => 'A title is required',
            'body.required' => 'A message is required',
        ];
    }

    /**
     * Display render
     *
     * @param string $tpl
     * @param array $data
     * @return bool
     */
    public function view(string $tpl, array $data = [])
    {
        return \View::make('sOffers::'.$tpl, $data);
    }
}
