<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jarvis Tang
 * Released under the Apache-2.0 License.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PluginUsagesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('plugin_usages')->delete();

        \DB::table('plugin_usages')->insert([
            0 => [
                'id' => 1,
                'plugin_unikey' => 'all',
                'type' => 4,
                'name' => 'all',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 1,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
            1 => [
                'id' => 2,
                'plugin_unikey' => 'text',
                'type' => 4,
                'name' => 'text',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 2,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
            2 => [
                'id' => 3,
                'plugin_unikey' => 'image',
                'type' => 4,
                'name' => 'image',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 3,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
            3 => [
                'id' => 4,
                'plugin_unikey' => 'video',
                'type' => 4,
                'name' => 'video',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 4,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
            4 => [
                'id' => 5,
                'plugin_unikey' => 'audio',
                'type' => 4,
                'name' => 'audio',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 5,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
            5 => [
                'id' => 6,
                'plugin_unikey' => 'doc',
                'type' => 4,
                'name' => 'doc',
                'icon_file_id' => null,
                'icon_file_url' => null,
                'scene' => null,
                'editor_number' => null,
                'data_sources' => '{"postLists": {"sortNumber": [], "pluginUnikey": ""}, "postFollows": {"sortNumber": [], "pluginUnikey": ""}, "postNearbys": {"sortNumber": [], "pluginUnikey": ""}}',
                'is_group_admin' => 0,
                'group_id' => null,
                'member_roles' => null,
                'parameter' => null,
                'rank_num' => 6,
                'can_delete' => 1,
                'is_enable' => 1,
                'created_at' => '2021-10-08 10:00:00',
                'updated_at' => '2021-10-08 10:00:00',
                'deleted_at' => null,
            ],
        ]);
    }
}
