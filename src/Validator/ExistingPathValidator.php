<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ExistingPathValidator extends ConstraintValidator
{
    public function validate(
        mixed $value,
        Constraint $constraint
    ): void {
        if (!$constraint instanceof ExistingPath) {
            throw new UnexpectedTypeException($constraint, ExistingPath::class);
        }

        if ($value === null || $value === '') {
            return; // Let NotBlank handle this
        }

        if (!is_string($value) ||
            (!is_file($value) && !is_dir($value))) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', (string) $value)
                ->addViolation();
        }
    }
}
