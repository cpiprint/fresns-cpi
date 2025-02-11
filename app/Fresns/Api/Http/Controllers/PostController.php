<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Fresns\Api\Http\Controllers;

use App\Fresns\Api\Exceptions\ResponseException;
use App\Fresns\Api\Http\DTO\HistoryDTO;
use App\Fresns\Api\Http\DTO\InteractionDTO;
use App\Fresns\Api\Http\DTO\PaginationDTO;
use App\Fresns\Api\Http\DTO\PostDetailDTO;
use App\Fresns\Api\Http\DTO\PostListDTO;
use App\Fresns\Api\Http\DTO\PostNearbyDTO;
use App\Fresns\Api\Http\DTO\PostQuotesDTO;
use App\Fresns\Api\Http\DTO\PostTimelinesDTO;
use App\Fresns\Api\Services\ContentService;
use App\Fresns\Api\Services\InteractionService;
use App\Fresns\Api\Services\TimelineService;
use App\Helpers\AppHelper;
use App\Helpers\CacheHelper;
use App\Helpers\ConfigHelper;
use App\Helpers\FileHelper;
use App\Helpers\PrimaryHelper;
use App\Helpers\StrHelper;
use App\Models\Post;
use App\Models\PostLog;
use App\Models\PostUser;
use App\Models\Seo;
use App\Models\SessionLog;
use App\Utilities\DetailUtility;
use App\Utilities\InteractionUtility;
use App\Utilities\PermissionUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    // list
    public function list(Request $request)
    {
        $dtoRequest = new PostListDTO($request->all());

        // Plugin provides data
        $dataPluginFskey = ConfigHelper::fresnsConfigByItemKey('post_list_service');

        if ($dataPluginFskey) {
            $wordBody = [
                'headers' => AppHelper::getHeaders(),
                'body' => $request->all(),
            ];

            $fresnsResp = \FresnsCmdWord::plugin($dataPluginFskey)->getPosts($wordBody);

            return $fresnsResp->getOrigin();
        }

        // Fresns provides data
        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();
        $authUserId = $authUser?->id;

        $postOptions = [
            'viewType' => 'list',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'location' => [
                'mapId' => $dtoRequest->mapId,
                'longitude' => $dtoRequest->mapLng,
                'latitude' => $dtoRequest->mapLat,
            ],
            'checkPermissions' => true,
            'isPreviewLikeUsers' => true,
            'isPreviewComments' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterGroup' => [
                'type' => $dtoRequest->filterGroupType,
                'keys' => $dtoRequest->filterGroupKeys,
            ],
            'filterHashtag' => [
                'type' => $dtoRequest->filterHashtagType,
                'keys' => $dtoRequest->filterHashtagKeys,
            ],
            'filterGeotag' => [
                'type' => $dtoRequest->filterGeotagType,
                'keys' => $dtoRequest->filterGeotagKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
            'filterQuotedPost' => [
                'type' => $dtoRequest->filterQuotedPostType,
                'keys' => $dtoRequest->filterQuotedPostKeys,
            ],
            'filterPreviewLikeUser' => [
                'type' => $dtoRequest->filterPreviewLikeUserType,
                'keys' => $dtoRequest->filterPreviewLikeUserKeys,
            ],
            'filterPreviewComment' => [
                'type' => $dtoRequest->filterPreviewCommentType,
                'keys' => $dtoRequest->filterPreviewCommentKeys,
            ],
        ];

        // cache
        $listCrc32 = crc32(json_encode($request->all()));
        $cacheKey = "fresns_api_post_list_{$listCrc32}_guest";

        $posts = CacheHelper::get($cacheKey, 'fresnsList');

        if (empty($authUserId) && $posts) {
            $postList = [];
            foreach ($posts as $post) {
                $postList[] = DetailUtility::postDetail($post, $langTag, $timezone, $authUserId, $postOptions);
            }

            return $this->fresnsPaginate($postList, $posts->total(), $posts->perPage());
        }

        // query
        $postQuery = Post::query();

        // has author
        $postQuery->whereRelation('author', 'is_enabled', true);

        // block
        $blockGroupIds = InteractionUtility::explodeIdArr('group', $dtoRequest->blockGroups);
        $privateGroupIds = PermissionUtility::getGroupContentFilterIdArr($authUserId);

        $filterUserIds = InteractionUtility::explodeIdArr('user', $dtoRequest->blockUsers);
        $filterGroupIds = array_unique(array_merge($blockGroupIds, $privateGroupIds));
        $filterHashtagIds = InteractionUtility::explodeIdArr('hashtag', $dtoRequest->blockHashtags);
        $filterGeotagIds = InteractionUtility::explodeIdArr('geotag', $dtoRequest->blockGeotags);
        $filterPostIds = InteractionUtility::explodeIdArr('post', $dtoRequest->blockPosts);

        if (empty($authUserId)) {
            $postQuery->where('is_enabled', true);
        } else {
            $postQuery->where(function ($query) use ($authUserId) {
                $query->where('is_enabled', true)->orWhere(function ($query) use ($authUserId) {
                    $query->where('is_enabled', false)->where('user_id', $authUserId);
                });
            });

            $blockUserIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_USER, $authUserId);
            $blockGroupIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GROUP, $authUserId);
            $blockHashtagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_HASHTAG, $authUserId);
            $blockGeotagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GEOTAG, $authUserId);
            $blockPostIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_POST, $authUserId);

            $filterUserIds = array_unique(array_merge($filterUserIds, $blockUserIds));
            $filterGroupIds = array_unique(array_merge($filterGroupIds, $blockGroupIds));
            $filterHashtagIds = array_unique(array_merge($filterHashtagIds, $blockHashtagIds));
            $filterGeotagIds = array_unique(array_merge($filterGeotagIds, $blockGeotagIds));
            $filterPostIds = array_unique(array_merge($filterPostIds, $blockPostIds));
        }

        $postQuery->when($filterUserIds, function ($query, $value) {
            $query->whereNotIn('user_id', $value);
        });

        $postQuery->when($filterGroupIds, function ($query, $value) {
            $query->whereNotIn('group_id', $value);
        });

        $postQuery->when($filterHashtagIds, function ($query, $value) {
            $query->where(function ($postQuery) use ($value) {
                $postQuery->whereDoesntHave('hashtagUsages')->orWhereHas('hashtagUsages', function ($query) use ($value) {
                    $query->whereNotIn('hashtag_id', $value);
                });
            });
        });

        $postQuery->when($filterGeotagIds, function ($query, $value) {
            $query->whereNotIn('geotag_id', $value);
        });

        $postQuery->when($filterPostIds, function ($query, $value) {
            $query->whereNotIn('id', $value);
        });

        // users
        if ($dtoRequest->users) {
            $profilePostsEnabled = ConfigHelper::fresnsConfigByItemKey('profile_posts_enabled');
            if (! $profilePostsEnabled) {
                throw new ResponseException(35305);
            }

            $userCrc32 = crc32($dtoRequest->users);
            $userCacheKey = "fresns_api_list_{$userCrc32}_user_ids";

            $userExplodeArr = CacheHelper::get($userCacheKey, 'fresnsConfigs');

            if (empty($userExplodeArr)) {
                $userExplodeArr = PermissionUtility::getPrimaryIdArr('user', $dtoRequest->users);

                CacheHelper::put($userExplodeArr, $userCacheKey, 'fresnsConfigs');
            }

            if ($userExplodeArr['idCount'] == 0) {
                return $this->warning(35400);
            }

            $postQuery->whereIn('user_id', $userExplodeArr['idArr'])->where('is_anonymous', false);
        }

        // groups
        $groupDateLimit = null;
        if ($dtoRequest->groups) {
            $groupCrc32Text = $dtoRequest->groups.$dtoRequest->includeSubgroups.$authUserId;
            $groupCrc32 = crc32($groupCrc32Text);
            $groupCacheKey = "fresns_api_list_{$groupCrc32}_group_ids";

            $groupExplodeArr = CacheHelper::get($groupCacheKey, 'fresnsConfigs');

            if (empty($groupExplodeArr)) {
                $groupExplodeArr = PermissionUtility::getPrimaryIdArr('group', $dtoRequest->groups, $authUserId, $dtoRequest->includeSubgroups);

                CacheHelper::put($groupExplodeArr, $groupCacheKey, 'fresnsConfigs');
            }

            if ($groupExplodeArr['idCount'] == 0) {
                return $this->warning(37102);
            }

            $groupDateLimit = $groupExplodeArr['datetime'];

            $postQuery->whereIn('group_id', $groupExplodeArr['idArr']);
        }

        // hashtags
        if ($dtoRequest->hashtags) {
            $hashtagCrc32 = crc32($dtoRequest->hashtags);
            $hashtagCacheKey = "fresns_api_list_{$hashtagCrc32}_hashtag_ids";

            $hashtagExplodeArr = CacheHelper::get($hashtagCacheKey, 'fresnsConfigs');

            if (empty($hashtagExplodeArr)) {
                $hashtagExplodeArr = PermissionUtility::getPrimaryIdArr('hashtag', $dtoRequest->hashtags);

                CacheHelper::put($hashtagExplodeArr, $hashtagCacheKey, 'fresnsConfigs');
            }

            if ($hashtagExplodeArr['idCount'] == 0) {
                return $this->warning(37202);
            }

            $viewHashtagIdArr = $hashtagExplodeArr['idArr'];

            $postQuery->whereHas('hashtagUsages', function ($query) use ($viewHashtagIdArr) {
                $query->whereIn('hashtag_id', $viewHashtagIdArr);
            });
        }

        // geotags
        if ($dtoRequest->geotags) {
            $geotagCrc32 = crc32($dtoRequest->geotags);
            $geotagCacheKey = "fresns_api_list_{$geotagCrc32}_geotag_ids";

            $geotagExplodeArr = CacheHelper::get($geotagCacheKey, 'fresnsConfigs');

            if (empty($geotagExplodeArr)) {
                $geotagExplodeArr = PermissionUtility::getPrimaryIdArr('geotag', $dtoRequest->geotags);

                CacheHelper::put($geotagExplodeArr, $geotagCacheKey, 'fresnsConfigs');
            }

            if ($geotagExplodeArr['idCount'] == 0) {
                return $this->warning(37302);
            }

            $postQuery->whereIn('geotag_id', $geotagExplodeArr['idArr']);
        }

        // other conditions
        if ($dtoRequest->allDigest) {
            $postQuery->whereNot('digest_state', Post::DIGEST_NO);
        } else {
            $postQuery->when($dtoRequest->digestState, function ($query, $value) {
                $query->where('digest_state', $value);
            });
        }

        $postQuery->when($dtoRequest->stickyState, function ($query, $value) {
            $query->where('sticky_state', $value);
        });

        if ($dtoRequest->createdDays || $dtoRequest->createdDate) {
            switch ($dtoRequest->createdDate) {
                case 'today':
                    $postQuery->whereDate('created_at', now()->format('Y-m-d'));
                    break;

                case 'yesterday':
                    $postQuery->whereDate('created_at', now()->subDay()->format('Y-m-d'));
                    break;

                case 'week':
                    $postQuery->whereDate('created_at', '>=', now()->startOfWeek()->format('Y-m-d'))
                        ->whereDate('created_at', '<=', now()->endOfWeek()->format('Y-m-d'));
                    break;

                case 'lastWeek':
                    $postQuery->whereDate('created_at', '>=', now()->subWeek()->startOfWeek()->format('Y-m-d'))
                        ->whereDate('created_at', '<=', now()->subWeek()->endOfWeek()->format('Y-m-d'));
                    break;

                case 'month':
                    $postQuery->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                    break;

                case 'lastMonth':
                    $lastMonth = now()->subMonth()->month;
                    $year = now()->year;
                    if ($lastMonth == 12) {
                        $year = now()->subYear()->year;
                    }
                    $postQuery->whereMonth('created_at', $lastMonth)->whereYear('created_at', $year);
                    break;

                case 'year':
                    $postQuery->whereYear('created_at', now()->year);
                    break;

                case 'lastYear':
                    $postQuery->whereYear('created_at', now()->subYear()->year);
                    break;

                default:
                    $postQuery->whereDate('created_at', '>=', now()->subDays($dtoRequest->createdDays ?? 1)->format('Y-m-d'));
            }
        } else {
            $postQuery->when($dtoRequest->createdDateGt, function ($query, $value) {
                $query->whereDate('created_at', '>=', $value);
            });

            $postQuery->when($dtoRequest->createdDateLt, function ($query, $value) {
                $query->whereDate('created_at', '<=', $value);
            });
        }

        $postQuery->when($dtoRequest->viewCountGt, function ($query, $value) {
            $query->where('view_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->viewCountLt, function ($query, $value) {
            $query->where('view_count', '<=', $value);
        });

        $postQuery->when($dtoRequest->likeCountGt, function ($query, $value) {
            $query->where('like_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->likeCountLt, function ($query, $value) {
            $query->where('like_count', '<=', $value);
        });

        $postQuery->when($dtoRequest->dislikeCountGt, function ($query, $value) {
            $query->where('dislike_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->dislikeCountLt, function ($query, $value) {
            $query->where('dislike_count', '<=', $value);
        });

        $postQuery->when($dtoRequest->followCountGt, function ($query, $value) {
            $query->where('follow_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->followCountLt, function ($query, $value) {
            $query->where('follow_count', '<=', $value);
        });

        $postQuery->when($dtoRequest->blockCountGt, function ($query, $value) {
            $query->where('block_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->blockCountLt, function ($query, $value) {
            $query->where('block_count', '<=', $value);
        });

        $postQuery->when($dtoRequest->commentCountGt, function ($query, $value) {
            $query->where('comment_count', '>=', $value);
        });

        $postQuery->when($dtoRequest->commentCountLt, function ($query, $value) {
            $query->where('comment_count', '<=', $value);
        });

        // since post
        $postQuery->when($dtoRequest->sincePid, function ($query, $value) {
            $sincePostId = PrimaryHelper::fresnsPrimaryId('post', $value);

            $query->where('id', '>', $sincePostId);
        });

        // before post
        $postQuery->when($dtoRequest->beforePid, function ($query, $value) {
            $beforePostId = PrimaryHelper::fresnsPrimaryId('post', $value);

            $query->where('id', '<', $beforePostId);
        });

        // lang tag
        $postQuery->when($dtoRequest->langTag, function ($query, $value) {
            $query->where('lang_tag', $value);
        });

        // content type
        if ($dtoRequest->contentType && $dtoRequest->contentType != 'All') {
            // file
            $fileTypeNumber = FileHelper::fresnsFileTypeNumber($dtoRequest->contentType);

            $postQuery->when($fileTypeNumber, function ($query, $value) {
                $query->whereRelation('fileUsages', 'file_type', $value);
            });

            // text
            if ($dtoRequest->contentType == 'Text') {
                $postQuery->doesntHave('fileUsages')->doesntHave('extendUsages');
            } elseif (empty($fileTypeNumber)) {
                $postQuery->whereRelation('extendUsages', 'app_fskey', $dtoRequest->contentType);
            }
        }

        // datetime limit
        $dateLimit = $groupDateLimit ?? ContentService::getContentDateLimit($authUserId, $authUser?->expired_at);
        $postQuery->when($dateLimit, function ($query, $value) {
            $query->where('created_at', '<=', $value);
        });

        // order
        if ($dtoRequest->orderType == 'random') {
            $postQuery->inRandomOrder();
        } else {
            $orderType = match ($dtoRequest->orderType) {
                'createdTime' => 'created_at',
                'commentTime' => 'last_comment_at',
                'view' => 'view_count',
                'like' => 'like_count',
                'dislike' => 'dislike_count',
                'follow' => 'follow_count',
                'block' => 'block_count',
                'comment' => 'comment_count',
                default => 'created_at',
            };

            $orderDirection = match ($dtoRequest->orderDirection) {
                'asc' => 'asc',
                'desc' => 'desc',
                default => 'desc',
            };

            if ($dtoRequest->orderType == 'commentTime') {
                $postQuery->orderBy(DB::raw('COALESCE(last_comment_at, created_at)'), $orderDirection);
            } else {
                $postQuery->orderBy($orderType, $orderDirection);
            }
        }

        $posts = $postQuery->paginate($dtoRequest->pageSize ?? 15);

        if (empty($authUserId)) {
            CacheHelper::put($posts, $cacheKey, 'fresnsList', 5);
        }

        $postList = [];
        foreach ($posts as $post) {
            $postList[] = DetailUtility::postDetail($post, $langTag, $timezone, $authUserId, $postOptions);
        }

        return $this->fresnsPaginate($postList, $posts->total(), $posts->perPage());
    }

    // detail
    public function detail(string $pid, Request $request)
    {
        $dtoRequest = new PostDetailDTO($request->all());

        // Plugin provides data
        $dataPluginFskey = ConfigHelper::fresnsConfigByItemKey('post_detail_service');

        if ($dataPluginFskey) {
            $wordBody = [
                'headers' => AppHelper::getHeaders(),
                'body' => $request->all(),
                'fsid' => $pid,
            ];

            $fresnsResp = \FresnsCmdWord::plugin($dataPluginFskey)->getPostDetail($wordBody);

            return $fresnsResp->getOrigin();
        }

        // Fresns provides data
        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $post = Post::with(['author'])->where('pid', $pid)->first();

        if (empty($post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($post->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $post->is_enabled && $post->user_id != $authUser?->id) {
            throw new ResponseException(37401);
        }

        ContentService::checkUserContentViewPerm($post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($post->created_at, $post->group_id, $authUser?->id);

        $seoData = PrimaryHelper::fresnsModelSeo(Seo::TYPE_POST, $post->id);

        $item['title'] = StrHelper::languageContent($seoData?->title, $langTag);
        $item['keywords'] = StrHelper::languageContent($seoData?->keywords, $langTag);
        $item['description'] = StrHelper::languageContent($seoData?->description, $langTag);

        $postOptions = [
            'viewType' => 'detail',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'location' => [
                'mapId' => $dtoRequest->mapId,
                'longitude' => $dtoRequest->mapLng,
                'latitude' => $dtoRequest->mapLat,
            ],
            'checkPermissions' => true,
            'isPreviewLikeUsers' => true,
            'isPreviewComments' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterGroup' => [
                'type' => $dtoRequest->filterGroupType,
                'keys' => $dtoRequest->filterGroupKeys,
            ],
            'filterHashtag' => [
                'type' => $dtoRequest->filterHashtagType,
                'keys' => $dtoRequest->filterHashtagKeys,
            ],
            'filterGeotag' => [
                'type' => $dtoRequest->filterGeotagType,
                'keys' => $dtoRequest->filterGeotagKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
            'filterQuotedPost' => [
                'type' => $dtoRequest->filterQuotedPostType,
                'keys' => $dtoRequest->filterQuotedPostKeys,
            ],
            'filterPreviewLikeUser' => [
                'type' => $dtoRequest->filterPreviewLikeUserType,
                'keys' => $dtoRequest->filterPreviewLikeUserKeys,
            ],
            'filterPreviewComment' => [
                'type' => $dtoRequest->filterPreviewCommentType,
                'keys' => $dtoRequest->filterPreviewCommentKeys,
            ],
        ];

        $data = [
            'items' => $item,
            'detail' => DetailUtility::postDetail($post, $langTag, $timezone, $authUser?->id, $postOptions),
        ];

        return $this->success($data);
    }

    // interaction
    public function interaction(string $pid, string $type, Request $request)
    {
        $requestData = $request->all();
        $requestData['type'] = $type;
        $dtoRequest = new InteractionDTO($requestData);

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $post = PrimaryHelper::fresnsModelByFsid('post', $pid);

        if (empty($post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($post->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $post->is_enabled && $post->user_id != $authUser?->id) {
            throw new ResponseException(37401);
        }

        InteractionService::checkInteractionSetting('post', $dtoRequest->type);

        ContentService::checkUserContentViewPerm($post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($post->created_at, $post->group_id, $authUser?->id);

        $orderDirection = $dtoRequest->orderDirection ?: 'desc';

        $service = new InteractionService();
        $data = $service->getUsersWhoMarkIt($dtoRequest->type, InteractionService::TYPE_POST, $post->id, $orderDirection, $langTag, $timezone, $authUser?->id);

        return $this->fresnsPaginate($data['paginateData'], $data['interactionData']->total(), $data['interactionData']->perPage());
    }

    // users
    public function users(string $pid, Request $request)
    {
        $dtoRequest = new PaginationDTO($request->all());

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $post = PrimaryHelper::fresnsModelByFsid('post', $pid);

        if (empty($post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($post->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $post->is_enabled && $post->user_id != $authUser?->id) {
            throw new ResponseException(37401);
        }

        ContentService::checkUserContentViewPerm($post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($post->created_at, $post->group_id, $authUser?->id);

        $userListData = PostUser::with('user')->where('post_id', $post->id)->latest()->paginate($dtoRequest->pageSize ?? 15);

        $userOptions = [
            'viewType' => 'list',
            'isLiveStats' => false,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
        ];

        $userList = [];
        foreach ($userListData as $postUser) {
            $userList[] = DetailUtility::userDetail($postUser->user, $langTag, $timezone, $authUser?->id, $userOptions);
        }

        return $this->fresnsPaginate($userList, $userListData->total(), $userListData->perPage());
    }

    // quotes
    public function quotes(string $pid, Request $request)
    {
        $dtoRequest = new PostQuotesDTO($request->all());

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();
        $authUserId = $authUser?->id;

        $post = PrimaryHelper::fresnsModelByFsid('post', $pid);

        if (empty($post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($post->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $post->is_enabled && $post->user_id != $authUserId) {
            throw new ResponseException(37401);
        }

        ContentService::checkUserContentViewPerm($post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($post->created_at, $post->group_id, $authUser?->id);

        // query
        $postQuery = Post::query();

        // has author
        $postQuery->whereRelation('author', 'is_enabled', true);

        // filter
        $filterGroupIds = PermissionUtility::getGroupContentFilterIdArr($authUserId);

        if (empty($authUserId)) {
            $postQuery->where('is_enabled', true);
        } else {
            $postQuery->where(function ($query) use ($authUserId) {
                $query->where('is_enabled', true)->orWhere(function ($query) use ($authUserId) {
                    $query->where('is_enabled', false)->where('user_id', $authUserId);
                });
            });

            $blockGroupIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GROUP, $authUserId);

            $filterUserIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_USER, $authUserId);
            $filterGroupIds = array_unique(array_merge($filterGroupIds, $blockGroupIds));
            $filterHashtagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_HASHTAG, $authUserId);
            $filterGeotagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GEOTAG, $authUserId);
            $filterPostIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_POST, $authUserId);

            $postQuery->when($filterUserIds, function ($query, $value) {
                $query->whereNotIn('user_id', $value);
            });

            $postQuery->when($filterHashtagIds, function ($query, $value) {
                $query->where(function ($postQuery) use ($value) {
                    $postQuery->whereDoesntHave('hashtagUsages')->orWhereHas('hashtagUsages', function ($query) use ($value) {
                        $query->whereNotIn('hashtag_id', $value);
                    });
                });
            });

            $postQuery->when($filterGeotagIds, function ($query, $value) {
                $query->whereNotIn('geotag_id', $value);
            });

            $postQuery->when($filterPostIds, function ($query, $value) {
                $query->whereNotIn('id', $value);
            });
        }

        $postQuery->when($filterGroupIds, function ($query, $value) {
            $query->whereNotIn('group_id', $value);
        });

        // datetime limit
        $dateLimit = ContentService::getContentDateLimit($authUserId, $authUser?->expired_at);
        $postQuery->when($dateLimit, function ($query, $value) {
            $query->where('created_at', '<=', $value);
        });

        $postListData = $postQuery->latest()->paginate($dtoRequest->pageSize ?? 15);

        $postOptions = [
            'viewType' => 'list',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'location' => [
                'mapId' => $dtoRequest->mapId,
                'longitude' => $dtoRequest->mapLng,
                'latitude' => $dtoRequest->mapLat,
            ],
            'checkPermissions' => true,
            'isPreviewLikeUsers' => true,
            'isPreviewComments' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterGroup' => [
                'type' => $dtoRequest->filterGroupType,
                'keys' => $dtoRequest->filterGroupKeys,
            ],
            'filterHashtag' => [
                'type' => $dtoRequest->filterHashtagType,
                'keys' => $dtoRequest->filterHashtagKeys,
            ],
            'filterGeotag' => [
                'type' => $dtoRequest->filterGeotagType,
                'keys' => $dtoRequest->filterGeotagKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
            'filterQuotedPost' => [
                'type' => $dtoRequest->filterQuotedPostType,
                'keys' => $dtoRequest->filterQuotedPostKeys,
            ],
            'filterPreviewLikeUser' => [
                'type' => $dtoRequest->filterPreviewLikeUserType,
                'keys' => $dtoRequest->filterPreviewLikeUserKeys,
            ],
            'filterPreviewComment' => [
                'type' => $dtoRequest->filterPreviewCommentType,
                'keys' => $dtoRequest->filterPreviewCommentKeys,
            ],
        ];

        $postList = [];
        foreach ($postListData as $post) {
            $postList[] = DetailUtility::postDetail($post, $langTag, $timezone, $authUserId, $postOptions);
        }

        return $this->fresnsPaginate($postList, $postListData->total(), $postListData->perPage());
    }

    // delete
    public function delete(string $pid)
    {
        $post = Post::where('pid', $pid)->first();

        if (empty($post)) {
            throw new ResponseException(36400);
        }

        $authUser = $this->user();

        if ($post->user_id != $authUser->id) {
            throw new ResponseException(36403);
        }

        $canDelete = PermissionUtility::checkContentIsCanDelete('post', $post->digest_state, $post->sticky_state);

        $permissions = $post->permissions;
        $canDeleteConfig = $permissions['canDelete'] ?? true;

        if (! $canDeleteConfig || ! $canDelete) {
            throw new ResponseException(36401);
        }

        InteractionUtility::publishStats('post', $post->id, 'decrement');

        PostLog::where('post_id', $post->id)->delete();

        // session log
        $sessionLog = [
            'type' => SessionLog::TYPE_POST_DELETE,
            'fskey' => 'Fresns',
            'appId' => $this->appId(),
            'platformId' => $this->platformId(),
            'version' => $this->version(),
            'langTag' => $this->langTag(),
            'aid' => $this->account()->aid,
            'uid' => $authUser->uid,
            'actionName' => \request()->path(),
            'actionDesc' => 'Post Delete',
            'actionState' => SessionLog::STATE_SUCCESS,
            'actionId' => $post->id,
            'deviceInfo' => $this->deviceInfo(),
            'deviceToken' => null,
            'loginToken' => null,
            'moreInfo' => null,
        ];
        // create session log
        \FresnsCmdWord::plugin('Fresns')->createSessionLog($sessionLog);

        $post->delete();

        return $this->success();
    }

    // histories
    public function histories(string $pid, Request $request)
    {
        $dtoRequest = new HistoryDTO($request->all());

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $post = PrimaryHelper::fresnsModelByFsid('post', $pid);

        if (empty($post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($post->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $post->is_enabled && $post->user_id != $authUser?->id) {
            throw new ResponseException(37401);
        }

        ContentService::checkUserContentViewPerm($post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($post->created_at, $post->group_id, $authUser?->id);

        $historyQuery = PostLog::where('post_id', $post->id)->where('state', PostLog::STATE_SUCCESS)->latest();

        // has author
        $historyQuery->whereRelation('author', 'is_enabled', true);

        $histories = $historyQuery->paginate($dtoRequest->pageSize ?? 15);

        $historyOptions = [
            'viewType' => 'list',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'checkPermissions' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
        ];

        $historyList = [];
        foreach ($histories as $history) {
            $historyList[] = DetailUtility::postHistoryDetail($history, $langTag, $timezone, $authUser?->id, $historyOptions);
        }

        return $this->fresnsPaginate($historyList, $histories->total(), $histories->perPage());
    }

    // historyDetail
    public function historyDetail(string $hpid, Request $request)
    {
        $dtoRequest = new HistoryDTO($request->all());

        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $postLog = PostLog::with(['author', 'post'])->where('hpid', $hpid)->where('state', PostLog::STATE_SUCCESS)->first();

        // check log
        if (empty($postLog)) {
            throw new ResponseException(37402);
        }

        // check is enabled
        if (! $postLog->is_enabled && $postLog->user_id != $authUser?->id) {
            throw new ResponseException(37403);
        }

        // check post
        if (empty($postLog->post)) {
            throw new ResponseException(37400);
        }

        // check author
        if (empty($postLog->author)) {
            throw new ResponseException(35203);
        }

        // check is enabled
        if (! $postLog->post->is_enabled && $postLog->post->user_id != $authUser?->id) {
            throw new ResponseException(37401);
        }

        ContentService::checkUserContentViewPerm($postLog->post->created_at, $authUser?->id, $authUser?->expired_at);

        ContentService::checkGroupContentViewPerm($postLog->post->created_at, $postLog->post->group_id, $authUser?->id);

        $historyOptions = [
            'viewType' => 'detail',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'checkPermissions' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
        ];

        $data['detail'] = DetailUtility::postHistoryDetail($postLog, $langTag, $timezone, $authUser?->id, $historyOptions);

        return $this->success($data);
    }

    // timelines
    public function timelines(Request $request)
    {
        $dtoRequest = new PostTimelinesDTO($request->all());

        // Plugin provides data
        $dataPluginFskey = ConfigHelper::fresnsConfigByItemKey('post_timelines_service');

        if ($dataPluginFskey) {
            $wordBody = [
                'headers' => AppHelper::getHeaders(),
                'body' => $request->all(),
            ];

            $fresnsResp = \FresnsCmdWord::plugin($dataPluginFskey)->getPostsByTimelines($wordBody);

            return $fresnsResp->getOrigin();
        }

        // Fresns provides data
        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();

        $timelineService = new TimelineService();
        $timelineOptions = [
            'langTag' => $dtoRequest->langTag,
            'contentType' => $dtoRequest->contentType,
            'sincePid' => $dtoRequest->sincePid,
            'beforePid' => $dtoRequest->beforePid,
            'dateLimit' => ContentService::getContentDateLimit($authUser->id, $authUser->expired_at),
        ];

        $followType = null;
        switch ($dtoRequest->type) {
            case 'user':
                $followType = 'user';
                $posts = $timelineService->getPostListByFollowUsers($authUser->id, $timelineOptions);
                break;

            case 'group':
                $followType = 'group';
                $posts = $timelineService->getPostListByFollowGroups($authUser->id, $timelineOptions);
                break;

            case 'hashtag':
                $followType = 'hashtag';
                $posts = $timelineService->getPostListByFollowHashtags($authUser->id, $timelineOptions);
                break;

            case 'geotag':
                $followType = 'geotag';
                $posts = $timelineService->getPostListByFollowGeotags($authUser->id, $timelineOptions);
                break;

            default:
                $followType = 'all';
                $posts = $timelineService->getPostListByFollowAll($authUser->id, $timelineOptions);
        }

        $postOptions = [
            'viewType' => 'list',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'location' => [
                'mapId' => $dtoRequest->mapId,
                'longitude' => $dtoRequest->mapLng,
                'latitude' => $dtoRequest->mapLat,
            ],
            'checkPermissions' => true,
            'isPreviewLikeUsers' => true,
            'isPreviewComments' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterGroup' => [
                'type' => $dtoRequest->filterGroupType,
                'keys' => $dtoRequest->filterGroupKeys,
            ],
            'filterHashtag' => [
                'type' => $dtoRequest->filterHashtagType,
                'keys' => $dtoRequest->filterHashtagKeys,
            ],
            'filterGeotag' => [
                'type' => $dtoRequest->filterGeotagType,
                'keys' => $dtoRequest->filterGeotagKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
            'filterQuotedPost' => [
                'type' => $dtoRequest->filterQuotedPostType,
                'keys' => $dtoRequest->filterQuotedPostKeys,
            ],
            'filterPreviewLikeUser' => [
                'type' => $dtoRequest->filterPreviewLikeUserType,
                'keys' => $dtoRequest->filterPreviewLikeUserKeys,
            ],
            'filterPreviewComment' => [
                'type' => $dtoRequest->filterPreviewCommentType,
                'keys' => $dtoRequest->filterPreviewCommentKeys,
            ],
        ];

        $postList = [];
        foreach ($posts as $post) {
            $item = DetailUtility::postDetail($post, $langTag, $timezone, $authUser->id, $postOptions);

            $item['contentSource'] = InteractionUtility::getTimelineContentSource($followType, $post->user_id, $post->digest_state, $authUser->id, $post->group_id, $post->geotag_id);

            $postList[] = $item;
        }

        return $this->fresnsPaginate($postList, $posts->total(), $posts->perPage());
    }

    // nearby
    public function nearby(Request $request)
    {
        $dtoRequest = new PostNearbyDTO($request->all());

        // Plugin provides data
        $dataPluginFskey = ConfigHelper::fresnsConfigByItemKey('post_nearby_service');

        if ($dataPluginFskey) {
            $wordBody = [
                'headers' => AppHelper::getHeaders(),
                'body' => $request->all(),
            ];

            $fresnsResp = \FresnsCmdWord::plugin($dataPluginFskey)->getPostsByNearby($wordBody);

            return $fresnsResp->getOrigin();
        }

        // Fresns provides data
        $langTag = $this->langTag();
        $timezone = $this->timezone();
        $authUser = $this->user();
        $authUserId = $authUser?->id;

        $postQuery = Post::query();

        // has author
        $postQuery->whereRelation('author', 'is_enabled', true);

        // has geotag
        $postQuery->whereNot('geotag_id', 0);

        // block
        $filterGroupIds = PermissionUtility::getGroupContentFilterIdArr($authUserId);

        if (empty($authUserId)) {
            $postQuery->where('is_enabled', true);
        } else {
            $postQuery->where(function ($query) use ($authUserId) {
                $query->where('is_enabled', true)->orWhere(function ($query) use ($authUserId) {
                    $query->where('is_enabled', false)->where('user_id', $authUserId);
                });
            });

            $blockUserIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_USER, $authUserId);
            $blockGroupIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GROUP, $authUserId);
            $blockHashtagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_HASHTAG, $authUserId);
            $blockGeotagIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_GEOTAG, $authUserId);
            $blockPostIds = InteractionUtility::getBlockIdArr(InteractionUtility::TYPE_POST, $authUserId);

            $postQuery->when($blockUserIds, function ($query, $value) {
                $query->whereNotIn('user_id', $value);
            });

            $postQuery->when($blockHashtagIds, function ($query, $value) {
                $query->where(function ($postQuery) use ($value) {
                    $postQuery->whereDoesntHave('hashtagUsages')->orWhereHas('hashtagUsages', function ($query) use ($value) {
                        $query->whereNotIn('hashtag_id', $value);
                    });
                });
            });

            $postQuery->when($blockGeotagIds, function ($query, $value) {
                $query->whereNotIn('geotag_id', $value);
            });

            $postQuery->when($blockPostIds, function ($query, $value) {
                $query->whereNotIn('id', $value);
            });

            $filterGroupIds = array_unique(array_merge($filterGroupIds, $blockGroupIds));
        }

        $postQuery->when($filterGroupIds, function ($query, $value) {
            $query->whereNotIn('group_id', $value);
        });

        // nearby
        $nearbyConfig = ConfigHelper::fresnsConfigByItemKeys([
            'nearby_length_km',
            'nearby_length_mi',
        ]);

        $unit = $dtoRequest->unit ?? ConfigHelper::fresnsConfigLengthUnit($langTag);
        $length = $dtoRequest->length ?? $nearbyConfig["nearby_length_{$unit}"];

        $nearbyLength = match ($unit) {
            'km' => $length,
            'mi' => $length * 0.6214,
            default => $length,
        };
        $distance = $nearbyLength * 1000;

        $mapLng = $dtoRequest->mapLng;
        $mapLat = $dtoRequest->mapLat;

        switch (config('database.default')) {
            case 'sqlite':
                $postQuery->whereHas('geotag', function ($query) use ($mapLng, $mapLat, $distance) {
                    $query->select(DB::raw("*, ( 6371 * acos( cos( radians($mapLat) ) * cos( radians( map_latitude ) ) * cos( radians( map_longitude ) - radians($mapLng) ) + sin( radians($mapLat) ) * sin( radians( map_latitude ) ) ) ) AS distance"))
                        ->having('distance', '<=', $distance)
                        ->orderBy('distance');
                });
                break;

            case 'mysql':
                $mysqlVersion = DB::select('SELECT VERSION() as version')[0]->version;

                $postQuery->whereHas('geotag', function ($query) use ($mapLng, $mapLat, $distance, $mysqlVersion) {
                    if (version_compare($mysqlVersion, '8.0.0', '>=')) {
                        $query->whereRaw("ST_Distance_Sphere(map_location, ST_GeomFromText('POINT($mapLat $mapLng)', 4326)) <= {$distance}"); // MySQL 8
                    } else {
                        $query->whereRaw("ST_Distance_Sphere(map_location, ST_GeomFromText('POINT($mapLng $mapLat)', 4326)) <= {$distance}"); // MySQL 5
                    }
                });
                break;

            case 'mariadb':
                $postQuery->whereHas('geotag', function ($query) use ($mapLng, $mapLat, $distance) {
                    $query->whereRaw("ST_Distance_Sphere(map_location, ST_GeomFromText('POINT($mapLng $mapLat)', 4326)) <= {$distance}");
                });
                break;

            case 'pgsql':
                // use PostGIS
                $postQuery->whereHas('geotag', function ($query) use ($mapLng, $mapLat, $distance) {
                    $query->whereRaw("ST_DWithin(map_location::geography, ST_SetSRID(ST_MakePoint($mapLng, $mapLat), 4326)::geography, {$distance})");
                });
                break;

            case 'sqlsrv':
                $postQuery->whereHas('geotag', function ($query) use ($mapLng, $mapLat, $distance) {
                    $query->whereRaw("map_location.STDistance(geography::Point($mapLat, $mapLng, 4326)) <= {$distance}");
                });
                break;

            default:
                throw new ResponseException(32303);
        }

        // lang tag
        $postQuery->when($dtoRequest->langTag, function ($query, $value) {
            $query->where('lang_tag', $value);
        });

        // content type
        if ($dtoRequest->contentType && $dtoRequest->contentType != 'All') {
            // file
            $fileTypeNumber = FileHelper::fresnsFileTypeNumber($dtoRequest->contentType);

            $postQuery->when($fileTypeNumber, function ($query, $value) {
                $query->whereRelation('fileUsages', 'file_type', $value);
            });

            // text
            if ($dtoRequest->contentType == 'Text') {
                $postQuery->doesntHave('fileUsages')->doesntHave('extendUsages');
            } elseif (empty($fileTypeNumber)) {
                $postQuery->whereRelation('extendUsages', 'app_fskey', $dtoRequest->contentType);
            }
        }

        // datetime limit
        $dateLimit = ContentService::getContentDateLimit($authUserId, $authUser?->expired_at);
        $postQuery->when($dateLimit, function ($query, $value) {
            $query->where('created_at', '<=', $value);
        });

        $posts = $postQuery->paginate($dtoRequest->pageSize ?? 15);

        $postOptions = [
            'viewType' => 'list',
            'contentFormat' => \request()->header('X-Fresns-Client-Content-Format'),
            'location' => [
                'mapId' => $dtoRequest->mapId,
                'longitude' => $dtoRequest->mapLng,
                'latitude' => $dtoRequest->mapLat,
            ],
            'checkPermissions' => true,
            'isPreviewLikeUsers' => true,
            'isPreviewComments' => true,
            'filter' => [
                'type' => $dtoRequest->filterType,
                'keys' => $dtoRequest->filterKeys,
            ],
            'filterGroup' => [
                'type' => $dtoRequest->filterGroupType,
                'keys' => $dtoRequest->filterGroupKeys,
            ],
            'filterHashtag' => [
                'type' => $dtoRequest->filterHashtagType,
                'keys' => $dtoRequest->filterHashtagKeys,
            ],
            'filterGeotag' => [
                'type' => $dtoRequest->filterGeotagType,
                'keys' => $dtoRequest->filterGeotagKeys,
            ],
            'filterAuthor' => [
                'type' => $dtoRequest->filterAuthorType,
                'keys' => $dtoRequest->filterAuthorKeys,
            ],
            'filterQuotedPost' => [
                'type' => $dtoRequest->filterQuotedPostType,
                'keys' => $dtoRequest->filterQuotedPostKeys,
            ],
            'filterPreviewLikeUser' => [
                'type' => $dtoRequest->filterPreviewLikeUserType,
                'keys' => $dtoRequest->filterPreviewLikeUserKeys,
            ],
            'filterPreviewComment' => [
                'type' => $dtoRequest->filterPreviewCommentType,
                'keys' => $dtoRequest->filterPreviewCommentKeys,
            ],
        ];

        $postList = [];
        foreach ($posts as $post) {
            $postList[] = DetailUtility::postDetail($post, $langTag, $timezone, $authUser?->id, $postOptions);
        }

        return $this->fresnsPaginate($postList, $posts->total(), $posts->perPage());
    }
}
