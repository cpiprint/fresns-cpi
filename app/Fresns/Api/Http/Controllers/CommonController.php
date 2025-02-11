<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Fresns\Api\Http\Controllers;

use App\Fresns\Api\Exceptions\ResponseException;
use App\Fresns\Api\Http\DTO\CommonCmdWordDTO;
use App\Fresns\Api\Http\DTO\CommonFileLinkDTO;
use App\Fresns\Api\Http\DTO\CommonFileUpdateDTO;
use App\Fresns\Api\Http\DTO\CommonFileUploadDTO;
use App\Fresns\Api\Http\DTO\CommonFileUploadTokenDTO;
use App\Fresns\Api\Http\DTO\CommonFileUsersDTO;
use App\Fresns\Api\Http\DTO\CommonInputTipsDTO;
use App\Fresns\Api\Http\DTO\CommonIpInfoDTO;
use App\Helpers\CacheHelper;
use App\Helpers\ConfigHelper;
use App\Helpers\DateHelper;
use App\Helpers\FileHelper;
use App\Helpers\PrimaryHelper;
use App\Models\App;
use App\Models\ConversationMessage;
use App\Models\File;
use App\Models\FileDownload;
use App\Models\FileUsage;
use App\Models\Hashtag;
use App\Models\TempCallbackContent;
use App\Models\User;
use App\Utilities\ConfigUtility;
use App\Utilities\DetailUtility;
use App\Utilities\FileUtility;
use App\Utilities\InteractionUtility;
use App\Utilities\PermissionUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommonController extends Controller
{
    // ip info
    public function ipInfo(Request $request)
    {
        $dtoRequest = new CommonIpInfoDTO($request->all());

        $ip = $dtoRequest->ip ?? $request->ip();

        $fresnsResp = \FresnsCmdWord::plugin('Fresns')->ipInfo([
            'ip' => $ip,
        ]);

        return $fresnsResp->getOrigin();
    }

    // input tips
    public function inputTips(Request $request)
    {
        $dtoRequest = new CommonInputTipsDTO($request->all());
        $authUserId = $this->user()?->id;

        $list = [];

        switch ($dtoRequest->type) {
            case 'user':
                $userIdentifier = ConfigHelper::fresnsConfigByItemKey('user_identifier');

                if ($userIdentifier == 'uid') {
                    $userWhereAny = ['uid', 'nickname'];
                } else {
                    $userWhereAny = ['username', 'nickname'];
                }

                $users = User::whereAny($userWhereAny, 'LIKE', "%$dtoRequest->key%")->isEnabled()->limit(10)->get();

                if ($users) {
                    foreach ($users as $user) {
                        $interactionStatus = InteractionUtility::getInteractionStatus(InteractionUtility::TYPE_USER, $user->id, $authUserId);

                        $item['fsid'] = ($userIdentifier == 'uid') ? $user->uid : $user->username;
                        $item['name'] = $user->nickname;
                        $item['image'] = $user->getUserAvatar();
                        $item['interaction'] = [
                            'likeStatus' => $interactionStatus['likeStatus'],
                            'dislikeStatus' => $interactionStatus['dislikeStatus'],
                            'followStatus' => $interactionStatus['followStatus'],
                            'blockStatus' => $interactionStatus['blockStatus'],
                            'note' => $interactionStatus['note'],
                        ];

                        $list[] = $item;
                    }
                }
                break;

            case 'hashtag':
                $hashtags = Hashtag::where('name', 'LIKE', "%$dtoRequest->key%")->isEnabled()->limit(10)->get();

                foreach ($hashtags as $hashtag) {
                    $interactionStatus = InteractionUtility::getInteractionStatus(InteractionUtility::TYPE_HASHTAG, $hashtag->id, $authUserId);

                    $item['fsid'] = $hashtag->slug;
                    $item['name'] = $hashtag->name;
                    $item['image'] = FileHelper::fresnsFileUrlByTableColumn($hashtag->cover_file_id, $hashtag->cover_file_url);
                    $item['interaction'] = [
                        'likeStatus' => $interactionStatus['likeStatus'],
                        'dislikeStatus' => $interactionStatus['dislikeStatus'],
                        'followStatus' => $interactionStatus['followStatus'],
                        'blockStatus' => $interactionStatus['blockStatus'],
                        'note' => $interactionStatus['note'],
                    ];

                    $list[] = $item;
                }
                break;
        }

        return $this->success($list);
    }

    // cmd word
    public function cmdWord(Request $request)
    {
        $dtoRequest = new CommonCmdWordDTO($request->all());

        $fskey = $dtoRequest->fskey;
        $wordName = $dtoRequest->cmdWord;
        $wordBody = $dtoRequest->wordBody ?? [];

        $commandWords = ConfigHelper::fresnsConfigByItemKey('interface_command_words');

        $filtered = array_filter($commandWords, function ($item) use ($fskey, $wordName) {
            return $item['fskey'] == $fskey && $item['cmdWord'] == $wordName;
        });

        $cmdWordArr = array_values($filtered);

        if (empty($cmdWordArr)) {
            throw new ResponseException(32100);
        }

        $fresnsResp = \FresnsCmdWord::plugin($fskey)->$wordName($wordBody);

        return $fresnsResp->getOrigin();
    }

    // file upload token
    public function fileUploadToken(Request $request)
    {
        $dtoRequest = new CommonFileUploadTokenDTO($request->all());

        $fileTypeInt = match ($dtoRequest->type) {
            'image' => File::TYPE_IMAGE,
            'video' => File::TYPE_VIDEO,
            'audio' => File::TYPE_AUDIO,
            'document' => File::TYPE_DOCUMENT,
        };

        $storageConfig = FileHelper::fresnsFileStorageConfigByType($fileTypeInt);

        if (! $storageConfig['storageConfigStatus']) {
            return $this->failure(21000, ConfigUtility::getCodeMessage(21000, 'CmdWord'));
        }

        $servicePlugin = App::where('fskey', $storageConfig['service'])->isEnabled()->first();

        if (! $servicePlugin) {
            throw new ResponseException(32102);
        }

        $platformId = $this->platformId();
        $aid = \request()->header('X-Fresns-Aid');
        $uid = \request()->header('X-Fresns-Uid');

        // check file info
        $permWordBody = [
            'uid' => $uid,
            'usageType' => $dtoRequest->usageType,
            'usageFsid' => $dtoRequest->usageFsid,
            'archiveCode' => $dtoRequest->archiveCode,
            'type' => $fileTypeInt,
            'extension' => $dtoRequest->extension,
            'size' => $dtoRequest->size,
            'duration' => $dtoRequest->duration,
        ];

        $permResp = \FresnsCmdWord::plugin('Fresns')->checkUploadPerm($permWordBody);

        if ($permResp->isErrorResponse()) {
            return $permResp->getErrorResponse();
        }

        $usageType = $permResp->getData('usageType');
        $tableName = $permResp->getData('tableName');
        $tableColumn = $permResp->getData('tableColumn');
        $tableId = $permResp->getData('tableId');
        $tableKey = $permResp->getData('tableKey');

        $storePath = FileHelper::fresnsFileStoragePath($fileTypeInt, $usageType);
        $fileNewName = (string) Str::ulid();
        $path = $storePath.'/'.$fileNewName.'.'.$dtoRequest->extension;

        $wordBody = [
            'type' => $fileTypeInt,
            'path' => $path,
            'minutes' => 10,
        ];

        $fresnsResp = \FresnsCmdWord::plugin('Fresns')->getUploadToken($wordBody);

        if ($fresnsResp->isErrorResponse()) {
            return $fresnsResp->getErrorResponse();
        }

        // warning type
        $warningType = match ($dtoRequest->warning) {
            'none' => File::WARNING_NONE,
            'nudity' => File::WARNING_NUDITY,
            'violence' => File::WARNING_VIOLENCE,
            'sensitive' => File::WARNING_SENSITIVE,
            default => File::WARNING_NONE,
        };

        $fileInfo = [
            'type' => $fileTypeInt,
            'name' => $dtoRequest->name,
            'mime' => $dtoRequest->mime,
            'extension' => $dtoRequest->extension,
            'size' => $dtoRequest->size,
            'width' => $dtoRequest->width,
            'height' => $dtoRequest->height,
            'duration' => $dtoRequest->duration,
            'sha' => $dtoRequest->sha,
            'shaType' => $dtoRequest->shaType,
            'warningType' => $warningType,
            'path' => $path,
            'transcodingState' => File::TRANSCODING_STATE_WAIT,
            'videoPosterPath' => null,
            'originalPath' => null,
            'uploaded' => false,
        ];

        $usageInfo = [
            'usageType' => $usageType,
            'platformId' => $platformId,
            'tableName' => $tableName,
            'tableColumn' => $tableColumn,
            'tableId' => $tableId,
            'tableKey' => $tableKey,
            'sortOrder' => null,
            'moreInfo' => $dtoRequest->moreInfo,
            'aid' => $aid,
            'uid' => $uid,
            'remark' => null,
        ];

        $fileModel = FileUtility::saveFileInfo($fileInfo, $usageInfo);

        if (! $fileModel) {
            throw new ResponseException(30008);
        }

        $data = $fresnsResp->getData();
        $data['headers']['Content-Type'] = $fileModel->mime;
        $data['headers']['Cache-Control'] = '';
        $data['fid'] = $fileModel->fid;

        // callback
        \FresnsCmdWord::plugin('Fresns')->updateOrCreateCallbackContent([
            'fskey' => 'Fresns',
            'callbackKey' => $fileModel->fid,
            'callbackType' => TempCallbackContent::TYPE_FILE,
            'callbackContent' => [
                'tableName' => $tableName,
                'tableColumn' => $tableColumn,
                'tableId' => $tableId,
            ],
        ]);

        return $this->success($data);
    }

    // file upload
    public function fileUpload(Request $request)
    {
        $dtoRequest = new CommonFileUploadDTO($request->all());

        $fileType = match ($dtoRequest->type) {
            'image' => File::TYPE_IMAGE,
            'video' => File::TYPE_VIDEO,
            'audio' => File::TYPE_AUDIO,
            'document' => File::TYPE_DOCUMENT,
        };

        // check upload service
        $storageConfig = FileHelper::fresnsFileStorageConfigByType($fileType);

        if (! $storageConfig['storageConfigStatus']) {
            throw new ResponseException(32100);
        }

        $servicePlugin = App::where('fskey', $storageConfig['service'])->isEnabled()->first();

        if (! $servicePlugin) {
            throw new ResponseException(32102);
        }

        $platformId = $this->platformId();
        $aid = \request()->header('X-Fresns-Aid');
        $uid = \request()->header('X-Fresns-Uid');

        $fileExtension = $dtoRequest->file->extension();
        $fileSize = $dtoRequest->file->getSize();

        // check file info
        $permWordBody = [
            'uid' => $uid,
            'usageType' => $dtoRequest->usageType,
            'usageFsid' => $dtoRequest->usageFsid,
            'archiveCode' => $dtoRequest->archiveCode,
            'type' => $fileType,
            'extension' => $fileExtension,
            'size' => $fileSize,
            'duration' => null,
        ];

        $permResp = \FresnsCmdWord::plugin('Fresns')->checkUploadPerm($permWordBody);

        if ($permResp->isErrorResponse()) {
            return $permResp->getErrorResponse();
        }

        $usageType = $permResp->getData('usageType');
        $tableName = $permResp->getData('tableName');
        $tableColumn = $permResp->getData('tableColumn');
        $tableId = $permResp->getData('tableId');
        $tableKey = $permResp->getData('tableKey');

        // warning type
        $warningType = match ($dtoRequest->warning) {
            'none' => File::WARNING_NONE,
            'nudity' => File::WARNING_NUDITY,
            'violence' => File::WARNING_VIOLENCE,
            'sensitive' => File::WARNING_SENSITIVE,
            default => File::WARNING_NONE,
        };

        // more info
        $moreInfo = null;
        if ($dtoRequest->moreInfo) {
            try {
                $moreInfo = json_decode($dtoRequest->moreInfo, true);
            } catch (\Exception $e) {
            }
        }

        // upload
        $wordBody = [
            'file' => $dtoRequest->file,
            'type' => $fileType,
            'warningType' => $warningType,

            'usageType' => $usageType,
            'platformId' => $platformId,
            'tableName' => $tableName,
            'tableColumn' => $tableColumn,
            'tableId' => $tableId,
            'tableKey' => $tableKey,
            'moreInfo' => $moreInfo,
            'aid' => $aid,
            'uid' => $uid,
        ];

        $fresnsResp = \FresnsCmdWord::plugin($storageConfig['service'])->uploadFile($wordBody);

        if ($fresnsResp->isErrorResponse()) {
            return $fresnsResp->getErrorResponse();
        }

        // use file
        $authUser = $this->user();
        FileUtility::useFile($authUser->id, $fresnsResp->getData('fid'), $tableName, $tableColumn, $tableId);

        return $fresnsResp->getOrigin();
    }

    // file update
    public function fileUpdate(string $fid, Request $request)
    {
        $dtoRequest = new CommonFileUpdateDTO($request->all());

        $authAccountId = $this->account()->id;
        $authUserId = $this->user()->id;

        // check file
        $file = File::whereFid($fid)->first();
        if (empty($file)) {
            throw new ResponseException(37600);
        }

        if (! $file->is_enabled) {
            throw new ResponseException(37601);
        }

        $checkUploader = FileUsage::where('file_id', $file->id)->where('account_id', $authAccountId)->where('user_id', $authUserId)->first();

        if (! $checkUploader) {
            throw new ResponseException(37602);
        }

        // uploaded
        if ($dtoRequest->uploaded && ! $file->is_uploaded) {
            $file->update([
                'is_uploaded' => true,
            ]);

            // callback
            $fresnsResp = \FresnsCmdWord::plugin('Fresns')->getCallbackContent([
                'fskey' => 'Fresns',
                'callbackKey' => $file->fid,
                'markAsUsed' => true,
            ]);

            if ($fresnsResp->isSuccessResponse()) {
                $tableName = $fresnsResp->getData('tableName');
                $tableColumn = $fresnsResp->getData('tableColumn');
                $tableId = $fresnsResp->getData('tableId');

                // use file
                if ($tableName && $tableColumn && $tableId) {
                    FileUtility::useFile($authUserId, $file->fid, $tableName, $tableColumn, $tableId);
                }
            }
        }

        // warning
        if ($dtoRequest->warning) {
            $warningType = match ($dtoRequest->warning) {
                'none' => File::WARNING_NONE,
                'nudity' => File::WARNING_NUDITY,
                'violence' => File::WARNING_VIOLENCE,
                'sensitive' => File::WARNING_SENSITIVE,
                default => File::WARNING_NONE,
            };

            $file->update([
                'warning_type' => $warningType,
            ]);
        }

        CacheHelper::clearDataCache('file', $file->fid);

        $fileInfo = FileHelper::fresnsFileInfoById($file->id);

        return $this->success($fileInfo);
    }

    // file download link
    public function fileLink(string $fid, Request $request)
    {
        $dtoRequest = new CommonFileLinkDTO($request->all());

        $authAccountId = $this->account()->id;
        $authUserId = $this->user()->id;

        $mainRolePerms = PermissionUtility::getUserMainRole($authUserId, $this->langTag())['permissions'];

        // check down count
        $roleDownloadCount = $mainRolePerms['download_file_count'] ?? 0;
        if ($roleDownloadCount == 0) {
            throw new ResponseException(36102);
        }

        $userDownloadCount = FileDownload::where('user_id', $authUserId)->whereDate('created_at', now())->count();
        if ($roleDownloadCount <= $userDownloadCount) {
            throw new ResponseException(36117);
        }

        // check file
        $file = File::whereFid($fid)->first();
        if (empty($file)) {
            throw new ResponseException(37600);
        }

        if (! $file->is_enabled) {
            throw new ResponseException(37601);
        }

        // get model
        if ($dtoRequest->type == 'conversation') {
            $model = ConversationMessage::where('cmid', $dtoRequest->fsid)->first();

            $tableId = $model?->conversation_id;
        } else {
            $model = PrimaryHelper::fresnsModelByFsid($dtoRequest->type, $dtoRequest->fsid);

            $tableId = $model?->id;
        }

        // check model
        if (empty($model)) {
            throw new ResponseException(32201);
        }

        if ($model->deleted_at) {
            throw new ResponseException(32304);
        }

        $permissions = $model?->permissions ?? [];
        $isReadLocked = $permissions['readConfig']['isReadLocked'] ?? false;

        // check permission
        if ($dtoRequest->type == 'post' && $isReadLocked) {
            $checkPostAuth = PermissionUtility::checkPostAuth($model->id, $authUserId);

            if (! $checkPostAuth) {
                throw new ResponseException(35301);
            }
        }

        if ($dtoRequest->type == 'conversation') {
            if ($model->send_user_id != $authUserId && $model->receive_user_id != $authUserId) {
                throw new ResponseException(36602);
            }
        }

        $fileUsage = FileUsage::where('file_id', $file->id)
            ->where('table_name', "{$dtoRequest->type}s")
            ->where('table_column', 'id')
            ->where('table_id', $tableId)
            ->first();

        if (empty($fileUsage)) {
            throw new ResponseException(32304);
        }

        $data['link'] = FileHelper::fresnsFileOriginalUrlById($file->id);

        $targetType = match ($dtoRequest->type) {
            'post' => FileDownload::TYPE_POST,
            'comment' => FileDownload::TYPE_COMMENT,
            'conversation' => FileDownload::TYPE_CONVERSATION,
        };
        $downloader = [
            'file_id' => $file->id,
            'file_type' => $file->type,
            'account_id' => $authAccountId,
            'user_id' => $authUserId,
            'target_type' => $targetType,
            'target_id' => $model->id,
        ];
        FileDownload::create($downloader);

        return $this->success($data);
    }

    // file download users
    public function fileUsers(string $fid, Request $request)
    {
        $dtoRequest = new CommonFileUsersDTO($request->all());

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $file = File::whereFid($fid)->first();
        if (empty($file)) {
            throw new ResponseException(37600);
        }

        if (! $file->is_enabled) {
            throw new ResponseException(37601);
        }

        $dbType = config('database.default');

        switch ($dbType) {
            case 'sqlite':
                $downUsers = FileDownload::with(['user'])
                    ->select([
                        'id',
                        'file_id',
                        'file_type',
                        'account_id',
                        'user_id',
                        'app_fskey',
                        'target_type',
                        'target_id',
                        'created_at',
                    ])
                    ->whereIn('id', function ($query) use ($file) {
                        $query->select(DB::raw('max(id)'))
                            ->from('file_downloads')
                            ->where('file_id', $file->id)
                            ->groupBy('user_id');
                    })
                    ->orderByDesc('created_at')
                    ->paginate($dtoRequest->pageSize ?? 15);
                break;

            case 'mysql':
                $downUsers = FileDownload::with(['user'])
                    ->select([
                        DB::raw('any_value(id) as id'),
                        DB::raw('any_value(file_id) as file_id'),
                        DB::raw('any_value(file_type) as file_type'),
                        DB::raw('any_value(account_id) as account_id'),
                        DB::raw('any_value(user_id) as user_id'),
                        DB::raw('any_value(app_fskey) as app_fskey'),
                        DB::raw('any_value(target_type) as target_type'),
                        DB::raw('any_value(target_id) as target_id'),
                        DB::raw('any_value(created_at) as created_at'),
                    ])
                    ->where('file_id', $file->id)
                    ->groupBy('user_id')
                    ->latest()
                    ->paginate($dtoRequest->pageSize ?? 15);
                break;

            case 'mariadb':
                $downUsers = FileDownload::with(['user'])
                    ->select([
                        DB::raw('any_value(id) as id'),
                        DB::raw('any_value(file_id) as file_id'),
                        DB::raw('any_value(file_type) as file_type'),
                        DB::raw('any_value(account_id) as account_id'),
                        DB::raw('any_value(user_id) as user_id'),
                        DB::raw('any_value(app_fskey) as app_fskey'),
                        DB::raw('any_value(target_type) as target_type'),
                        DB::raw('any_value(target_id) as target_id'),
                        DB::raw('any_value(created_at) as created_at'),
                    ])
                    ->where('file_id', $file->id)
                    ->groupBy('user_id')
                    ->latest()
                    ->paginate($dtoRequest->pageSize ?? 15);
                break;

            case 'pgsql':
                $downUsers = FileDownload::with(['user'])
                    ->select([
                        DB::raw('DISTINCT ON (user_id) id'),
                        'file_id',
                        'file_type',
                        'account_id',
                        'user_id',
                        'app_fskey',
                        'target_type',
                        'target_id',
                        'created_at',
                    ])
                    ->where('file_id', $file->id)
                    ->orderBy('user_id')
                    ->orderByDesc('created_at')
                    ->paginate($dtoRequest->pageSize ?? 15);
                break;

            case 'sqlsrv':
                $downUsers = FileDownload::with(['user'])
                    ->select([
                        DB::raw('DISTINCT user_id'),
                        'id',
                        'file_id',
                        'file_type',
                        'account_id',
                        'app_fskey',
                        'target_type',
                        'target_id',
                        'created_at',
                    ])
                    ->where('file_id', $file->id)
                    ->orderBy('user_id')
                    ->orderByDesc('created_at')
                    ->paginate($dtoRequest->pageSize ?? 15);
                break;

            default:
                $downUsers = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        $userOptions = [
            'viewType' => 'list',
            'isLiveStats' => false,
            'filter' => [
                'type' => $dtoRequest->filterUserType,
                'keys' => $dtoRequest->filterUserKeys,
            ],
        ];

        $items = [];
        foreach ($downUsers as $downloader) {
            if (empty($downloader->user)) {
                continue;
            }

            $item['datetime'] = DateHelper::fresnsFormatDateTime($downloader->created_at, $timezone, $langTag);
            $item['timeAgo'] = DateHelper::fresnsHumanReadableTime($downloader->created_at, $langTag);
            $item['user'] = DetailUtility::userDetail($downloader->user, $langTag, $timezone, $authUser?->id, $userOptions);

            $items[] = $item;
        }

        return $this->fresnsPaginate($items, $downUsers->total(), $downUsers->perPage());
    }
}
