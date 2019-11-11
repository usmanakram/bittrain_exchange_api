<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTradeOrderConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_order_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('trade_order_id');
            $table->integer('associate_trade_order_id')->nullable();
            $table->double('lower_trigger_rate', 25, 8)->nullable(); // >=
            $table->double('upper_trigger_rate', 25, 8)->nullable(); // <=
            // $table->tinyInteger('type')->comment = '2=stop_limit, 3=oco_limit, 4=oco_stop_limit';
            // $table->tinyInteger('status')->comment = '0=pending, 1=executed, 2=canceled';
            $table->tinyInteger('status')->comment = '1=active, 2=executed, 3=canceled';
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
        Schema::dropIfExists('trade_order_conditions');
    }
}
