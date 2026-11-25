<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data) {}

    private function length(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        $count = preg_match_all('/./us', $value);
        return $count === false ? strlen($value) : $count;
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "Майдони «{$label}» ҳатмист.";
        }
        return $this;
    }

    public function string(string $field, int $min, int $max, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') return $this;
        if (!is_string($value) || $this->length(trim($value)) < $min || $this->length(trim($value)) > $max) {
            $this->errors[$field] = "Майдони «{$label}» бояд аз {$min} то {$max} аломат дошта бошад.";
        }
        return $this;
    }

    public function email(string $field, string $label = 'Email'): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 190)) {
            $this->errors[$field] = "Қимати «{$label}» нодуруст аст.";
        }
        return $this;
    }

    public function phone(string $field, string $label = 'Телефон'): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && !preg_match('/^\+?[0-9]{9,15}$/', $value)) {
            $this->errors[$field] = "Рақами «{$label}» нодуруст аст.";
        }
        return $this;
    }

    public function oneOf(string $field, array $values, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !in_array($value, $values, true)) {
            $this->errors[$field] = "Қимати «{$label}» иҷозат дода нашудааст.";
        }
        return $this;
    }

    public function integer(string $field, int $min, int $max, string $label): self
    {
        $value = filter_var($this->data[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value === null || $value < $min || $value > $max) {
            $this->errors[$field] = "Майдони «{$label}» бояд адади бутуни аз {$min} то {$max} бошад.";
        }
        return $this;
    }

    public function decimal(string $field, float $min, float $max, string $label): self
    {
        $value = filter_var($this->data[$field] ?? null, FILTER_VALIDATE_FLOAT);
        if ($value === false || $value === null || $value < $min || $value > $max) {
            $this->errors[$field] = "Қимати «{$label}» бояд адади аз {$min} то {$max} бошад.";
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $valid = $date && $date->format('Y-m-d') === $value;
        if (!$valid) $this->errors[$field] = "Формати «{$label}» бояд YYYY-MM-DD бошад.";
        return $this;
    }

    public function time(string $field, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            $this->errors[$field] = "Формати вақти «{$label}» нодуруст аст.";
        }
        return $this;
    }

    public function errors(): array { return $this->errors; }
    public function fails(): bool { return $this->errors !== []; }
}
