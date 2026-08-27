<?php

namespace RebeccaTheDev\SearchParser;

final readonly class ParseResult implements \JsonSerializable {
    /** @param list<ParseDiagnostic> $diagnostics */
    public function __construct(public SearchQuery|false $query, public array $diagnostics = []) {}

    public function isValid(): bool {
        return !array_any($this->diagnostics, fn (ParseDiagnostic $item): bool => $item->severity === 'error');
    }

    public function jsonSerialize(): array {
        return ['query' => $this->query, 'diagnostics' => $this->diagnostics, 'valid' => $this->isValid()];
    }
}
