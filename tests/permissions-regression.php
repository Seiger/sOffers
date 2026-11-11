<?php
/**
 * Test permission registration against arrays, never a CMS database or migration runner.
 *
 * Run with `php tests/permissions-regression.php`; up/down operate only on the
 * in-memory builder below to verify coexistence with sTask, idempotence and rollback.
 */

namespace Illuminate\Database\Migrations {
    /** Stand-in for the migration base, with no connection or runner. @since 1.1.0 */
    class Migration {}
}

namespace Illuminate\Support\Facades {
    /** Permission-table fixtures shared by the in-memory query builder. @since 1.1.0 */
    class DB
    {
        public static $tables = [];
        /** Construct a builder limited to the named fixture table. @since 1.1.0 */
        public static function table($name) { return new \PermissionQueryDouble($name); }
    }
    /** Schema availability controlled entirely by the fixture set. @since 1.1.0 */
    class Schema
    {
        /** Report only in-memory tables, without schema introspection. @since 1.1.0 */
        public static function hasTable($name) { return array_key_exists($name, DB::$tables); }
    }
}

namespace {
    use Illuminate\Support\Facades\DB;

    /** Minimal equality/in-list builder for the permission migration's data operations. @since 1.1.0 */
    class PermissionQueryDouble
    {
        private $table;
        private $filters = [];
        /** Bind only a fixture table; no connection can be established. @since 1.1.0 */
        public function __construct($table) { $this->table = $table; }
        /** Add an equality predicate to the in-memory query. @since 1.1.0 */
        public function where($key, $value) { $this->filters[] = [$key, [$value]]; return $this; }
        /** Add an allowlist predicate for rollback checks. @since 1.1.0 */
        public function whereIn($key, $values) { $this->filters[] = [$key, $values]; return $this; }
        /** Match fixture rows without applying transformations. @since 1.1.0 */
        private function matches($row)
        {
            foreach ($this->filters as $filter) {
                if (!in_array($row[$filter[0]] ?? null, $filter[1], true)) { return false; }
            }
            return true;
        }
        /** Read the first matching fixture row as a database-style object. @since 1.1.0 */
        public function first()
        {
            foreach (DB::$tables[$this->table] as $row) { if ($this->matches($row)) { return (object)$row; } }
            return null;
        }
        /** Check existence without modifying fixture data. @since 1.1.0 */
        public function exists() { return $this->first() !== null; }
        /** Update only matching rows, preserving unspecified fields. @since 1.1.0 */
        public function update($attributes)
        {
            foreach (DB::$tables[$this->table] as &$row) { if ($this->matches($row)) { $row = array_replace($row, $attributes); } }
        }
        /** Append a fixture row with a table-local ID. @since 1.1.0 */
        public function insert($attributes) { $this->insertGetId($attributes); return true; }
        /** Return an in-memory ID without relying on an actual sequence. @since 1.1.0 */
        public function insertGetId($attributes)
        {
            $id = 1;
            foreach (DB::$tables[$this->table] as $row) { $id = max($id, ($row['id'] ?? 0) + 1); }
            DB::$tables[$this->table][] = $attributes + ['id' => $id];
            return $id;
        }
        /** Remove matching fixture rows only. @since 1.1.0 */
        public function delete()
        {
            DB::$tables[$this->table] = array_values(array_filter(DB::$tables[$this->table], function ($row) { return !$this->matches($row); }));
        }
    }

    function now() { return '2026-11-10 12:00:00'; }
    $checks = 0;
    function check($condition, $message)
    {
        $GLOBALS['checks']++;
        if (!$condition) { throw new RuntimeException($message); }
    }
    require dirname(__DIR__) . '/database/migrations/2026_11_10_000000_add_soffers_permissions.php';
    $migration = new AddSoffersPermissions();
    $stask = ['id' => 9, 'key' => 'stask', 'group_id' => 4, 'disabled' => 0];
    $grant = ['id' => 9, 'role_id' => 2, 'permission' => 'stask'];
    DB::$tables = [
        'permissions_groups' => [['id' => 4, 'name' => 'Seiger packages', 'lang_key' => 'seiger_packages']],
        'permissions' => [$stask], 'role_permissions' => [$grant],
    ];
    $migration->up();
    check(count(DB::$tables['permissions_groups']) === 1, 'Existing shared group was duplicated.');
    check(count(DB::$tables['permissions']) === 8, 'Seven sOffers permissions were not registered.');
    check(count(DB::$tables['role_permissions']) === 8, 'Admin grants differ from the permission set.');
    check(DB::$tables['permissions'][0] === $stask && DB::$tables['role_permissions'][0] === $grant, 'sTask data changed.');
    foreach (array_slice(DB::$tables['permissions'], 1) as $permission) {
        check($permission['group_id'] === 4, 'Permission is outside the shared group.');
        check(strpos($permission['lang_key'], 'sOffers::global.permission_') === 0, 'Missing package lexicon mapping.');
    }
    foreach (['en', 'uk', 'ru'] as $language) {
        $labels = require dirname(__DIR__) . '/lang/' . $language . '/global.php';
        foreach (array_slice(DB::$tables['permissions'], 1) as $permission) {
            $key = substr($permission['lang_key'], strlen('sOffers::global.'));
            check(isset($labels[$key]) && $labels[$key] !== '', 'Missing permission label for ' . $language);
        }
        check(isset($labels['permissions_group'], $labels['sync_language_fields']), 'Missing shared-group or schema-action label.');
    }
    foreach (array_slice(DB::$tables['role_permissions'], 1) as $permission) {
        check($permission['role_id'] === 1, 'A non-admin role was granted access.');
    }
    DB::$tables['permissions'][1]['disabled'] = 1;
    $before = DB::$tables;
    $migration->up();
    check(DB::$tables === $before, 'Repeated registration changed grants, disabled flags or row counts.');
    DB::$tables['role_permissions'][] = ['id' => 30, 'role_id' => 2, 'permission' => 'soffers_save'];
    $migration->down();
    check(DB::$tables['permissions'] === [$stask], 'Rollback touched unrelated definitions or left package rows.');
    check(DB::$tables['role_permissions'] === [$grant], 'Rollback left dangling package grants or removed sTask grants.');
    check(count(DB::$tables['permissions_groups']) === 1, 'Rollback removed the shared group.');
    $migration->down();
    check(DB::$tables['permissions'] === [$stask], 'Repeated rollback is not safe.');

    DB::$tables = ['permissions_groups' => [], 'permissions' => [], 'role_permissions' => []];
    $migration->up();
    check(count(DB::$tables['permissions_groups']) === 1 && count(DB::$tables['permissions']) === 7, 'Clean registration failed.');
    $migration->down();
    check(DB::$tables['permissions'] === [] && DB::$tables['role_permissions'] === [], 'Clean rollback failed.');
    DB::$tables['permissions_groups'][0]['name'] = 'Пакети Seiger';
    $migration->up();
    check(count(DB::$tables['permissions_groups']) === 1, 'Localized shared group was duplicated.');

    unset(DB::$tables['role_permissions']);
    $before = DB::$tables;
    try {
        $migration->up();
        throw new LogicException('Missing CMS permission table was ignored.');
    } catch (RuntimeException $exception) {
        check(DB::$tables === $before, 'Missing schema caused partial writes.');
    }
    echo 'PASS: ' . $checks . " permission-registration checks (in-memory only)\n";
}
