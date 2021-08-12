<?php

namespace Aurora\Modules\PushNotificator\Models;

use \Aurora\System\Classes\Model;

class PushToken extends Model
{
    protected $table = 'core_push_notificator_tokens';
    protected $moduleName = 'PushNotificator';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'IdUser',
        'IdAccount',
        'Email',
        'Uid',
        'Token'
    ];

    /**
    * The attributes that should be hidden for arrays.
    *
    * @var array
    */
    protected $hidden = [
    ];

    protected $casts = [
    ];

    protected $attributes = [
    ];
}