<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'currency_id' => 'integer',
    ];

    protected $appends = [
        'dollar_value',
    ];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function currency()
    {
    	return $this->belongsTo(Currency::class);
    }

    private function getDollarValue($currency, $amount)
    {
        // return Latest_price::wherePair(strtoupper($currency) . 'USDT')->first()->last_price * $amount;
        return 'need to fix';
    }

    public function getDollarValueAttribute()
    {
        // return $this->attributes['dollar_value'] = 333333333333;
        // return 33333;
        return $this->getDollarValue('btc', $this->attributes['total_balance']);
    }

    public static function getUserBalance($user_id, $currency_id)
    {
        return self::firstOrCreate(
            ['user_id' => $user_id, 'currency_id' => $currency_id],
            ['in_order_balance' => 0, 'total_balance' => 0]
        );
    }

    public static function createUserBalance($user_id, $currency_id)
    {
        return self::getUserBalance($user_id, $currency_id);
    }

    public static function incrementUserBalance($user_id, $currency_id, $amount)
    {
        $balance = self::getUserBalance($user_id, $currency_id);
        return $balance->increment('total_balance', $amount);
    }

    public static function decrementUserBalance($user_id, $currency_id, $amount)
    {
        $balance = self::getUserBalance($user_id, $currency_id);
        return $balance->decrement('total_balance', $amount);
    }

    public static function incrementUserBalanceAndInOrderBalance($user_id, $currency_id, $amount, $in_order_amount = null)
    {
        $balance = self::getUserBalance($user_id, $currency_id);
        $balance->increment('total_balance', $amount);
        // $balance->increment('in_order_balance', $amount);
        $balance->increment('in_order_balance', (is_null($in_order_amount) ? $amount : $in_order_amount));
    }

    public static function decrementUserBalanceAndInOrderBalance($user_id, $currency_id, $amount, $in_order_amount = null)
    {
        $balance = self::getUserBalance($user_id, $currency_id);
        $balance->decrement('total_balance', $amount);
        // $balance->decrement('in_order_balance', $amount);
        $balance->increment('in_order_balance', (is_null($in_order_amount) ? $amount : $in_order_amount));
    }
}
