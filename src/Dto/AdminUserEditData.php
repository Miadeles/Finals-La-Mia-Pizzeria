<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class AdminUserEditData
{
    #[Assert\NotBlank(message: 'First name is required.')]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Last name is required.')]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Enter a valid email address.')]
    public ?string $email = null;
}
