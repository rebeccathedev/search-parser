<?php

namespace RebeccaTheDev\SearchParser\Transforms\SQL;

use RebeccaTheDev\SearchParser\Transforms\Transform;
use RebeccaTheDev\SearchParser\Transforms\Transformation;
use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;
use RebeccaTheDev\SearchParser\Expressions\ComponentExpression;
use RebeccaTheDev\SearchParser\Expressions\Expression;
use RebeccaTheDev\SearchParser\Expressions\LogicalExpression;
use RebeccaTheDev\SearchParser\Expressions\NotExpression;

/**
 * A class that converts a SearchQuery to a SQL query. Mostly used as an example
 * for what is possible.
 */
class SQL extends Transform {
    private int $parameter = 0;
    private array $bindings = [];

    /** Builds a composable SQL fragment with named parameter bindings. */
    public function transformBound(SearchQuery $query): SqlResult {
        $this->parameter = 0;
        $this->bindings = [];
        $expression = $query->expression;
        if ($expression === null) {
            $nodes = array_map(fn ($component) => new ComponentExpression($component), $query->toArray());
            $expression = array_shift($nodes);
            foreach ($nodes as $node) { $expression = new LogicalExpression('AND', $expression, $node); }
        }
        $clause = $expression ? $this->boundExpression($expression) : '';
        return new SqlResult($clause, $this->bindings);
    }

    private function boundExpression(Expression $expression): string {
        if ($expression instanceof LogicalExpression) {
            return '(' . $this->boundExpression($expression->left) . ' ' . $expression->operator . ' ' . $this->boundExpression($expression->right) . ')';
        }
        if ($expression instanceof NotExpression) { return '(NOT ' . $this->boundExpression($expression->expression) . ')'; }
        return $this->boundComponent($expression->component);
    }

    private function boundComponent(SearchQueryComponent $component): string {
        $field = $component->field ?: $this->defaultField;
        if (!is_string($field) || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $field)) {
            throw new \InvalidArgumentException('Unsafe or missing SQL field identifier.');
        }
        $identifier = '`' . str_replace('.', '`.`', $field) . '`';
        if ($component->operator === 'is-null') { return "$identifier IS " . ($component->negate ? 'NOT ' : '') . 'NULL'; }
        if ($component->operator === 'exists') { return "$identifier IS " . ($component->negate ? '' : 'NOT ') . 'NULL'; }
        if ($component->type === SearchQueryComponent::RANGE) {
            if ($component->firstRangeValue === null) { return $identifier . ($component->negate ? ' > ' : ' <= ') . $this->bind($component->secondRangeValue); }
            if ($component->secondRangeValue === null) { return $identifier . ($component->negate ? ' < ' : ' >= ') . $this->bind($component->firstRangeValue); }
            return $identifier . ($component->negate ? ' NOT BETWEEN ' : ' BETWEEN ') . $this->bind($component->firstRangeValue) . ' AND ' . $this->bind($component->secondRangeValue);
        }
        $values = is_array($component->value) ? $component->value : [$component->value];
        $parts = [];
        foreach ($values as $value) {
            $operator = $component->operator;
            if ($component->negate) {
                $operator = ['=' => '!=', '!=' => '=', '>' => '<=', '>=' => '<', '<' => '>=', '<=' => '>'][$operator] ?? $operator;
            }
            if (is_string($value) && str_contains($value, '*')) { $operator = $component->negate ? 'NOT LIKE' : 'LIKE'; $value = str_replace('*', '%', $value); }
            $parts[] = "$identifier $operator " . $this->bind($value);
        }
        return count($parts) > 1 ? '(' . implode($component->negate ? ' AND ' : ' OR ', $parts) . ')' : $parts[0];
    }

    private function bind(mixed $value): string {
        $name = ':p' . ++$this->parameter;
        $this->bindings[$name] = $value;
        return $name;
    }

    /**
     * Transforms a SearchQuery object into a string.
     *
     * @param SearchQuery   $query          The search query.
     * @return string
     */
    public function transform(SearchQuery $query): mixed {

        // Holds all the ANDS.
        $ands = [];

        // Loop through the query components.
        foreach ($query as $component) {

            // Call the user defined transforms if any.
            if (!empty($this->transforms)) {
                foreach ($this->transforms as $transform) {
                    $message = $transform->transformComponent(
                        $component, 
                        $this->defaultField, 
                        $this->context
                    );
                    
                    // If we actually got a result out of this, add it to the
                    // ands.
                    if ($message->isDirty()) {
                        $ands[] = $this->parseGroup($message->getMessage());
                        continue 2;
                    }
                }
            }
            
            // Convert each component to a query array.
            $message = $this->transformComponent(
                $component, 
                $this->defaultField, 
                $this->context
            );

            // If we have an empty message, just continue. We couldn't parse
            // something.
            if (!$message->isDirty()) {
                continue;
            }

            // Add it to the ands.
            $ands[] = $this->parseGroup($message->getMessage());
        }

        // Implode them all together and return.
        return implode(" and ", $ands);
    }

    /**
     * Transforms a SearchQueryComponent into an array that can be flattened in
     * transform().
     *
     * @param SearchQueryComponent $component
     * @param string $default_field
     * @param object $context
     * @return array
     */
    public function transformComponent(SearchQueryComponent $component, ?string $default_field = null, $context = null) {

        $transformation = new Transformation();

        // Holds the query.
        $query = [];

        // If we don't have a field, we need to use the default field.
        $field = $component->field;
        if (empty($field)) {
            $field = $default_field;
        }

        // For anything other than a range...
        if ($component->type != SearchQueryComponent::RANGE) {
            if ($component->operator === 'is-null') {
                $query = ["`$field`", 'is', $component->negate ? 'not null' : 'null'];
                $transformation->setMessage($query);
                return $transformation;
            }
            if ($component->operator === 'exists') {
                $query = ["`$field`", 'is', $component->negate ? 'null' : 'not null'];
                $transformation->setMessage($query);
                return $transformation;
            }
            $value = $component->value;
            
            // If the value is a string, this is a simple convert.
            if (is_string($value)) {
                $query = $this->transformIntoSearchComparison($component->type, $value, $field, $component->negate, $component->operator);
            
            // If the first group is an array, that means that this is a field
            // query with multiple values that should be OR'd.
            } else if (is_array($value)) {
                foreach ($value as $inner_value) {
                    $query[] = $this->transformIntoSearchComparison($component->type, $inner_value, $field, $component->negate, $component->operator);
                }
            }
        
        // Otherwise, we need to build a range query.
        } else {
            if ($component->firstRangeValue === null) {
                $query = ["`$field`", $component->negate ? '>' : '<=', $this->context->quote($component->secondRangeValue)];
                $transformation->setMessage($query);
                return $transformation;
            }
            if ($component->secondRangeValue === null) {
                $query = ["`$field`", $component->negate ? '<' : '>=', $this->context->quote($component->firstRangeValue)];
                $transformation->setMessage($query);
                return $transformation;
            }
            $comparator = $component->negate ? 'not between' : 'between';
            $query[] = [
                "`$field`", 
                $comparator, 
                $this->context->quote($component->firstRangeValue),
                "and",
                $this->context->quote($component->secondRangeValue)
            ];
        }
        
        // Add the message to the bag.
        $transformation->setMessage($query);

        // Return the message.
        return $transformation;
    }

    /**
     * Parses a group into a string.
     *
     * @param array $group
     * @return string
     */
    private function parseGroup($group) {
        // If the first group is an array, that means that this is a field
        // query with multiple values that should be OR'd.
        if (is_array($group[0])) {
            $ors = [];
            foreach ($group as $inner_group) {
                $ors[] = implode(" ", $inner_group);
            }

            // Group them together.
            return "(" . implode(" or ", $ors) . ")";
        
        // Otherwise, standard AND query.
        } else {
            return implode(" ", $group);
        }
    }

    /**
     * Converts the arguments to an array.
     *
     * @param string        $type           The component type.
     * @param string        $value          The search value.
     * @param string        $field          The field
     * @param boolean       $negate         Whether the query is negated.
     * @return array
     */
    private function transformIntoSearchComparison(string $type, string $value, string $field, $negate = false, string $operator = '=') {

        $query = [];
        $comparator = $operator;

        // We only do this on text types.
        if ($type != SearchQueryComponent::FIELD && $this->looseMode) {
            $comparator = $negate ? 'not like' : 'like';
            $value = str_replace('*', '', $value);
            if (substr($value, 0, 1) != '%') {
                $value = '%' . $value;
            }

            if (substr($value, -1, 1) != '%') {
                $value .= '%';
            }
        } else if (strstr($value, "*")) {
            $comparator = $negate ? 'not like' : 'like';
            $value = \str_replace("*", "%", $value);

        // Otherwise, this is a standard equality search.
        } else {
            $comparator = $negate
                ? (['=' => '!=', '!=' => '=', '>' => '<=', '>=' => '<', '<' => '>=', '<=' => '>'][$operator] ?? $operator)
                : $operator;
        }

        // Return an array containing the properly formatted data.
        return ["`$field`", $comparator, $this->context->quote($value)];
    }
}
