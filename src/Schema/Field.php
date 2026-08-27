<?php

namespace RebeccaTheDev\SearchParser\Schema;

final readonly class Field {
    /** @param list<string|int|float|bool>|null $values */
    public function __construct(
        public string $type = 'string',
        public ?string $mapsTo = null,
        public ?array $values = null,
    ) {}

    public static function string(?string $mapsTo = null): self { return new self('string', $mapsTo); }
    public static function integer(?string $mapsTo = null): self { return new self('integer', $mapsTo); }
    public static function float(?string $mapsTo = null): self { return new self('float', $mapsTo); }
    public static function boolean(?string $mapsTo = null): self { return new self('boolean', $mapsTo); }
    public static function date(?string $mapsTo = null): self { return new self('date', $mapsTo); }
    /** @param list<string|int|float|bool> $values */
    public static function enum(array $values, ?string $mapsTo = null): self { return new self('enum', $mapsTo, $values); }
}
