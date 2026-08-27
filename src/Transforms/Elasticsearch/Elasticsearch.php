<?php

namespace RebeccaTheDev\SearchParser\Transforms\Elasticsearch;

use RebeccaTheDev\SearchParser\Expressions\ComponentExpression;
use RebeccaTheDev\SearchParser\Expressions\Expression;
use RebeccaTheDev\SearchParser\Expressions\LogicalExpression;
use RebeccaTheDev\SearchParser\Expressions\NotExpression;
use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

final class Elasticsearch {
    public function __construct(private readonly ?string $defaultField = null) {}

    public function transform(SearchQuery $query): array {
        $expression = $query->expression;
        if ($expression === null) {
            $nodes = array_map(fn ($component) => new ComponentExpression($component), $query->toArray());
            $expression = array_shift($nodes);
            foreach ($nodes as $node) { $expression = new LogicalExpression('AND', $expression, $node); }
        }
        return $expression ? ['query' => $this->expression($expression)] : ['query' => ['match_all' => (object) []]];
    }

    private function expression(Expression $expression): array {
        if ($expression instanceof LogicalExpression) {
            $key = $expression->operator === 'OR' ? 'should' : 'must';
            $bool = [$key => [$this->expression($expression->left), $this->expression($expression->right)]];
            if ($key === 'should') { $bool['minimum_should_match'] = 1; }
            return ['bool' => $bool];
        }
        if ($expression instanceof NotExpression) {
            return ['bool' => ['must_not' => [$this->expression($expression->expression)]]];
        }
        return $this->component($expression->component);
    }

    private function component(SearchQueryComponent $component): array {
        $field = $component->field ?: $this->defaultField;
        if (!$field) { throw new \InvalidArgumentException('A default field is required for text components.'); }
        if ($component->operator === 'is-null' || $component->operator === 'exists') {
            $exists = ['exists' => ['field' => $field]];
            $missing = $component->operator === 'is-null';
            if ($component->negate) { $missing = !$missing; }
            return $missing ? ['bool' => ['must_not' => [$exists]]] : $exists;
        }
        if ($component->type === SearchQueryComponent::RANGE) {
            $range = [];
            if ($component->firstRangeValue !== null) { $range['gte'] = $component->firstRangeValue; }
            if ($component->secondRangeValue !== null) { $range['lte'] = $component->secondRangeValue; }
            $query = ['range' => [$field => $range]];
            return $component->negate ? ['bool' => ['must_not' => [$query]]] : $query;
        }
        if ($component->operator !== '=') {
            $map = ['>' => 'gt', '>=' => 'gte', '<' => 'lt', '<=' => 'lte', '!=' => null];
            if ($component->operator === '!=') {
                return ['bool' => ['must_not' => [['term' => [$field => $component->value]]]]];
            }
            $query = ['range' => [$field => [$map[$component->operator] => $component->value]]];
            return $component->negate ? ['bool' => ['must_not' => [$query]]] : $query;
        }
        $values = is_array($component->value) ? $component->value : [$component->value];
        $queries = array_map(function ($value) use ($field, $component): array {
            $query = is_string($value) && str_contains($value, '*')
                ? ['wildcard' => [$field => $value]]
                : ['term' => [$field => $value]];
            return $component->negate ? ['bool' => ['must_not' => [$query]]] : $query;
        }, $values);
        return count($queries) === 1 ? $queries[0] : ['bool' => ['should' => $queries, 'minimum_should_match' => 1]];
    }
}
