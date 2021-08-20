<?php

namespace Aurora\Modules\PushNotificator\Models;

use \Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;

class PushToken extends Model
{
    protected $table = 'core_push_notificator_tokens';
    protected $moduleName = 'PushNotificator';

	protected $foreignModel = User::class;
	protected $foreignModelIdColumn = 'IdUser'; // Column that refers to an external table

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