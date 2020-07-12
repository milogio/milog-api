<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteUserTable extends Migration
{
    private const TABLE = 'site_user';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigInteger('site_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('site_id')->references('id')->on('sites');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasTable(self::TABLE)) {
            Schema::table(self::TABLE, function(Blueprint $table) {
                $table->dropForeign('page_site_page_id_foreign');
                $table->dropForeign('page_site_site_id_foreign');
            });
            Schema::drop(self::TABLE);
        }
    }
}
