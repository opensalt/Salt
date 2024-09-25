<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PasswordField extends Constraint
{
    public string $message = 'Password does not match required criteria';
}
