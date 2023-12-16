<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

namespace App\Fresns\Panel\Http\Controllers;

use App\Helpers\StrHelper;
use App\Models\Config;

class InteractionController extends Controller
{
    public function show()
    {
        // config keys
        $configKeys = [
            'user_like_enabled',
            'user_like_name',
            'user_like_user_title',
            'user_like_public_record',
            'user_like_public_count',
            'user_dislike_enabled',
            'user_dislike_name',
            'user_dislike_user_title',
            'user_dislike_public_record',
            'user_dislike_public_count',
            'user_follow_enabled',
            'user_follow_name',
            'user_follow_user_title',
            'user_follow_public_record',
            'user_follow_public_count',
            'user_block_enabled',
            'user_block_name',
            'user_block_user_title',
            'user_block_public_record',
            'user_block_public_count',
            'group_like_enabled',
            'group_like_name',
            'group_like_user_title',
            'group_like_public_record',
            'group_like_public_count',
            'group_dislike_enabled',
            'group_dislike_name',
            'group_dislike_user_title',
            'group_dislike_public_record',
            'group_dislike_public_count',
            'group_follow_enabled',
            'group_follow_name',
            'group_follow_user_title',
            'group_follow_public_record',
            'group_follow_public_count',
            'group_block_enabled',
            'group_block_name',
            'group_block_user_title',
            'group_block_public_record',
            'group_block_public_count',
            'hashtag_like_enabled',
            'hashtag_like_name',
            'hashtag_like_user_title',
            'hashtag_like_public_record',
            'hashtag_like_public_count',
            'hashtag_dislike_enabled',
            'hashtag_dislike_name',
            'hashtag_dislike_user_title',
            'hashtag_dislike_public_record',
            'hashtag_dislike_public_count',
            'hashtag_follow_enabled',
            'hashtag_follow_name',
            'hashtag_follow_user_title',
            'hashtag_follow_public_record',
            'hashtag_follow_public_count',
            'hashtag_block_enabled',
            'hashtag_block_name',
            'hashtag_block_user_title',
            'hashtag_block_public_record',
            'hashtag_block_public_count',
            'geotag_like_enabled',
            'geotag_like_name',
            'geotag_like_user_title',
            'geotag_like_public_record',
            'geotag_like_public_count',
            'geotag_dislike_enabled',
            'geotag_dislike_name',
            'geotag_dislike_user_title',
            'geotag_dislike_public_record',
            'geotag_dislike_public_count',
            'geotag_follow_enabled',
            'geotag_follow_name',
            'geotag_follow_user_title',
            'geotag_follow_public_record',
            'geotag_follow_public_count',
            'geotag_block_enabled',
            'geotag_block_name',
            'geotag_block_user_title',
            'geotag_block_public_record',
            'geotag_block_public_count',
            'post_like_enabled',
            'post_like_name',
            'post_like_user_title',
            'post_like_public_record',
            'post_like_public_count',
            'post_dislike_enabled',
            'post_dislike_name',
            'post_dislike_user_title',
            'post_dislike_public_record',
            'post_dislike_public_count',
            'post_follow_enabled',
            'post_follow_name',
            'post_follow_user_title',
            'post_follow_public_record',
            'post_follow_public_count',
            'post_block_enabled',
            'post_block_name',
            'post_block_user_title',
            'post_block_public_record',
            'post_block_public_count',
            'comment_like_enabled',
            'comment_like_name',
            'comment_like_user_title',
            'comment_like_public_record',
            'comment_like_public_count',
            'comment_dislike_enabled',
            'comment_dislike_name',
            'comment_dislike_user_title',
            'comment_dislike_public_record',
            'comment_dislike_public_count',
            'comment_follow_enabled',
            'comment_follow_name',
            'comment_follow_user_title',
            'comment_follow_public_record',
            'comment_follow_public_count',
            'comment_block_enabled',
            'comment_block_name',
            'comment_block_user_title',
            'comment_block_public_record',
            'comment_block_public_count',
            'post_name',
            'comment_name',
            'profile_posts_enabled',
            'profile_comments_enabled',
            'profile_likes_name',
            'profile_dislikes_name',
            'profile_followers_name',
            'profile_blockers_name',
            'profile_followers_you_follow_enabled',
            'profile_followers_you_follow_name',
            'profile_like_users_enabled',
            'profile_like_users_name',
            'profile_like_groups_enabled',
            'profile_like_groups_name',
            'profile_like_hashtags_enabled',
            'profile_like_hashtags_name',
            'profile_like_geotags_enabled',
            'profile_like_geotags_name',
            'profile_like_posts_enabled',
            'profile_like_posts_name',
            'profile_like_comments_enabled',
            'profile_like_comments_name',
            'profile_dislike_users_enabled',
            'profile_dislike_users_name',
            'profile_dislike_groups_enabled',
            'profile_dislike_groups_name',
            'profile_dislike_hashtags_enabled',
            'profile_dislike_hashtags_name',
            'profile_dislike_geotags_enabled',
            'profile_dislike_geotags_name',
            'profile_dislike_posts_enabled',
            'profile_dislike_posts_name',
            'profile_dislike_comments_enabled',
            'profile_dislike_comments_name',
            'profile_follow_users_enabled',
            'profile_follow_users_name',
            'profile_follow_groups_enabled',
            'profile_follow_groups_name',
            'profile_follow_hashtags_enabled',
            'profile_follow_hashtags_name',
            'profile_follow_geotags_enabled',
            'profile_follow_geotags_name',
            'profile_follow_posts_enabled',
            'profile_follow_posts_name',
            'profile_follow_comments_enabled',
            'profile_follow_comments_name',
            'profile_block_users_enabled',
            'profile_block_users_name',
            'profile_block_groups_enabled',
            'profile_block_groups_name',
            'profile_block_hashtags_enabled',
            'profile_block_hashtags_name',
            'profile_block_geotags_enabled',
            'profile_block_geotags_name',
            'profile_block_posts_enabled',
            'profile_block_posts_name',
            'profile_block_comments_enabled',
            'profile_block_comments_name',
        ];

        $configs = Config::whereIn('item_key', $configKeys)->get();

        foreach ($configs as $config) {
            $params[$config->item_key] = $config->item_value;
        }

        // language keys
        $langKeys = [
            'user_like_name',
            'user_like_user_title',
            'user_dislike_name',
            'user_dislike_user_title',
            'user_follow_name',
            'user_follow_user_title',
            'user_block_name',
            'user_block_user_title',
            'group_like_name',
            'group_like_user_title',
            'group_dislike_name',
            'group_dislike_user_title',
            'group_follow_name',
            'group_follow_user_title',
            'group_block_name',
            'group_block_user_title',
            'hashtag_like_name',
            'hashtag_like_user_title',
            'hashtag_dislike_name',
            'hashtag_dislike_user_title',
            'hashtag_follow_name',
            'hashtag_follow_user_title',
            'hashtag_block_name',
            'hashtag_block_user_title',
            'geotag_like_name',
            'geotag_like_user_title',
            'geotag_dislike_name',
            'geotag_dislike_user_title',
            'geotag_follow_name',
            'geotag_follow_user_title',
            'geotag_block_name',
            'geotag_block_user_title',
            'post_like_name',
            'post_like_user_title',
            'post_dislike_name',
            'post_dislike_user_title',
            'post_follow_name',
            'post_follow_user_title',
            'post_block_name',
            'post_block_user_title',
            'comment_like_name',
            'comment_like_user_title',
            'comment_dislike_name',
            'comment_dislike_user_title',
            'comment_follow_name',
            'comment_follow_user_title',
            'comment_block_name',
            'comment_block_user_title',
            'post_name',
            'comment_name',
            'profile_likes_name',
            'profile_dislikes_name',
            'profile_followers_name',
            'profile_blockers_name',
            'profile_followers_you_follow_name',
            'profile_like_users_name',
            'profile_like_groups_name',
            'profile_like_hashtags_name',
            'profile_like_geotags_name',
            'profile_like_posts_name',
            'profile_like_comments_name',
            'profile_dislike_users_name',
            'profile_dislike_groups_name',
            'profile_dislike_hashtags_name',
            'profile_dislike_geotags_name',
            'profile_dislike_posts_name',
            'profile_dislike_comments_name',
            'profile_follow_users_name',
            'profile_follow_groups_name',
            'profile_follow_hashtags_name',
            'profile_follow_geotags_name',
            'profile_follow_posts_name',
            'profile_follow_comments_name',
            'profile_block_users_name',
            'profile_block_groups_name',
            'profile_block_hashtags_name',
            'profile_block_geotags_name',
            'profile_block_posts_name',
            'profile_block_comments_name',
        ];

        $defaultLangParams = [];
        foreach ($langKeys as $langKey) {
            $defaultLangParams[$langKey] = StrHelper::languageContent($params[$langKey]);
        }

        return view('FsView::operations.interaction', compact('params', 'defaultLangParams'));
    }
}
