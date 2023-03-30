<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSessionKeysTable extends Migration
{
    /**
     * Run fresns migrations.
     */
    public function up(): void
    {
        Schema::create('session_keys', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('platform_id');
            $table->string('name', 64);
            $table->unsignedTinyInteger('type')->default(1);
            $table->string('plugin_unikey', 32)->nullable();
            $table->string('app_id', 8)->unique('app_id');
            $table->string('app_secret', 32);
            $table->unsignedTinyInteger('is_enable')->default(1);
            $table->text('remark')->nullable();
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
        Schema::dropIfExists('session_keys');
    }
}
