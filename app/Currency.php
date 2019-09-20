<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $guarded = ['id'];

    public function coinpayments_transactions()
    {
    	return $this->hasMany(Coinpayments_transaction::class);
    }

    public function user_deposit_address()
    {
    	return $this->hasMany(User_deposit_address::class);
    }

    public function transactions()
    {
    	return $this->hasMany(Transactions::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }
}
