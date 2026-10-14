<?php

namespace App\Files;

use Illuminate\Database\Eloquent\Model;

class DesignFileVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = ['*'];
}
