<?php 

namespace Asdfx\Phergie\Plugin\Trivia\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {

    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @var array
     */
    protected $fillable = ['nick'];
}

