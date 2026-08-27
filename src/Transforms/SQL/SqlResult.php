<?php

namespace RebeccaTheDev\SearchParser\Transforms\SQL;

final readonly class SqlResult implements \JsonSerializable {
    /** @param array<string,mixed> $bindings */
    public function __construct(public string $clause, public array $bindings) {}
    public function jsonSerialize(): array { return ['clause' => $this->clause, 'bindings' => $this->bindings]; }
}
