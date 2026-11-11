<?php
/**
 * Standalone P0 regression checks with CMS/DB doubles and a disposable directory.
 *
 * Run with `php tests/p0-regression.php`; no CMS bootstrap, HTTP, real database,
 * Composer autoload or deployed config is used. Str is a minimal test double:
 * these checks cover settings policy, not Illuminate transliteration behavior.
 */

namespace Illuminate\Support {
    /** Small ASCII slug/Unicode length double; transliteration is not under test. @since 1.1.0 */
    class Str
    {
        /** Normalize fixture keys without loading Illuminate. @since 1.1.0 */
        public static function slug($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-')); }
        /** Lowercase ASCII fixture keys. @since 1.1.0 */
        public static function lower($value) { return strtolower($value); }
        /** Count Unicode characters for the name limit. @since 1.1.0 */
        public static function length($value) { return preg_match_all('/./us', $value); }
    }
}

namespace Illuminate\Support\Facades {
    /** Record bound builder values and simulate transaction/commit failures. @since 1.1.0 */
    class DB
    {
        public static $writes = [];
        public static $failQuery = false;
        public static $failCommit = false;
        public static $transactions = 0;

        /** Reject any table outside the settings scenario. @since 1.1.0 */
        public static function table($name)
        {
            if ($name !== 'system_settings') { throw new \RuntimeException('Unexpected table.'); }
            return new self();
        }

        /** Keep keys and values separate, as the real builder contract requires. @since 1.1.0 */
        public function updateOrInsert($keys, $values)
        {
            if (self::$failQuery) { throw new \RuntimeException('Simulated database failure.'); }
            self::$writes[] = [$keys, $values];
            return true;
        }

        /** Roll back the in-memory write log on a simulated transaction failure. @since 1.1.0 */
        public static function transaction($callback)
        {
            self::$transactions++;
            $previous = self::$writes;
            try {
                $callback();
                if (self::$failCommit) { throw new \RuntimeException('Simulated commit failure.'); }
            } catch (\Throwable $exception) {
                self::$writes = $previous;
                throw $exception;
            }
        }
    }
}

namespace Seiger\sOffers\Controllers {
    // Fault injection for real file operations, confined to the test namespace.
    function rename($from, $to)
    {
        if (!empty($GLOBALS['failRestore']) && ++$GLOBALS['renameCalls'] > 1) { return false; }
        if (!empty($GLOBALS['failRename'])) { return false; }
        return \rename($from, $to);
    }

    function file_put_contents($path, $contents)
    {
        if (!empty($GLOBALS['failWrite'])) { return false; }
        return \file_put_contents($path, $contents);
    }

    function chmod($path, $permissions)
    {
        if (!empty($GLOBALS['failChmod'])) { return false; }
        return \chmod($path, $permissions);
    }

    function flock($file, $operation)
    {
        if ($operation === LOCK_EX && !empty($GLOBALS['failLock'])) { return false; }
        return \flock($file, $operation);
    }
}

namespace {
    use Illuminate\Support\Facades\DB;
    use Seiger\sOffers\Controllers\sOfferController;

    require dirname(__DIR__) . '/src/Controllers/sOfferController.php';
    $controller = (new ReflectionClass(sOfferController::class))->newInstanceWithoutConstructor();

    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'soffers-p0-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700)) { throw new RuntimeException('Cannot create test directory.'); }
    $path = $directory . DIRECTORY_SEPARATOR . 'sOffer.php';
    $checks = 0;

    function check($condition, $message)
    {
        $GLOBALS['checks']++;
        if (!$condition) { throw new RuntimeException($message); }
    }

    function rejects($callback, $class = InvalidArgumentException::class)
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            check($exception instanceof $class, 'Unexpected exception: ' . get_class($exception));
            return;
        }
        throw new RuntimeException('Expected rejection.');
    }

    function payload($name = 'Title')
    {
        return ['key' => ['Title'], 'name' => [$name], 'type' => ['Text']];
    }

    try {
        $name = "Назва O'Brien \\ текст\nдругий рядок";
        $settings = $controller->normalizeSettings(payload($name));
        check($settings === ['title' => ['key' => 'title', 'name' => $name, 'type' => 'Text']], 'Normalized settings differ.');
        check($controller->normalizeSettings([]) === [], 'Empty config is not supported.');
        foreach (['Text', 'Textarea', 'RichText'] as $type) {
            $input = payload();
            $input['type'][0] = $type;
            check($controller->normalizeSettings($input)['title']['type'] === $type, 'Supported field type rejected.');
        }

        $invalid = [null, 'text', ['key' => []], ['key' => 'title', 'name' => [], 'type' => []]];
        $input = payload(); $input['name'] = []; $invalid[] = $input;
        $input = payload(); $input['type'] = [1 => 'Text']; $invalid[] = $input;
        $input = payload(); $input['key'][0] = []; $invalid[] = $input;
        $input = payload(); $input['name'][0] = []; $invalid[] = $input;
        $input = payload(); $input['type'][0] = 'Unknown'; $invalid[] = $input;
        $input = payload(); $input['key'][0] = ''; $invalid[] = $input;
        $input = payload(); $input['key'][0] = str_repeat('a', 256); $invalid[] = $input;
        $input = payload(); $input['name'][0] = str_repeat('а', 256); $invalid[] = $input;
        $input = payload(); $input['extra'] = []; $invalid[] = $input;
        $invalid[] = ['key' => ['Title', 'title'], 'name' => ['One', 'Two'], 'type' => ['Text', 'Text']];
        $invalid[] = ['key' => array_fill(0, 251, 'key'), 'name' => array_fill(0, 251, 'Name'), 'type' => array_fill(0, 251, 'Text')];
        foreach ($invalid as $input) { rejects(function () use ($controller, $input) { $controller->normalizeSettings($input); }); }
        check(!file_exists($path) && DB::$writes === [], 'Validation has side effects.');

        foreach ([0, '0', 12, '12'] as $input) {
            check($controller->parseResourceId($input) === (int)$input, 'Valid resource ID rejected.');
        }
        foreach ([null, false, true, 1.5, [], -1, '-1', '01', '1x', "1'", '1e2', ' 1', str_repeat('9', 30)] as $input) {
            rejects(function () use ($controller, $input) { $controller->parseResourceId($input); });
        }

        $controller->saveSettings($path, $settings, 12);
        // Only require a config generated here from harmless text, never user/deployed PHP.
        check((require $path) === $settings, 'Serialized text did not round-trip.');
        check(DB::$writes === [[['setting_name' => 's_offers_resource'], ['setting_value' => '12']]], 'Resource value is not passed separately to the builder.');
        check(DB::$transactions === 1, 'Resource/config save is not transactional.');
        check(glob($directory . DIRECTORY_SEPARATOR . '.soffer-*') === [], 'Temporary files leaked after success.');

        $previous = file_get_contents($path);
        $writes = DB::$writes;
        DB::$failQuery = true;
        rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
        DB::$failQuery = false;
        check(file_get_contents($path) === $previous && DB::$writes === $writes, 'Query failure changed persisted settings.');

        DB::$failCommit = true;
        rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
        DB::$failCommit = false;
        check(file_get_contents($path) === $previous && DB::$writes === $writes, 'Commit failure did not restore the config.');

        $GLOBALS['failRename'] = true;
        rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
        $GLOBALS['failRename'] = false;
        check(file_get_contents($path) === $previous && DB::$writes === $writes, 'Rename failure changed persisted settings.');

        $GLOBALS['failLock'] = true;
        rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
        $GLOBALS['failLock'] = false;
        check(file_get_contents($path) === $previous && DB::$writes === $writes, 'Lock failure changed persisted settings.');
        check(glob($directory . DIRECTORY_SEPARATOR . '.soffer-*') === [], 'Temporary files leaked after failure.');

        foreach (['failWrite', 'failChmod'] as $flag) {
            $GLOBALS[$flag] = true;
            rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
            $GLOBALS[$flag] = false;
            check(file_get_contents($path) === $previous && DB::$writes === $writes, 'Temporary-file failure changed persisted settings.');
            check(glob($directory . DIRECTORY_SEPARATOR . '.soffer-*') === [], 'Failed preparation leaked a temporary file.');
        }

        $GLOBALS['failRestore'] = true;
        $GLOBALS['renameCalls'] = 0;
        DB::$failCommit = true;
        rejects(function () use ($controller, $path) { $controller->saveSettings($path, [], 13); }, RuntimeException::class);
        $GLOBALS['failRestore'] = false;
        DB::$failCommit = false;
        $recovery = glob($directory . DIRECTORY_SEPARATOR . '.soffer-*');
        check(count($recovery) === 1 && file_get_contents($recovery[0]) === $previous, 'Failed restoration discarded the recovery copy.');
        check(DB::$writes === $writes, 'Failed restoration did not roll back the database double.');
        rename($recovery[0], $path);

        $controller->saveSettings($path, [], null);
        check((require $path) === [], 'Clearing definitions failed.');
        check(DB::$writes === $writes, 'Omitted parent changed the database.');
        unlink($path);
        DB::$failCommit = true;
        rejects(function () use ($controller, $path, $settings) { $controller->saveSettings($path, $settings, 13); }, RuntimeException::class);
        DB::$failCommit = false;
        check(!file_exists($path), 'Commit failure left a newly created config.');
        check(glob($directory . DIRECTORY_SEPARATOR . '.soffer-*') === [], 'Rollback leaked temporary files.');

        echo 'PASS: ' . $checks . " settings checks\n";
    } finally {
        // Only files under the random directory created above are removed.
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') as $file) { if (is_file($file)) { unlink($file); } }
        foreach (glob($directory . DIRECTORY_SEPARATOR . '.soffer-*') as $file) { if (is_file($file)) { unlink($file); } }
        rmdir($directory);
    }
}
