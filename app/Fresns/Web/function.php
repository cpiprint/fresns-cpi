<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jarvis Tang
 * Released under the Apache-2.0 License.
 */

use App\Fresns\Web\Auth\UserGuard;
use App\Fresns\Web\Helpers\ApiHelper;
use App\Helpers\CacheHelper;
use App\Helpers\ConfigHelper;
use App\Helpers\LanguageHelper;
use App\Helpers\PluginHelper;
use App\Helpers\StrHelper;
use App\Models\Config;
use App\Models\File;
use Illuminate\Support\Facades\Cache;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// current langTag
if (! function_exists('current_lang_tag')) {
    function current_lang_tag()
    {
        return \App::getLocale() ?? ConfigHelper::fresnsConfigByItemKey('default_language');
    }
}

// fresns api config
if (! function_exists('fs_api_config')) {
    function fs_api_config(string $itemKey, mixed $default = null)
    {
        $langTag = current_lang_tag();

        $cacheKey = "fresns_web_api_config_all_{$langTag}";
        $cacheTime = CacheHelper::fresnsCacheTimeByFileType(File::TYPE_ALL);

        $apiConfig = Cache::remember($cacheKey, $cacheTime, function () {
            $result = ApiHelper::make()->get('/api/v2/global/configs', [
                'query' => [
                    'isAll' => true,
                ],
            ]);

            return $result;
        });

        if (! $apiConfig) {
            Cache::forget($cacheKey);
        }

        return data_get($apiConfig, "data.list.{$itemKey}") ?? $default;
    }
}

// fresns db config
if (! function_exists('fs_db_config')) {
    function fs_db_config(string $itemKey, mixed $default = null)
    {
        $langTag = current_lang_tag();

        $cacheKey = "fresns_web_db_config_{$itemKey}_{$langTag}";
        $cacheTime = CacheHelper::fresnsCacheTimeByFileType(File::TYPE_ALL);

        $dbConfig = Cache::remember($cacheKey, $cacheTime, function () use ($itemKey, $langTag) {
            $config = Config::where('item_key', $itemKey)->first();

            if (! $config) {
                return null;
            }

            $itemValue = $config->item_value;

            if ($config->is_multilingual == 1) {
                $itemValue = LanguageHelper::fresnsLanguageByTableKey($config->item_key, $config->item_type, $langTag);
            } elseif ($config->item_type == 'file' && StrHelper::isPureInt($config->item_value)) {
                $itemValue = ConfigHelper::fresnsConfigFileUrlByItemKey($config->item_value);
            } elseif ($config->item_type == 'plugin') {
                $itemValue = PluginHelper::fresnsPluginUrlByUnikey($config->item_value);
            } elseif ($config->item_type == 'plugins') {
                if ($config->item_value) {
                    foreach ($config->item_value as $plugin) {
                        $pluginItem['code'] = $plugin['code'];
                        $pluginItem['url'] = PluginHelper::fresnsPluginUrlByUnikey($plugin['unikey']);
                        $itemArr[] = $pluginItem;
                    }
                    $itemValue = $itemArr;
                }
            }

            return $itemValue;
        });

        if (! $dbConfig) {
            Cache::forget($cacheKey);
        }

        return $dbConfig ?? $default;
    }
}

// fs_lang
if (! function_exists('fs_lang')) {
    function fs_lang(string $langKey, ?string $default = null): ?string
    {
        $langArr = fs_api_config('language_pack_contents');
        $result = $langArr[$langKey] ?? $default;

        return $result;
    }
}

// fs_code_message
if (! function_exists('fs_code_message')) {
    function fs_code_message(int $code, ?string $unikey = 'Fresns', ?string $default = null): ?string
    {
        $langTag = current_lang_tag();

        $cacheKey = "fresns_web_code_message_all_{$unikey}_{$langTag}";
        $cacheTime = CacheHelper::fresnsCacheTimeByFileType();

        $codeMessages = Cache::remember($cacheKey, $cacheTime, function () use ($unikey) {
            $result = ApiHelper::make()->get('/api/v2/global/code-messages', [
                'query' => [
                    'unikey' => $unikey,
                    'isAll' => true,
                ],
            ]);

            return $result;
        });

        if (! $codeMessages) {
            Cache::forget($cacheKey);
        }

        return data_get($codeMessages, "data.{$code}") ?? $default;
    }
}

if (! function_exists('fs_route')) {
    /**
     * @param  string|null  $url
     * @param  string|bool|null  $locale
     * @return string
     */
    function fs_route(string $url = null, string|bool $locale = null): string
    {
        return LaravelLocalization::localizeUrl($url, $locale);
    }
}

if (! function_exists('fs_account')) {
    /**
     * @return AccountGuard|mixin
     */
    function fs_account(?string $detailKey = null)
    {
        if ($detailKey) {
            return app('fresns.account')->get($detailKey);
        }

        return app('fresns.account');
    }
}

if (! function_exists('fs_user')) {
    /**
     * @return UserGuard|mixin
     */
    function fs_user(?string $detailKey = null)
    {
        if ($detailKey) {
            return app('fresns.user')->get($detailKey);
        }

        return app('fresns.user');
    }
}
