<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdempotencyToEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable();
            $table->string('request_hash', 64)->nullable();

            $table->unique(['tenant_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
    }
}
