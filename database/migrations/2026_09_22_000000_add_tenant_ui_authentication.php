<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('auth_version')->default(1);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status')->default('active')->index();
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('user_id');
            $table->uuid('ui_token_family_id')->nullable()->after('tenant_id');
            $table->boolean('is_ui_token')->default(false)->after('ui_token_family_id');
            $table->unsignedInteger('auth_version')->nullable()->after('is_ui_token');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['user_id', 'is_ui_token', 'revoked']);
            $table->index(['ui_token_family_id', 'revoked']);
        });

        Schema::create('ui_refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->uuid('tenant_id');
            $table->string('access_token_id', 100);
            $table->unsignedInteger('auth_version');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('access_token_id')->references('id')->on('oauth_access_tokens')->cascadeOnDelete();
            $table->index(['family_id', 'revoked_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ui_refresh_tokens');

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['user_id', 'is_ui_token', 'revoked']);
            $table->dropIndex(['ui_token_family_id', 'revoked']);
            $table->dropColumn(['tenant_id', 'ui_token_family_id', 'is_ui_token', 'auth_version']);
        });

        Schema::dropIfExists('tenant_user');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'auth_version']);
        });
    }
};
