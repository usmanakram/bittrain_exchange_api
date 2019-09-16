<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCoinpaymentsTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coinpayments_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('address', 100);
            $table->double('amount', 25, 8);
            $table->integer('confirms');
            // $table->string('currency', 10);
            $table->integer('currency_id');
            $table->string('deposit_id', 100);
            $table->double('fee', 25, 8);
            $table->double('fiat_amount', 25, 8);
            $table->string('fiat_coin', 50);
            $table->double('fiat_fee', 25, 8);
            $table->string('ipn_id', 100);
            $table->string('ipn_mode', 10);
            $table->string('ipn_type', 10);
            $table->string('ipn_version', 10);
            $table->string('label', 100);
            $table->string('merchant', 100);
            $table->integer('status');
            $table->string('status_text', 100);
            $table->string('txn_id', 100);
            /*
            [currency] => BTC
            [fiat_coin] => USD
            */
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
        Schema::dropIfExists('coinpayments_transactions');
    }
}
