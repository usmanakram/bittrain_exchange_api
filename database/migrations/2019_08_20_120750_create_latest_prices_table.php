<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLatestPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('latest_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pair');
            $table->double('last_price', 25, 8);
            $table->double('volume', 25, 8);
            $table->float('price_change_percent');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('latest_prices');
    }
}
