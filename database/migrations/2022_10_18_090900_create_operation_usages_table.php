<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jarvis Tang
 * Released under the Apache-2.0 License.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperationUsagesTable extends Migration
{
    /**
     * Fresns migration file.
     */
    public function up(): void
    {
        Schema::create('operation_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('usage_type');
            $table->unsignedBigInteger('usage_id');
            $table->unsignedInteger('operation_id');
            $table->string('plugin_unikey', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->softDeletes();

            $table->index(['usage_type', 'usage_id'], 'usage_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('operation_usages');
    }
}
