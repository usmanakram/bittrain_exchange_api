<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id');
            $table->integer('currency_id');
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->enum('payment_gateway', ['coinpayments'])->default('coinpayments');
            $table->integer('payment_gateway_table_id');
            $table->string('address', 100);
            $table->double('amount', 25, 8);
            $table->integer('confirmations');
            $table->string('txn_id', 100);
            $table->integer('status');
            $table->string('status_text', 100);
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
        Schema::dropIfExists('transactions');
    }
}
