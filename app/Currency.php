<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function currency_pairs()
    {
        return $this->hasMany(Currency_pair::class);
    }

    public function user_deposit_address()
    {
    	return $this->hasMany(User_deposit_address::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function transactions()
    {
    	return $this->hasMany(Transaction::class);
    }

    public function coinpayments_transactions()
    {
    	return $this->hasMany(Coinpayments_transaction::class);
    }

    public function bittrain_transactions()
    {
        return $this->hasMany(Bittrain_transaction::class);
    }

    public function trade_orders()
    {
        return $this->hasMany(Trade_order::class, 'fee_currency_id');
    }
}
