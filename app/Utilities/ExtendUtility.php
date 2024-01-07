<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Utilities;

use App\Helpers\CacheHelper;
use App\Helpers\ConfigHelper;
use App\Helpers\FileHelper;
use App\Helpers\PluginHelper;
use App\Helpers\StrHelper;
use App\Models\AppBadge;
use App\Models\AppUsage;
use App\Models\ArchiveUsage;
use App\Models\Extend;
use App\Models\ExtendUsage;
use App\Models\File;
use App\Models\Operation;
use App\Models\OperationUsage;
use Illuminate\Support\Arr;

class ExtendUtility
{
    // get archives
    public static function getArchives(int $type, int $id, ?string $langTag = null): ?array
    {
        $archiveQuery = ArchiveUsage::with('archive')->type($type)->where('usage_id', $id);

        $archiveQuery->whereHas('archive', function ($query) {
            $query->where('is_enabled', true)->orderBy('sort_order');
        });

        $archiveUsages = $archiveQuery->get();

        $archiveList = [];
        foreach ($archiveUsages as $usageInfo) {
            $archiveInfo = $usageInfo?->archive;

            if (empty($archiveInfo)) {
                continue;
            }

            $pluginArr = [];
            if ($archiveInfo->value_type == 'plugins' && $usageInfo->archive_value) {
                $plugins = json_encode($usageInfo->archive_value) ?? [];

                foreach ($plugins as $plugin) {
                    $item['code'] = $plugin['code'];
                    $item['fskey'] = $plugin['fskey'];
                    $item['appUrl'] = PluginHelper::fresnsPluginUrlByFskey($plugin['fskey']);

                    $pluginArr[] = $item;
                }
            }

            $archiveValue = match ($archiveInfo->value_type) {
                'file' => StrHelper::isPureInt($usageInfo->archive_value) ? FileHelper::fresnsFileUrlById($usageInfo->archive_value) : $usageInfo->archive_value,
                'plugin' => PluginHelper::fresnsPluginUrlByFskey($usageInfo->archive_value),
                'plugins' => $pluginArr,
                'number' => (int) $usageInfo->archive_value,
                'boolean' => (bool) $usageInfo->archive_value,
                'array' => (array) $usageInfo->archive_value,
                'object' => (object) $usageInfo->archive_value,
                default => $usageInfo->archive_value,
            };

            $item['code'] = $archiveInfo->code;
            $item['name'] = StrHelper::languageContent($archiveInfo->name, $langTag); // Multilingual
            $item['description'] = StrHelper::languageContent($archiveInfo->description, $langTag); // Multilingual
            $item['value'] = $archiveValue;
            $item['isPrivate'] = (bool) $usageInfo->is_private;

            $archiveList[] = $item;
        }

        return $archiveList;
    }

    // get operations
    public static function getOperations(int $type, int $id, ?string $langTag = null): ?array
    {
        $operationQuery = OperationUsage::with('operation')->type($type)->where('usage_id', $id);

        $operationQuery->whereHas('operation', function ($query) {
            $query->where('is_enabled', true);
        });

        $operations = $operationQuery->get()->map(function ($usageInfo) use ($langTag) {
            $operationInfo = $usageInfo->operation;

            $item['code'] = $operationInfo->code;
            $item['style'] = $operationInfo->style;
            $item['name'] = StrHelper::languageContent($operationInfo->name, $langTag); // Multilingual
            $item['description'] = StrHelper::languageContent($operationInfo->description, $langTag); // Multilingual
            $item['image'] = FileHelper::fresnsFileUrlByTableColumn($operationInfo->image_file_id, $operationInfo->image_file_url);
            $item['activeImage'] = FileHelper::fresnsFileUrlByTableColumn($operationInfo->image_active_file_id, $operationInfo->image_active_file_url);
            $item['displayType'] = $operationInfo->display_type;
            $item['appUrl'] = PluginHelper::fresnsPluginUsageUrl($operationInfo->app_fskey);

            return $item;
        })->groupBy('type');

        $operationList['customizes'] = $operations->get(Operation::TYPE_CUSTOMIZE)?->all() ?? [];
        $operationList['buttonIcons'] = $operations->get(Operation::TYPE_BUTTON_ICON)?->all() ?? [];
        $operationList['diversifyImages'] = $operations->get(Operation::TYPE_DIVERSIFY_IMAGE)?->all() ?? [];
        $operationList['tips'] = $operations->get(Operation::TYPE_TIP)?->all() ?? [];

        return $operationList;
    }

    // get extends
    public static function getExtends(int $type, int $id, ?string $langTag = null): ?array
    {
        $extendQuery = ExtendUsage::with('extend')->type($type)->where('usage_id', $id)->orderBy('sort_order');

        $extendQuery->whereHas('extend', function ($query) {
            $query->where('is_enabled', true);
        });

        $extends = $extendQuery->get()->map(function ($extendUsage) use ($langTag) {
            $extend = $extendUsage->extend;
            $content = $extend->content;

            $item['eid'] = $extend->eid;
            $item['type'] = $extend->view_type;
            $item['typeString'] = StrHelper::extendViewTypeString($extend->view_type);
            $item['image'] = FileHelper::fresnsFileUrlByTableColumn($extend->image_file_id, $extend->image_file_url);
            switch ($extend->type) {
                case Extend::TYPE_TEXT:
                    $item['content'] = $content['content'] ?? null;
                    $item['isMarkdown'] = $content['isMarkdown'] ?? false;
                    break;

                case Extend::TYPE_INFO:
                    $item['title'] = StrHelper::languageContent($content['title'] ?? null, $langTag);
                    $item['titleColor'] = $content['titleColor'] ?? null;
                    $item['descPrimary'] = StrHelper::languageContent($content['descPrimary'] ?? null, $langTag);
                    $item['descPrimaryColor'] = $content['descPrimaryColor'] ?? null;
                    $item['descSecondary'] = StrHelper::languageContent($content['descSecondary'] ?? null, $langTag);
                    $item['descSecondaryColor'] = $content['descSecondaryColor'] ?? null;
                    $item['buttonName'] = StrHelper::languageContent($content['buttonName'] ?? null, $langTag);
                    $item['buttonColor'] = $content['buttonColor'] ?? null;
                    break;

                case Extend::TYPE_ACTION:
                    $item['title'] = StrHelper::languageContent($content['title'] ?? null, $langTag);
                    $item['titleColor'] = $content['titleColor'] ?? null;
                    $item['endDateTime'] = $extend->ended_at;
                    $item['status'] = $extend->ended_at->isPast();
                    $item['hasOperated'] = false;
                    $item['items'] = $extend->action_items;
                    break;
            }
            $item['position'] = $extend->position;
            $item['appUrl'] = PluginHelper::fresnsPluginUsageUrl($extend->app_fskey, $extend->url_parameter);

            return $item;
        })->groupBy('type');

        $extendList['texts'] = $extends->get(Extend::TYPE_TEXT)?->all() ?? [];
        $extendList['infos'] = $extends->get(Extend::TYPE_INFO)?->all() ?? [];
        $extendList['actions'] = $extends->get(Extend::TYPE_ACTION)?->all() ?? [];

        return $extendList;
    }

    /**
     * get app usages.
     */

    // get extend cache key
    public static function getExtendCacheKey(int $type, string $typeName, ?int $scene = null, ?int $groupId = null, ?string $langTag = null): ?string
    {
        $sceneName = match ($scene) {
            AppUsage::SCENE_POST => 'post',
            AppUsage::SCENE_COMMENT => 'comment',
            AppUsage::SCENE_USER => 'user',
            default => null,
        };

        $cacheKey = match ($type) {
            AppUsage::TYPE_WALLET_RECHARGE => "fresns_wallet_recharge_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_WALLET_WITHDRAW => "fresns_wallet_withdraw_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_EDITOR => "fresns_editor_{$sceneName}_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_CONTENT => "fresns_{$sceneName}_content_types_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_MANAGE => "fresns_manage_{$sceneName}_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_GROUP => "fresns_group_{$groupId}_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_FEATURE => "fresns_feature_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_PROFILE => "fresns_profile_extends_by_{$typeName}_{$langTag}",
            AppUsage::TYPE_CHANNEL => "fresns_channel_extends_by_{$typeName}_{$langTag}",
            default => null,
        };

        return $cacheKey;
    }

    // get extend cache tags
    public static function getExtendCacheTags(int $type): array
    {
        $cacheTags = match ($type) {
            AppUsage::TYPE_WALLET_RECHARGE => ['fresnsExtensions'],
            AppUsage::TYPE_WALLET_WITHDRAW => ['fresnsExtensions'],
            AppUsage::TYPE_EDITOR => ['fresnsExtensions'],
            AppUsage::TYPE_CONTENT => ['fresnsExtensions', 'fresnsConfigs'],
            AppUsage::TYPE_MANAGE => ['fresnsExtensions'],
            AppUsage::TYPE_GROUP => ['fresnsExtensions', 'fresnsGroups'],
            AppUsage::TYPE_FEATURE => ['fresnsExtensions'],
            AppUsage::TYPE_PROFILE => ['fresnsExtensions'],
            AppUsage::TYPE_CHANNEL => ['fresnsExtensions'],
        };

        return $cacheTags;
    }

    // get app badge
    public static function getAppBadge(string $fskey, ?int $userId = null): array
    {
        $badge['badgeType'] = null;
        $badge['badgeValue'] = null;

        if (empty($userId)) {
            return $badge;
        }

        $cacheKey = "fresns_app_{$fskey}_badge_{$userId}";
        $cacheTag = 'fresnsUsers';

        // is known to be empty
        $isKnownEmpty = CacheHelper::isKnownEmpty($cacheKey);
        if ($isKnownEmpty) {
            return $badge;
        }

        $badge = CacheHelper::get($cacheKey, $cacheTag);

        if (empty($badge)) {
            $badgeModel = AppBadge::where('app_fskey', $fskey)->where('user_id', $userId)->first();

            $badge['badgeType'] = $badgeModel?->display_type;
            $badge['badgeValue'] = match ($badgeModel?->display_type) {
                AppBadge::TYPE_NUMBER => $badgeModel?->value_number,
                AppBadge::TYPE_TEXT => $badgeModel?->value_text,
                default => null,
            };

            CacheHelper::put($badge, $cacheKey, $cacheTag);
        }

        return $badge;
    }

    // get app extends by everyone
    public static function getAppExtendsByEveryone(int $type, ?int $scene = null, ?int $groupId = null, ?string $langTag = null): array
    {
        $langTag = $langTag ?: ConfigHelper::fresnsConfigDefaultLangTag();
        $cacheKey = ExtendUtility::getExtendCacheKey($type, 'everyone', $scene, $groupId, $langTag);
        $cacheTags = ExtendUtility::getExtendCacheTags($type);

        if (empty($cacheKey)) {
            return [];
        }

        // is known to be empty
        $isKnownEmpty = CacheHelper::isKnownEmpty($cacheKey);
        if ($isKnownEmpty) {
            return [];
        }

        $extendList = CacheHelper::get($cacheKey, $cacheTags);

        if (empty($extendList)) {
            $extendQuery = AppUsage::where('usage_type', $type)->where('is_group_admin', 0);

            $extendQuery->when($scene, function ($query, $value) {
                $query->where('scene', 'like', "%$value%");
            });

            $extendQuery->when($groupId, function ($query, $value) {
                $query->where('group_id', $value);
            });

            $extendArr = $extendQuery->orderBy('sort_order')->get();

            $extendList = [];
            foreach ($extendArr as $extend) {
                if ($extend->roles) {
                    continue;
                }

                $extendList[] = $extend->getUsageInfo($langTag);
            }

            $cacheTime = CacheHelper::fresnsCacheTimeByFileType(File::TYPE_IMAGE);
            CacheHelper::put($extendList, $cacheKey, $cacheTags, null, $cacheTime);
        }

        return $extendList;
    }

    // get app extends by role
    public static function getAppExtendsByRole(int $type, int $roleId, ?int $scene = null, ?int $groupId = null, ?string $langTag = null): array
    {
        $langTag = $langTag ?: ConfigHelper::fresnsConfigDefaultLangTag();
        $cacheKey = ExtendUtility::getExtendCacheKey($type, "role_{$roleId}", $scene, $groupId, $langTag);
        $cacheTags = ExtendUtility::getExtendCacheTags($type);

        if (empty($cacheKey)) {
            return [];
        }

        // is known to be empty
        $isKnownEmpty = CacheHelper::isKnownEmpty($cacheKey);
        if ($isKnownEmpty) {
            return [];
        }

        $extendList = CacheHelper::get($cacheKey, $cacheTags);

        if (empty($extendList)) {
            $extendQuery = AppUsage::where('usage_type', $type)->where('is_group_admin', 0);

            $extendQuery->when($scene, function ($query, $value) {
                $query->where('scene', 'like', "%$value%");
            });

            $extendQuery->when($groupId, function ($query, $value) {
                $query->where('group_id', $value);
            });

            $extendArr = $extendQuery->orderBy('sort_order')->get();

            $extendList = [];
            foreach ($extendArr as $extend) {
                if (empty($extend->roles)) {
                    continue;
                }

                $roleArr = explode(',', $extend->roles);

                if (! in_array($roleId, $roleArr)) {
                    continue;
                }

                $extendList[] = $extend->getUsageInfo($langTag);
            }

            $cacheTime = CacheHelper::fresnsCacheTimeByFileType(File::TYPE_IMAGE);
            CacheHelper::put($extendList, $cacheKey, $cacheTags, null, $cacheTime);
        }

        return $extendList;
    }

    // get app extends by group admin
    public static function getAppExtendsByGroupAdmin(string $type, ?int $scene = null, ?int $groupId = null, ?string $langTag = null): array
    {
        $langTag = $langTag ?: ConfigHelper::fresnsConfigDefaultLangTag();
        $cacheKey = ExtendUtility::getExtendCacheKey($type, 'group_admin', $scene, $groupId, $langTag);
        $cacheTags = ExtendUtility::getExtendCacheTags($type);

        if (empty($cacheKey)) {
            return [];
        }

        // is known to be empty
        $isKnownEmpty = CacheHelper::isKnownEmpty($cacheKey);
        if ($isKnownEmpty) {
            return [];
        }

        $extendList = CacheHelper::get($cacheKey, $cacheTags);

        if (empty($extendList)) {
            $extendQuery = AppUsage::where('usage_type', $type)->where('is_group_admin', 1);

            $extendQuery->when($scene, function ($query, $value) {
                $query->where('scene', 'like', "%$value%");
            });

            $extendQuery->when($groupId, function ($query, $value) {
                $query->where('group_id', $value);
            });

            $extendArr = $extendQuery->orderBy('sort_order')->get();

            $extendList = [];
            foreach ($extendArr as $extend) {
                $extendList[] = $extend->getUsageInfo($langTag);
            }

            $cacheTime = CacheHelper::fresnsCacheTimeByFileType(File::TYPE_IMAGE);
            CacheHelper::put($extendList, $cacheKey, $cacheTags, null, $cacheTime);
        }

        return $extendList;
    }

    /**
     * get extensions.
     */

    // get editor extensions
    public static function getEditorExtensions(string $type, int $authUserId, string $langTag): array
    {
        $scene = match ($type) {
            'post' => AppUsage::SCENE_POST,
            'comment' => AppUsage::SCENE_COMMENT,
            'posts' => AppUsage::SCENE_POST,
            'comments' => AppUsage::SCENE_COMMENT,
            default => null,
        };

        if (empty($scene)) {
            return [];
        }

        $everyoneExtends = ExtendUtility::getAppExtendsByEveryone(AppUsage::TYPE_EDITOR, $scene, null, $langTag);

        $roleExtends = [];
        $roleArr = PermissionUtility::getUserRoles($authUserId, $langTag);
        foreach ($roleArr as $role) {
            $roleExtends[] = ExtendUtility::getAppExtendsByRole(AppUsage::TYPE_EDITOR, $role['id'], $scene, null, $langTag);
        }

        $allExtends = array_merge($everyoneExtends, Arr::collapse($roleExtends));

        if (empty($allExtends)) {
            return [];
        }

        $fskeys = array_column($allExtends, 'name');
        $fskeys = array_unique($fskeys);
        $newAllExtends = array_intersect_key($allExtends, $fskeys);

        return array_values($newAllExtends);
    }

    // get manage extensions
    public static function getManageExtensions(string $type, string $langTag, ?int $authUserId = null, ?int $groupId = null): array
    {
        if (empty($authUserId)) {
            return [];
        }

        $scene = match ($type) {
            'post' => AppUsage::SCENE_POST,
            'comment' => AppUsage::SCENE_COMMENT,
            'user' => AppUsage::SCENE_USER,
            default => null,
        };

        $everyoneManages = ExtendUtility::getAppExtendsByEveryone(AppUsage::TYPE_MANAGE, $scene, null, $langTag);

        $roleManages = [];
        $roleArr = PermissionUtility::getUserRoles($authUserId, $langTag);
        foreach ($roleArr as $role) {
            $roleManages[] = ExtendUtility::getAppExtendsByRole(AppUsage::TYPE_MANAGE, $role['rid'], $scene, null, $langTag);
        }

        $groupManages = [];
        if ($groupId) {
            $checkGroupAdmin = PermissionUtility::checkUserGroupAdmin($groupId, $authUserId);
            $groupManages = $checkGroupAdmin ? ExtendUtility::getAppExtendsByGroupAdmin(AppUsage::TYPE_MANAGE, $scene, null, $langTag) : [];
        }

        $allManageExtends = array_merge($everyoneManages, Arr::collapse($roleManages), $groupManages);

        if (empty($allManageExtends)) {
            return [];
        }

        $fskeys = array_column($allManageExtends, 'name');
        $fskeys = array_unique($fskeys);
        $newManageExtends = array_intersect_key($allManageExtends, $fskeys);

        return array_values($newManageExtends);
    }

    // get user extensions
    public static function getUserExtensions(string $type, int $authUserId, string $langTag): array
    {
        $usageType = match ($type) {
            'feature' => AppUsage::TYPE_FEATURE,
            'profile' => AppUsage::TYPE_PROFILE,
            'features' => AppUsage::TYPE_FEATURE,
            'profiles' => AppUsage::TYPE_PROFILE,
            default => null,
        };

        if (empty($usageType)) {
            return [];
        }

        $everyoneExtends = ExtendUtility::getAppExtendsByEveryone($usageType, null, null, $langTag);

        $roleExtends = [];
        $roleArr = PermissionUtility::getUserRoles($authUserId, $langTag);
        foreach ($roleArr as $role) {
            $roleExtends[] = ExtendUtility::getAppExtendsByRole($usageType, $role['rid'], null, null, $langTag);
        }

        $allExtends = array_merge($everyoneExtends, Arr::collapse($roleExtends));

        if (empty($allExtends)) {
            return [];
        }

        $fskeys = array_column($allExtends, 'name');
        $fskeys = array_unique($fskeys);
        $newAllExtends = array_intersect_key($allExtends, $fskeys);
        $newAllExtends = array_values($newAllExtends);

        $userExtensions = [];
        foreach ($newAllExtends as $extend) {
            $badge = ExtendUtility::getAppBadge($extend['fskey'], $authUserId);

            $extend['badgeType'] = $badge['badgeType'];
            $extend['badgeValue'] = $badge['badgeValue'];

            $userExtensions[] = $extend;
        }

        return $userExtensions;
    }

    // get group extensions
    public static function getGroupExtensions(int $groupId, string $langTag, ?int $authUserId = null): array
    {
        $everyoneExtends = ExtendUtility::getAppExtendsByEveryone(AppUsage::TYPE_GROUP, null, $groupId, $langTag);

        $roleExtends = [];
        $roleArr = PermissionUtility::getUserRoles($authUserId, $langTag);
        foreach ($roleArr as $role) {
            $roleExtends[] = ExtendUtility::getAppExtendsByRole(AppUsage::TYPE_GROUP, $role['rid'], null, $groupId, $langTag);
        }

        $groupAdminExtends = [];
        if ($groupId) {
            $checkGroupAdmin = PermissionUtility::checkUserGroupAdmin($groupId, $authUserId);
            $groupAdminExtends = $checkGroupAdmin ? ExtendUtility::getAppExtendsByGroupAdmin(AppUsage::TYPE_GROUP, null, $groupId, $langTag) : [];
        }

        $allExtends = array_merge($everyoneExtends, Arr::collapse($roleExtends), $groupAdminExtends);

        if (empty($allExtends)) {
            return [];
        }

        $fskeys = array_column($allExtends, 'name');
        $fskeys = array_unique($fskeys);
        $newAllExtends = array_intersect_key($allExtends, $fskeys);
        $newAllExtends = array_values($newAllExtends);

        $groupExtensions = [];
        foreach ($newAllExtends as $extend) {
            $badge = ExtendUtility::getAppBadge($extend['fskey'], $authUserId);

            $extend['badgeType'] = $badge['badgeType'];
            $extend['badgeValue'] = $badge['badgeValue'];

            $groupExtensions[] = $extend;
        }

        return $groupExtensions;
    }
}
