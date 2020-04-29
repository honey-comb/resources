<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreserveFieldToHcResource extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hc_resource', function (Blueprint $table) {
            $table->boolean('preserve')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hc_resource', function (Blueprint $table) {
            $table->dropColumn('preserve');
        });
    }
}
