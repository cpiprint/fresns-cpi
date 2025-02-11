<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Fresns\Words\Feature;

use App\Fresns\Words\Feature\DTO\AddCrontabItemDTO;
use App\Fresns\Words\Feature\DTO\RemoveCrontabItemDTO;
use App\Helpers\CacheHelper;
use App\Helpers\ConfigHelper;
use App\Models\Account;
use App\Models\App;
use App\Models\Config;
use App\Models\User;
use App\Models\UserRole;
use Fresns\CmdWordManager\Traits\CmdWordResponseTrait;
use Illuminate\Support\Facades\Http;

class Crontab
{
    use CmdWordResponseTrait;

    // addCrontabItem
    public function addCrontabItem($wordBody)
    {
        $dtoWordBody = new AddCrontabItemDTO($wordBody);

        $crontabItems = Config::withTrashed()->where('item_key', 'crontab_items')->first();
        if (empty($crontabItems)) {
            return $this->failure(21008);
        }

        $itemArr = $crontabItems->item_value ?? [];

        $found = false;
        foreach ($itemArr as $item) {
            if ($item['fskey'] == $dtoWordBody->fskey && $item['cmdWord'] == $dtoWordBody->cmdWord) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            $itemArr[] = [
                'fskey' => $dtoWordBody->fskey,
                'cmdWord' => $dtoWordBody->cmdWord,
                'cronTableFormat' => $dtoWordBody->cronTableFormat,
            ];
        }

        $crontabItems->update([
            'item_value' => $itemArr,
        ]);

        CacheHelper::forgetFresnsConfigs('crontab_items');

        return $this->success();
    }

    // removeCrontabItem
    public function removeCrontabItem($wordBody)
    {
        $dtoWordBody = new RemoveCrontabItemDTO($wordBody);

        $crontabItems = Config::withTrashed()->where('item_key', 'crontab_items')->first();
        if (empty($crontabItems)) {
            return $this->failure(21008);
        }

        $cronArr = $crontabItems->item_value ?? [];

        $newItemArr = array_filter($cronArr, function ($item) use ($dtoWordBody) {
            return ! ($item['fskey'] == $dtoWordBody->fskey && $item['cmdWord'] == $dtoWordBody->cmdWord);
        });

        $newItemArr = array_values($newItemArr);

        $crontabItems->update([
            'item_value' => $newItemArr,
        ]);

        CacheHelper::forgetFresnsConfigs('crontab_items');

        return $this->success();
    }

    // checkUserRoleExpired
    public function checkUserRoleExpired()
    {
        info('cmd word: checkUserRoleExpired');

        $roleArr = UserRole::where('is_main', 1)->where('expired_at', '<', now())->get();

        foreach ($roleArr as $role) {
            if ($role->restore_role_id) {
                $nextRole = UserRole::whereNot('id', $role->id)
                    ->where('user_id', $role->user_id)
                    ->where('role_id', $role->restore_role_id)
                    ->first();

                // change role
                if (empty($nextRole)) {
                    UserRole::create([
                        'user_id' => $role->user_id,
                        'role_id' => $role->restore_role_id,
                        'is_main' => 1,
                    ]);
                } else {
                    $nextRole->update([
                        'is_main' => 1,
                    ]);
                }

                // delete old role
                $role->delete();
            }

            // clear role cache
            CacheHelper::forgetFresnsMultilingual("fresns_user_{$role->user_id}_main_role", 'fresnsUsers');
        }

        return $this->success();
    }

    // checkDeleteAccount
    public function checkDeleteAccount()
    {
        info('cmd word: checkDeleteAccount');

        $deleteType = ConfigHelper::fresnsConfigByItemKey('delete_account_type');

        if ($deleteType == Config::DELETE_ACCOUNT_CLOSE) {
            return $this->failure(21010);
        }

        $accountList = Account::where('wait_delete', true)->where('wait_delete_at', '<', now())->get();
        $userList = User::where('wait_delete', true)->where('wait_delete_at', '<', now())->get();

        switch ($deleteType) {
            case Config::DELETE_ACCOUNT_LOGICAL:
                foreach ($accountList as $account) {
                    \FresnsCmdWord::plugin('Fresns')->logicalDeletionAccount([
                        'aid' => $account->aid,
                    ]);
                }

                foreach ($userList as $user) {
                    \FresnsCmdWord::plugin('Fresns')->logicalDeletionUser([
                        'uid' => $user->uid,
                    ]);
                }
                break;

            case Config::DELETE_ACCOUNT_PHYSICAL:
                foreach ($accountList as $account) {
                    // waiting for development
                    // \FresnsCmdWord::plugin('Fresns')->physicalDeletionAccount([
                    //     'aid' => $account->aid,
                    // ]);

                    \FresnsCmdWord::plugin('Fresns')->logicalDeletionAccount([
                        'aid' => $account->aid,
                    ]);
                }

                foreach ($userList as $user) {
                    // waiting for development
                    // \FresnsCmdWord::plugin('Fresns')->physicalDeletionUser([
                    //     'uid' => $user->uid,
                    // ]);

                    \FresnsCmdWord::plugin('Fresns')->logicalDeletionUser([
                        'uid' => $user->uid,
                    ]);
                }
                break;

            default:
                return $this->failure(21004);
        }

        return $this->success();
    }

    // checkAppsVersions
    public function checkAppsVersions()
    {
        info('cmd word: checkAppsVersions');

        // Time of the latest check version
        Config::updateOrCreate([
            'item_key' => 'check_version_datetime',
        ], [
            'item_value' => now(),
            'item_type' => 'string',
        ]);

        // apps
        $apps = App::all();

        // market-manager
        $response = Http::market()->get('/api/open-source/v3/check', [
            'fskeys' => json_encode($apps->pluck('fskey')->all()),
        ]);

        // Request error
        if ($response->failed()) {
            return [
                'code' => 12000,
                'message' => 'Error: request failed (host or api)',
                'data' => [],
            ];
        }

        $data = $response->json('data') ?? null;

        if (empty($data)) {
            return $this->success();
        }

        foreach ($response->json('data') as $fskey => $version) {
            if (is_null($version)) {
                continue;
            }

            $app = $apps->where('fskey', $fskey)->first();

            // Same version number
            if ($app->version == $version) {
                continue;
            }

            $app->update([
                'is_upgrade' => true,
                'upgrade_version' => $version,
            ]);
        }

        return $this->success();
    }
}
