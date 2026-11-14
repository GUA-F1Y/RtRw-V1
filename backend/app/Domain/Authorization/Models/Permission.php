<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'description'])]
class Permission extends Model {}
