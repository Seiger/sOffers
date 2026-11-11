<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register sOffers action permissions in the shared Seiger packages group.
 *
 * Existing non-admin role grants and disabled flags are preserved. Only role 1
 * receives initial grants; other roles must be configured explicitly by an admin.
 *
 * @since 1.1.0
 */
class AddSoffersPermissions extends Migration
{
    private const PERMISSIONS = [
        'soffers' => ['Access sOffers', 'permission_access'],
        'soffers_create' => ['Create sOffers offers', 'permission_create'],
        'soffers_save' => ['Edit sOffers offers, content and TV values', 'permission_save'],
        'soffers_delete' => ['Delete sOffers offers and features', 'permission_delete'],
        'soffers_publish' => ['Change sOffers publication and publication date', 'permission_publish'],
        'soffers_features' => ['Manage sOffers features', 'permission_features'],
        'soffers_settings' => ['Configure sOffers and update feature language columns', 'permission_settings'],
    ];

    /**
     * Add package definitions and admin grants without granting existing editor roles.
     *
     * Fail before writing when the CMS permission schema is missing, rather than
     * marking a skipped migration as complete. No permission tables are created here.
     *
     * @return void
     * @since 1.1.0
     */
    public function up(): void
    {
        foreach (['permissions_groups', 'permissions', 'role_permissions'] as $table) {
            if (!Schema::hasTable($table)) {
                throw new RuntimeException('sOffers requires the CMS permission tables before migrating.');
            }
        }

        $group = DB::table('permissions_groups')->where('lang_key', 'seiger_packages')->first();
        if (!$group) {
            $group = DB::table('permissions_groups')->where('name', 'Seiger packages')->first();
        }
        if ($group) {
            $groupId = $group->id;
            DB::table('permissions_groups')->where('id', $groupId)->update([
                'lang_key' => 'seiger_packages', 'updated_at' => now(),
            ]);
        } else {
            $groupId = DB::table('permissions_groups')->insertGetId([
                'name' => 'Seiger packages', 'lang_key' => 'seiger_packages',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::PERMISSIONS as $key => $definition) {
            $attributes = [
                'name' => $definition[0], 'lang_key' => 'sOffers::global.' . $definition[1],
                'group_id' => $groupId, 'updated_at' => now(),
            ];
            if (DB::table('permissions')->where('key', $key)->exists()) {
                DB::table('permissions')->where('key', $key)->update($attributes);
            } else {
                DB::table('permissions')->insert($attributes + [
                    'key' => $key, 'disabled' => 0, 'created_at' => now(),
                ]);
            }
            if (!DB::table('role_permissions')->where('role_id', 1)->where('permission', $key)->exists()) {
                DB::table('role_permissions')->insert([
                    'role_id' => 1, 'permission' => $key,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Remove only sOffers definitions and their grants, keeping the shared group.
     *
     * The group may belong to sTask or another Seiger package; rollback must not
     * delete it or any unrelated role permissions.
     *
     * @return void
     * @since 1.1.0
     */
    public function down(): void
    {
        $keys = array_keys(self::PERMISSIONS);
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission', $keys)->delete();
        }
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('key', $keys)->delete();
        }
    }
}
