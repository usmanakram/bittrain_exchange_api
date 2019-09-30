<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Currency_pair extends Model
{
    protected $guarded = ['id'];

	protected $hidden = [
		'created_at',
		'updated_at',
	];

	protected $casts = [
		'id' => 'integer',
		'base_currency_id' => 'integer',
		'quote_currency_id' => 'integer',
		'status' => 'boolean',
	];

	public function base_currency()
	{
		return $this->belongsTo(Currency::class, 'base_currency_id');
	}

	public function quote_currency()
	{
		return $this->belongsTo(Currency::class, 'quote_currency_id');
	}

    public function latest_price()
    {
        return $this->hasOne(Latest_price::class);
    }

    public function historical_prices()
    {
    	return $this->hasMany(Historical_price::class);
    }
}
