<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Bittrain_transaction extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'id' => 'integer',
        'currency_id' => 'integer',
    ];

    public function transaction()
    {
    	return $this->morphOne(Transaction::class, 'payment_gateway');
    }

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }
}
