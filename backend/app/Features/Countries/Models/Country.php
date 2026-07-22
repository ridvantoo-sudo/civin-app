<?php

namespace App\Features\Countries\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['alpha2', 'alpha3', 'name', 'phone_code', 'flag_emoji', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
