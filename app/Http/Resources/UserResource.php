<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    /**
     * The resource's attributes.
     */
    public $attributes = [
        'id',
        'name',
        'email',
        'created_at',
    ];

    /**
     * The resource's relationships.
     */
    public $relationships = [
        'device' => User::class,
        'roles',
    ];

    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->toArray()),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->toArray()),
        ]);
    }
}
