<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTradeTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trade_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('buy_order_id');
            $table->integer('sell_order_id');
            $table->double('quantity', 25, 8);
            $table->double('rate', 25, 8);
            $table->double('buy_fee', 25, 8)->nullable();
            $table->double('sell_fee', 25, 8)->nullable();
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
        Schema::dropIfExists('trade_transactions');
    }
}
