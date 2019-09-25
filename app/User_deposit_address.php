<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class User_deposit_address extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'currency_id' => 'integer',
    ];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }
}
