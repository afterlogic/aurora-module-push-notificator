<?php

namespace Aurora\Modules\PushNotificator\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;

/**
 * Aurora\Modules\PushNotificator\Models\PushToken
 *
 * @property integer $Id
 * @property integer $IdUser
 * @property integer $IdAccount
 * @property string $Email
 * @property string $Uid
 * @property string $Token
 * @property \Illuminate\Support\Carbon|null $CreatedAt
 * @property \Illuminate\Support\Carbon|null $UpdatedAt
 * @property-read mixed $entity_id
 * @method static int count(string $columns = '*')
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken find(int|string $id, array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken findOrFail(int|string $id, mixed $id, Closure|array|string $columns = ['*'], Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken first(array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken firstWhere(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken query()
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken where(Closure|string|array|\Illuminate\Database\Query\Expression $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereIdAccount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereIdUser($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\Aurora\Modules\PushNotificator\Models\PushToken whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PushToken create($value)
 * @mixin \Eloquent
 */
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
