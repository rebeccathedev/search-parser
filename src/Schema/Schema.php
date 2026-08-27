<?php

namespace RebeccaTheDev\SearchParser\Schema;

use RebeccaTheDev\SearchParser\ParseDiagnostic;
use RebeccaTheDev\SearchParser\ParseResult;
use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

final readonly class Schema {
    /** @param array<string,Field> $fields */
    public function __construct(private array $fields) {}

    public function apply(SearchQuery $query): ParseResult {
        $diagnostics = [];
        foreach ($query as $component) {
            if ($component->field === '') { continue; }
            $original = $component->field;
            $field = $this->fields[$original] ?? null;
            if (!$field) {
                $diagnostics[] = new ParseDiagnostic("Unknown field: $original", 0);
                continue;
            }
            $component->field = $field->mapsTo ?? $original;
            try {
                if (is_array($component->value)) {
                    $component->value = array_map(fn ($value) => $this->coerce($value, $field), $component->value);
                } elseif ($component->value !== null) {
                    $component->value = $this->coerce($component->value, $field);
                }
                if ($component->type === SearchQueryComponent::RANGE) {
                    if ($component->firstRangeValue !== null) { $component->firstRangeValue = $this->coerce($component->firstRangeValue, $field); }
                    if ($component->secondRangeValue !== null) { $component->secondRangeValue = $this->coerce($component->secondRangeValue, $field); }
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = new ParseDiagnostic("$original: {$exception->getMessage()}", 0);
            }
        }
        return new ParseResult($query, $diagnostics);
    }

    private function coerce(mixed $value, Field $field): string|int|float|bool {
        return match ($field->type) {
            'string' => (string) $value,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : throw new \InvalidArgumentException('Expected an integer.'),
            'float' => is_numeric($value) ? (float) $value : throw new \InvalidArgumentException('Expected a number.'),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? throw new \InvalidArgumentException('Expected a boolean.'),
            'date' => ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value)) && $date->format('Y-m-d') === $value ? (string) $value : throw new \InvalidArgumentException('Expected a YYYY-MM-DD date.'),
            'enum' => in_array($value, $field->values ?? [], true) ? $value : throw new \InvalidArgumentException('Value is not allowed.'),
            default => throw new \InvalidArgumentException("Unknown type {$field->type}."),
        };
    }
}
