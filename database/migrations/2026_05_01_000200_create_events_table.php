<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('actor_type');
            $table->string('actor_id');
            $table->string('action');
            $table->string('target_type');
            $table->string('target_id');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'target_id', 'created_at']);
            $table->index(['tenant_id', 'actor_id', 'created_at']);
            $table->index(['tenant_id', 'actor_type', 'created_at']);
            $table->index(['tenant_id', 'target_type', 'created_at']);
        });

        DB::statement("create index events_metadata_type_index on events ((metadata->>'type'))");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('drop index if exists events_metadata_type_index');

        Schema::dropIfExists('events');
    }
}
