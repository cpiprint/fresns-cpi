<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Helpers;

use App\Models\Config;
use App\Models\File;
use Illuminate\Support\Facades\Cache;

class ConfigHelper
{
    // site url
    public static function fresnsSiteUrl(): string
    {
        $siteUrl = ConfigHelper::fresnsConfigByItemKey('site_url');

        return $siteUrl ?? config('app.url');
    }

    // default langTag
    public static function fresnsConfigDefaultLangTag(): string
    {
        $defaultLangTag = ConfigHelper::fresnsConfigByItemKey('default_language');

        return $defaultLangTag ?? config('app.locale');
    }

    // lang tags
    public static function fresnsConfigLangTags(): ?array
    {
        $languageMenus = ConfigHelper::fresnsConfigByItemKey('language_menus');

        $langTagArr = [];
        if ($languageMenus) {
            $langTagArr = collect($languageMenus)->pluck('langTag')->all();
        }

        return $langTagArr;
    }

    // Get config developer
    public static function fresnsConfigDeveloper(): array
    {
        $developerMode = Cache::rememberForever('developer_configs', function () {
            $itemData = Config::where('item_key', 'developer_configs')->first();

            return $itemData?->item_value;
        });

        $developerModeArr = [
            'cache' => $developerMode['cache'] ?? true,
            'apiSignature' => $developerMode['apiSignature'] ?? true,
        ];

        return $developerModeArr;
    }

    // Get config value based on Key
    public static function fresnsConfigByItemKey(string $itemKey, ?string $langTag = null): mixed
    {
        $itemData = PrimaryHelper::fresnsModelByFsid('config', $itemKey);

        if (empty($itemData)) {
            return null;
        }

        return $itemData->is_multilingual ? StrHelper::languageContent($itemData->item_value, $langTag) : $itemData->item_value;
    }

    // Get multiple values based on multiple keys
    public static function fresnsConfigByItemKeys(array $itemKeys, ?string $langTag = null): array
    {
        $keysData = [];

        foreach ($itemKeys as $itemKey) {
            $keysData[$itemKey] = ConfigHelper::fresnsConfigByItemKey($itemKey, $langTag);
        }

        return $keysData;
    }

    // Determine the storage type based on the file key value
    public static function fresnsConfigFileValueTypeByItemKey(string $itemKey): string
    {
        $file = ConfigHelper::fresnsConfigByItemKey($itemKey);

        if (StrHelper::isPureInt($file)) {
            return 'ID';
        }

        return 'URL';
    }

    // Get config file url
    public static function fresnsConfigFileUrlByItemKey(string $itemKey, ?string $urlConfig = null): ?string
    {
        $configValue = ConfigHelper::fresnsConfigByItemKey($itemKey);

        if (! $configValue) {
            return null;
        }

        if (ConfigHelper::fresnsConfigFileValueTypeByItemKey($itemKey) == 'URL') {
            $fileUrl = $configValue;

            if (substr($configValue, 0, 1) === '/') {
                $fileUrl = StrHelper::qualifyUrl($configValue);
            }

            return $fileUrl;
        }

        return FileHelper::fresnsFileUrlById($configValue, $urlConfig);
    }

    // Get length units based on langTag
    public static function fresnsConfigLengthUnit(string $langTag): string
    {
        $languageMenus = ConfigHelper::fresnsConfigByItemKey('language_menus');

        if (empty($languageMenus)) {
            return 'km';
        }

        $lengthUnit = 'km'; // km or mi

        foreach ($languageMenus as $menu) {
            if ($menu['langTag'] == $langTag) {
                $lengthUnit = $menu['lengthUnit'];
            }
        }

        return $lengthUnit;
    }

    // Get date format according to langTag
    public static function fresnsConfigDateFormat(string $langTag): string
    {
        $languageMenus = ConfigHelper::fresnsConfigByItemKey('language_menus');

        if (empty($languageMenus)) {
            return 'mm/dd/yyyy';
        }

        $dateFormat = 'mm/dd/yyyy';

        foreach ($languageMenus as $menu) {
            if ($menu['langTag'] == $langTag) {
                $dateFormat = $menu['dateFormat'];
            }
        }

        return $dateFormat;
    }

    // Get file url expire datetime
    public static function fresnsConfigFileUrlExpire(): ?int
    {
        $cacheKey = 'fresns_config_file_url_expire';
        $cacheTag = 'fresnsConfigs';

        $urlExpire = CacheHelper::get($cacheKey, $cacheTag);

        if (empty($urlExpire)) {
            $fileConfigArr = Config::where('item_type', 'file')->get();

            // get file type
            $fileTypeArr = [];
            foreach ($fileConfigArr as $config) {
                if (! StrHelper::isPureInt($config->item_value)) {
                    continue;
                }

                $file = File::where('id', $config->item_value)->first();
                if (! $file) {
                    continue;
                }

                $fileTypeArr[] = $file->type;
            }

            $urlExpire = null;
            if ($fileTypeArr) {
                $fileType = array_unique($fileTypeArr);

                // get anti link expire
                $antiLinkExpire = [];
                foreach ($fileType as $type) {
                    $storageConfig = FileHelper::fresnsFileStorageConfigByType($type);

                    if (! $storageConfig['antiLinkStatus']) {
                        continue;
                    }

                    $antiLinkExpire[] = $storageConfig['antiLinkExpire'];
                }

                if (empty($antiLinkExpire)) {
                    return null;
                }

                $newAntiLinkExpire = array_filter($antiLinkExpire);
                $minAntiLinkExpire = min($newAntiLinkExpire);

                $urlExpire = $minAntiLinkExpire - 1;
            }

            CacheHelper::put($urlExpire, $cacheKey, $cacheTag);
        }

        return $urlExpire;
    }
}
