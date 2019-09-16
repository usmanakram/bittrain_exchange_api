<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User_deposit_address extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }
}
