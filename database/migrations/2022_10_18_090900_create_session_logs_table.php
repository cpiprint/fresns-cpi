<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionLogsTable extends Migration
{
    /**
     * Run fresns migrations.
     */
    public function up(): void
    {
        Schema::create('session_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('plugin_unikey', 64)->default('Fresns');
            $table->unsignedTinyInteger('type')->default(1);
            $table->unsignedTinyInteger('platform_id');
            $table->string('version', 16);
            $table->string('app_id', 8)->nullable();
            $table->string('lang_tag', 16)->nullable();
            $table->string('object_name', 128);
            $table->string('object_action', 128)->nullable();
            $table->unsignedTinyInteger('object_result');
            $table->unsignedBigInteger('object_order_id')->nullable();
            $table->json('device_info')->nullable();
            $table->string('device_token', 128)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('more_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse fresns migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
}
