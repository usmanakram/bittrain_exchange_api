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
        'direction' => 'integer',
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

    public function buy_trade_transactions()
    {
    	return $this->hasMany(Trade_transaction::class, 'buy_order_id');
    }

    public function sell_trade_transactions()
    {
    	return $this->hasMany(Trade_transaction::class, 'sell_order_id');
    }

    public function condition()
    {
        return $this->hasOne(Trade_order_condition::class);
    }
}
