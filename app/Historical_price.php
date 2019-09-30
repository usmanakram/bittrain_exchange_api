<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Historical_price extends Model
{
	public $guarded = ['id'];

	protected $hidden = [
		'created_at',
		'updated_at',
	];

	protected $casts = [
		'id' => 'integer',
		'currency_pair_id' => 'integer',
	];

	public function currency_pair()
	{
		return $this->belongsTo(Currency_pair::class);
	}
}
