<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EcommerceAddressType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'technical_name',
        'name_de',
        'name_en',
        'role',
        'field_schema',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'field_schema' => 'array',
            'is_active'    => 'boolean',
        ];
    }
}
