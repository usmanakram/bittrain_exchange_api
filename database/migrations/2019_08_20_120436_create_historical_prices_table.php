<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHistoricalPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('historical_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('pair');
            // $table->enum('time_interval', ['1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d','3d','1w','1M']);
            $table->enum('time_interval', ['1m','3m','5m','15m','30m','1h','2h','4h','6h','8h','12h','1d','3d','1w']);
            $table->double('open', 25, 8);
            $table->double('high', 25, 8);
            $table->double('low', 25, 8);
            $table->double('close', 25, 8);
            $table->double('volume', 25, 8);
            $table->bigInteger('open_time');
            $table->bigInteger('close_time');
            $table->double('asset_volume', 25, 8);
            $table->double('base_volume', 25, 8);
            $table->bigInteger('trades');
            $table->double('asset_buy_volume', 25, 8);
            $table->double('taker_buy_volume', 25, 8);
            $table->double('ignored', 25, 8);
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
        Schema::dropIfExists('historical_prices');
    }
}
