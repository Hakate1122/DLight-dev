<?php

declare(strict_types=1);

namespace App\Model;

use DLight\Database\Traits\SoftDelete;

/**
 * Users model - represents the 'users' table in the database.
 */
class Users extends Model
{
    use SoftDelete;

    protected $table = 'users';
    protected $selectable = ['id', 'name', 'email', 'created_at', 'updated_at'];
    protected $fillable = ['name', 'email'];
}
