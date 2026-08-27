<?php

namespace RebeccaTheDev\SearchParser\Expressions;

use RebeccaTheDev\SearchParser\SearchQueryComponent;

final readonly class ComponentExpression implements Expression {
    public function __construct(public SearchQueryComponent $component) {}
    public function jsonSerialize(): array { return ['component' => $this->component]; }
}
