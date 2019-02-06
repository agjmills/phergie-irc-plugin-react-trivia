<?php 

namespace Asdfx\Phergie\Plugin\Trivia\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {

    protected $table = 'users';

    protected $fillable = ['nick', 'points'];
}

