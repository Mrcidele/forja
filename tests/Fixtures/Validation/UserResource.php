<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Validation;

use Forja\Http\Resource\JsonResource;
use Forja\Tests\Fixtures\Database\User;

final class UserResource extends JsonResource
{
    public function toArray(): array
    {
        assert($this->resource instanceof User);

        return [
            'id' => $this->resource->id,
            'nome' => $this->resource->name,
            'papel' => $this->resource->role->value,
        ];
    }

    public function with(): array
    {
        return ['versao' => '1'];
    }
}
