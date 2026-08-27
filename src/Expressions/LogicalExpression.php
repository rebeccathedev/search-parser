<?php

namespace RebeccaTheDev\SearchParser\Expressions;

final readonly class LogicalExpression implements Expression {
    public function __construct(public string $operator, public Expression $left, public Expression $right) {}
    public function jsonSerialize(): array { return ['operator' => $this->operator, 'left' => $this->left, 'right' => $this->right]; }
}
