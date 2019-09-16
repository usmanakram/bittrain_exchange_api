<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Coinpayments_transaction extends Model
{
    protected $guarded = ['id'];

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }
}
