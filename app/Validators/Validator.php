<?php

namespace App\Validators;

class Validator
{
    protected array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && (empty($value) && $value !== '0' && $value !== 0)) {
                    $this->errors[$field] = "The {$field} field is required.";
                } elseif ($rule === 'numeric' && !empty($value) && !is_numeric($value)) {
                    $this->errors[$field] = "The {$field} must be a number.";
                } elseif ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "The {$field} must be a valid email address.";
                } elseif (strpos($rule, 'min:') === 0 && !empty($value)) {
                    $min = (int)substr($rule, 4);
                    if (is_numeric($value) && $value < $min) {
                        $this->errors[$field] = "The {$field} must be at least {$min}.";
                    } elseif (is_string($value) && strlen($value) < $min) {
                        $this->errors[$field] = "The {$field} must be at least {$min} characters.";
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
