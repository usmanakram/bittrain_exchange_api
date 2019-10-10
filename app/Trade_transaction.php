<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Trade_transaction extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
    	'created_at',
        'updated_at',
    ];

    protected $casts = [
    	'buy_order_id' => 'integer',
    	'sell_order_id' => 'integer',
    ];

    public function buy_order()
    {
    	return $this->belongsTo(Trade_order::class, 'buy_order_id');
    }

    public function sell_order()
    {
    	return $this->belongsTo(Trade_order::class, 'sell_order_id');
    }
}
