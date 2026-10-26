<?php

namespace App\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PROPERTY)]
final class ExistingPath extends Constraint
{
    public string $message = 'The path "{{ value }}" does not exist.';
}
