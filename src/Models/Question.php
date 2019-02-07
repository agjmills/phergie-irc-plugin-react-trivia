<?php 

namespace Asdfx\Phergie\Plugin\Trivia\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    /**
     * @var string
     */
    protected $table = 'questions';

    protected $fillable = ['question', 'answer'];
}

