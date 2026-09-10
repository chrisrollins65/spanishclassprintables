<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A published game room, keyed by the code printed on the packet.
 *
 * Rooms are only ever created or replaced, never deleted: a code already
 * printed on paper has to keep resolving for as long as the packet exists.
 * There is deliberately no delete path anywhere in the application.
 */
class Room extends Model
{
    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'theme', 'payload'];
}
