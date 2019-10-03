<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Trade_order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
    	'created_at',
        'updated_at',
    ];

    protected $casts = [
    	'user_id' => 'integer',
    	'currency_pair_id' => 'integer',
    	// 'fee_currency_id' => 'integer',
    	'type' => 'integer',
    	'status' => 'integer',
    ];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function currency_pair()
    {
    	return $this->belongsTo(Currency_pair::class);
    }

    public function fee_currency()
    {
    	return $this->belongsTo(Currency::class, 'fee_currency_id');
    }

    /*public function trade_transactions()
    {
    	return $this->hasMany(Trade_transaction::class);
    }*/
}
