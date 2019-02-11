<?php 

namespace Asdfx\Phergie\Plugin\Trivia\Models;

use Illuminate\Database\Eloquent\Model;

class UsersPoints extends Model {

    /**
     * @var string
     */
    protected $table = 'users_points';

    /**
     * @var array
     */
    protected $fillable = ['user_id', 'points'];
}

