<?php
// Resource — это про форму ответа. Что именно отдаём фронту и в каком виде. Например у User модели есть password, remember_token, deleted_at — но фронту это не нужно и небезопасно. Resource говорит "отдай только эти поля и в таком формате".

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Models\User $resource
 * * @method __construct(\App\Models\User $resource)
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'email'              => $this->email,
            'email_verified_at'  => $this->email_verified_at,
            'phone'              => $this->phone,
            'first_name'         => $this->first_name,
            'last_name'          => $this->last_name,
            'role'               => $this->role,
            'created_at'         => $this->created_at,
        ];
    }
}
