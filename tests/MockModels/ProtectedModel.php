<?php

namespace Tests\MockModels;

use BobKosse\DataCompliance\Traits\HasPrivacy;
use Illuminate\Database\Eloquent\Model;

class ProtectedModel extends Model {
    use HasPrivacy;

    protected $privacyFields = ['email', 'phone'];
}
