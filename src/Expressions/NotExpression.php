<?php

namespace RebeccaTheDev\SearchParser\Expressions;

final readonly class NotExpression implements Expression {
    public function __construct(public Expression $expression) {}
    public function jsonSerialize(): array { return ['operator' => 'NOT', 'expression' => $this->expression]; }
}
