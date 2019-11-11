<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Trade_order_condition extends Model
{
    protected $guarded = ['id'];

    protected $hidden = [
    	'created_at',
        'updated_at',
    ];

    protected $casts = [
    	'trade_order_id' => 'integer',
    	'associate_trade_order_id' => 'integer',
    	'status' => 'integer',
    ];

    public function trade_order()
    {
    	return $this->belongsTo(Trade_order::class);
    }

    public function associate_trade_order()
    {
    	return $this->belongsTo(Trade_order::class, 'associate_trade_order');
    }
}
