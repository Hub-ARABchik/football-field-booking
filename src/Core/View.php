<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public function __construct(private readonly string $basePath) {}

    public function render(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-zA-Z0-9_/-]+$#', $template) || str_contains($template, '..')) {
            throw new RuntimeException('Invalid view name.');
        }
        $file = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template . '.php';
        if (!is_file($file)) throw new RuntimeException('View not found: ' . $template);
        extract($data, EXTR_SKIP);
        $view = $this;
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
