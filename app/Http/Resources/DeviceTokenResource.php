<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class DeviceTokenResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'id',
        'name',
        'type',
        'token',
        'is_active',
        'created_at',
    ];

    /**
     * The resource's relationships.
     */
    public array $relationships = [
        'user' => User::class,
    ];
}
