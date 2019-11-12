<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'created_at', 'updated_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function bittrain_detail()
    {
        return $this->hasOne(Bittrain_detail::class);
    }

    public function user_deposit_addresses()
    {
        return $this->hasMany(User_deposit_address::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function trade_orders()
    {
        return $this->hasMany(Trade_order::class);
    }
}
