<?php

namespace RebeccaTheDev\SearchParser;

final readonly class ParseDiagnostic implements \JsonSerializable {
    public function __construct(
        public string $message,
        public int $offset,
        public string $severity = 'error',
    ) {}

    public function jsonSerialize(): array {
        return ['message' => $this->message, 'offset' => $this->offset, 'severity' => $this->severity];
    }
}
