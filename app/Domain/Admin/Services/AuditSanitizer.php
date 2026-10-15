<?php

namespace App\Domain\Admin\Services;

final class AuditSanitizer
{
    private const SENSITIVE = '/password|secret|token|api[_-]?key|authorization|cookie|card|provider_(payment|refund)_id/i';

    public function clean(?array $data): ?array
    {
        return $data === null ? null : $this->walk($data, 0);
    }

    private function walk(array $data, int $depth): array
    {
        if ($depth >= 6) return ['_truncated' => true];
        $clean = [];
        foreach ($data as $key => $value) {
            $name = (string) $key;
            if (preg_match(self::SENSITIVE, $name)) {
                $clean[$name] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) $value = $this->walk($value, $depth + 1);
            elseif (is_string($value) && mb_strlen($value) > 2000) $value = mb_substr($value, 0, 2000).'…';
            elseif (is_object($value)) $value = '[OBJECT]';
            $clean[$name] = $value;
        }
        return $clean;
    }
}
