<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Roles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

final class RoleMemberResource extends JsonResource
{
    /**
     * The id is always returned; name and email only to actors who hold `access.users.view`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mayViewUsers = $this->mayViewUsers($request);

        return [
            'id' => $this->resource->getKey(),
            'name' => $this->when($mayViewUsers, fn (): mixed => $this->resource->name),
            'email' => $this->when($mayViewUsers, fn (): mixed => $this->resource->email),
        ];
    }

    private function mayViewUsers(Request $request): bool
    {
        /** @var bool|null $cached */
        $cached = $request->attributes->get('roles.may_view_users');

        if ($cached === null) {
            $cached = Gate::forUser($request->user())->allows('access.users.view');
            $request->attributes->set('roles.may_view_users', $cached);
        }

        return $cached;
    }
}
