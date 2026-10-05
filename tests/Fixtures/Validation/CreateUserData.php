<?php

declare(strict_types=1);

namespace Forja\Tests\Fixtures\Validation;

use DateTimeImmutable;
use Forja\Tests\Fixtures\Database\Role;
use Forja\Validation\Rule\Email;
use Forja\Validation\Rule\In;
use Forja\Validation\Rule\Max;
use Forja\Validation\Rule\Min;
use Forja\Validation\Rule\Required;
use Forja\Validation\Rule\Url;

final readonly class CreateUserData
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[Required, Min(3), Max(50)] public string $name,
        #[Required, Email] public string $email,
        #[Min(18)] public ?int $age = null,
        public Role $role = Role::Member,
        #[Url] public ?string $website = null,
        public ?AddressData $address = null,
        public bool $newsletter = false,
        public ?DateTimeImmutable $birthday = null,
        #[In(['pt', 'en'])] public string $locale = 'pt',
        #[Max(3)] public array $tags = [],
    ) {
    }
}
