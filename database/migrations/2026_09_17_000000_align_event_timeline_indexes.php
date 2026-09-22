<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlignEventTimelineIndexes extends Migration
{
    /**
     * Concurrent index changes cannot run inside a transaction.
     *
     * @var bool
     */
    public $withinTransaction = false;

    /**
     * Replace the original indexes with indexes matching timeline filters and ordering.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('create index concurrently events_timeline_index on events (tenant_id, occurred_at desc, created_at desc, id desc)');
        DB::statement('create index concurrently events_target_timeline_index on events (tenant_id, target_id, occurred_at desc, created_at desc, id desc)');
        DB::statement('create index concurrently events_actor_timeline_index on events (tenant_id, actor_id, occurred_at desc, created_at desc, id desc)');
        DB::statement('create index concurrently events_actor_type_timeline_index on events (tenant_id, actor_type, occurred_at desc, created_at desc, id desc)');
        DB::statement('create index concurrently events_target_type_timeline_index on events (tenant_id, target_type, occurred_at desc, created_at desc, id desc)');

        $this->dropOriginalIndexes();
    }

    /**
     * Restore the original indexes.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('create index concurrently events_tenant_id_created_at_index on events (tenant_id, created_at)');
        DB::statement('create index concurrently events_tenant_id_target_id_created_at_index on events (tenant_id, target_id, created_at)');
        DB::statement('create index concurrently events_tenant_id_actor_id_created_at_index on events (tenant_id, actor_id, created_at)');
        DB::statement('create index concurrently events_tenant_id_actor_type_created_at_index on events (tenant_id, actor_type, created_at)');
        DB::statement('create index concurrently events_tenant_id_target_type_created_at_index on events (tenant_id, target_type, created_at)');

        DB::statement('drop index concurrently if exists events_timeline_index');
        DB::statement('drop index concurrently if exists events_target_timeline_index');
        DB::statement('drop index concurrently if exists events_actor_timeline_index');
        DB::statement('drop index concurrently if exists events_actor_type_timeline_index');
        DB::statement('drop index concurrently if exists events_target_type_timeline_index');
    }

    /**
     * Drop indexes created by the original events migration.
     *
     * @return void
     */
    protected function dropOriginalIndexes()
    {
        DB::statement('drop index concurrently if exists events_tenant_id_created_at_index');
        DB::statement('drop index concurrently if exists events_tenant_id_target_id_created_at_index');
        DB::statement('drop index concurrently if exists events_tenant_id_actor_id_created_at_index');
        DB::statement('drop index concurrently if exists events_tenant_id_actor_type_created_at_index');
        DB::statement('drop index concurrently if exists events_tenant_id_target_type_created_at_index');
    }
}
