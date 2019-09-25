<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'currency_id' => 'integer',
        'payment_gateway_id' => 'integer',
    ];

    public function payment_gateway()
    {
        return $this->morphTo();
    }

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }
}
