<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
        });

        Schema::create('signup_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->uuid('tenant_id')->unique();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('tenant_entitlements', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('billing_status')->default('none');
            $table->timestamp('paid_through_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('kind')->default('legacy')->index();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'kind', 'status']);
        });
    }

    public function down()
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropIndex(['tenant_id', 'kind', 'status']);
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'status', 'expires_at', 'revoked_at', 'revoked_reason', 'created_by_user_id']);
        });

        Schema::dropIfExists('tenant_entitlements');
        Schema::dropIfExists('signup_verifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
