<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Bittrain_detail extends Model
{
    public function user()
    {
    	return $this->belongsTo(User::class);
    }
}
