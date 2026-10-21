<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Cloud Server — Telegram Bot tables
 *
 * Stores users allowed to use the auto-panel-creation Telegram bot,
 * and a log of every panel/server created via the bot.
 */
return new class () extends Migration {
    public function up(): void
    {
        // Allowed Telegram users (who can use the "Create Panel" flow)
        if (!Schema::hasTable('telegram_bot_users')) {
            Schema::create('telegram_bot_users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('telegram_user_id')->unsigned()->unique();
                $table->string('telegram_username', 191)->nullable();
                $table->string('display_name', 191)->nullable();
                $table->boolean('is_blocked')->default(false);
                $table->integer('panels_created')->unsigned()->default(0);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['telegram_user_id', 'is_blocked']);
            });
        }

        // Log of every panel + server created via the bot
        if (!Schema::hasTable('telegram_panel_creations')) {
            Schema::create('telegram_panel_creations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('telegram_user_id')->unsigned()->index();
                $table->string('telegram_username', 191)->nullable();
                $table->integer('panel_user_id')->unsigned()->nullable();
                $table->string('panel_username', 191)->nullable();
                $table->integer('server_id')->unsigned()->nullable();
                $table->string('server_name', 191)->nullable();
                $table->integer('egg_id')->unsigned()->nullable();
                $table->string('egg_name', 191)->nullable();
                $table->integer('memory_mb')->unsigned()->default(0);
                $table->integer('disk_mb')->unsigned()->default(0);
                $table->string('status', 32)->default('created');
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['telegram_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_panel_creations');
        Schema::dropIfExists('telegram_bot_users');
    }
};
