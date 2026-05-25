<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddLogLevelToEventsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('log_level')->nullable()->after('target_id');
        });

        DB::statement(
            "alter table events add constraint events_log_level_check check (log_level is null or log_level in ('trace', 'debug', 'info', 'warn', 'error', 'fatal'))"
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('alter table events drop constraint if exists events_log_level_check');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('log_level');
        });
    }
}
