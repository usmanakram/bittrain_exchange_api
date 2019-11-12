<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Bittrain_detail extends Model
{
	protected $fillable = ['data'];

	protected $hidden = [
        'created_at', 'updated_at'
    ];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }
}
