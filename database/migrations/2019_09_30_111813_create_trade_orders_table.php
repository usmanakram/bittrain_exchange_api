<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTradeOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->integer('currency_pair_id');
            $table->tinyInteger('direction')->comment = '0=sell, 1=buy';
            $table->double('quantity', 25, 8);
            $table->double('rate', 25, 8);
            // $table->double('fee', 25, 8)->nullable();
            // $table->integer('fee_currency_id')->nullable();
            $table->double('tradable_quantity', 25, 8);
            // $table->double('trigger_rate', 25, 8)->nullable();
            // $table->tinyInteger('type')->comment = '0=market, 1=limit, 2=stop_limit';
            $table->tinyInteger('type')->comment = '0=market, 1=limit';
            // $table->tinyInteger('status')->comment = '0=inactive, 1=active, 2=partially_executed, 3=executed, 4=canceled';
            $table->tinyInteger('status')->comment = '0=inactive, 1=active, 2=executed, 3=canceled';
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
        Schema::dropIfExists('trade_orders');
    }
}
