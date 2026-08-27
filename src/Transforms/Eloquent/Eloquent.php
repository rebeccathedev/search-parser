<?php

namespace RebeccaTheDev\SearchParser\Transforms\Eloquent;

use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;
use RebeccaTheDev\SearchParser\Transforms\Transform;
use RebeccaTheDev\SearchParser\Transforms\Transformation;
use Illuminate\Database\Eloquent\Builder;
use RebeccaTheDev\SearchParser\Expressions\ComponentExpression;
use RebeccaTheDev\SearchParser\Expressions\Expression;
use RebeccaTheDev\SearchParser\Expressions\LogicalExpression;
use RebeccaTheDev\SearchParser\Expressions\NotExpression;

/**
 * A class that converts a SearchQuery to a Laravel Eloquent Builder query. 
 */
class Eloquent extends Transform {

    /**
     * Transforms an eloquent object with the SearchQuery data.
     *
     * @param SearchQuery $query
     * @return Builder
     */
    public function transform(SearchQuery $query): mixed {
        if ($query->expression !== null) {
            $this->applyExpression($query->expression, $this->context);
            return $this->context;
        }

        // Loop through the query components.
        foreach ($query as $component) {
            // Call the user defined transforms if any.
            if (!empty($this->transforms)) {
                foreach ($this->transforms as $transform) {

                    // The component transforms return the context back to us.
                    $message = $transform->transformComponent(
                        $component, 
                        $this->defaultField, 
                        $this->context
                    );
                    
                    // If we actually got a result out of this, add it to the
                    // ands.
                    if ($message->isDirty()) {
                        $this->context = $message->getMessage();
                        continue 2;
                    }
                }
            }

            // If we didn't parse this above, we need to pass it along to the
            // built-in processor here.
            $message = $this->transformComponent(
                $component, 
                $this->defaultField, 
                $this->context
            );
            
            // If we actually got a result out of this, add it to the
            // ands.
            if ($message->isDirty()) {
                $this->context = $message->getMessage();
            }
        }

        return $this->context;
    }

    private function applyExpression(Expression $expression, mixed $context): void {
        if ($expression instanceof LogicalExpression) {
            if ($expression->operator === 'AND') {
                $this->applyExpression($expression->left, $context);
                $this->applyExpression($expression->right, $context);
                return;
            }
            $context->where(function ($group) use ($expression): void {
                $group->where(function ($left) use ($expression): void {
                    $this->applyExpression($expression->left, $left);
                });
                $group->orWhere(function ($right) use ($expression): void {
                    $this->applyExpression($expression->right, $right);
                });
            });
            return;
        }
        if ($expression instanceof NotExpression) {
            $context->whereNot(function ($group) use ($expression): void {
                $this->applyExpression($expression->expression, $group);
            });
            return;
        }

        foreach ($this->transforms as $transform) {
            $message = $transform->transformComponent($expression->component, $this->defaultField, $context);
            if ($message->isDirty()) { return; }
        }
        $this->transformComponent($expression->component, $this->defaultField, $context);
    }

    /**
     * Transforms a SearchQueryComponent and sets the appropriate methods on an
     * Eloquent Builder object.
     *
     * @param SearchQueryComponent $component
     * @param string $default_field
     * @param object $context
     * @return void
     */
    public function transformComponent(SearchQueryComponent $component, ?string $default_field = null, $context = null) {

        $transformation = new Transformation();

        // If we don't have a field, we need to use the default field.
        $field = $component->field;
        if (empty($field)) {
            $field = $default_field;
        }

        // If the component is anything other than a ranged query, we treat them
        // the same.
        if ($component->type != SearchQueryComponent::RANGE) {
            if ($component->operator === 'is-null') {
                $method = $component->negate ? 'whereNotNull' : 'whereNull';
                $context->{$method}($field);
                $transformation->setMessage($context);
                return $transformation;
            }
            if ($component->operator === 'exists') {
                $method = $component->negate ? 'whereNull' : 'whereNotNull';
                $context->{$method}($field);
                $transformation->setMessage($context);
                return $transformation;
            }
            $value = $component->value;

            // If the value is an array, that means we are OR'ing a bunch of the
            // same fields together.
            if (is_array($value)) {
                // Keep alternatives in a nested WHERE group. Without the
                // closure, a later OR can escape the surrounding AND clauses:
                // `status = active AND role = admin OR role = editor`.
                $context->where(function ($query) use ($value, $component, $field): void {
                    foreach ($value as $index => $innerValue) {
                        [$comparisonField, $comparator, $comparisonValue] =
                            $this->transformIntoSearchComparison(
                                $component->type,
                                $innerValue,
                                $field,
                                $component->negate,
                                $component->operator
                            );

                        $method = $index === 0 || $component->negate ? 'where' : 'orWhere';
                        $query->{$method}($comparisonField, $comparator, $comparisonValue);
                    }
                });
                
            // Just a standard query otherwise.
            } else if (is_string($value)) {

                // Check for the right comparison.
                list($field, $comparator, $value) = 
                    $this->transformIntoSearchComparison(
                        $component->type,
                        $value,
                        $field,
                        $component->negate,
                        $component->operator
                    );

                // Call the standard where.
                $context->where($field, $comparator, $value);
            }
            
        // On a range query, call the correct methods.
        } else {
            if ($component->firstRangeValue === null) {
                $context->where($field, $component->negate ? '>' : '<=', $component->secondRangeValue);
            } elseif ($component->secondRangeValue === null) {
                $context->where($field, $component->negate ? '<' : '>=', $component->firstRangeValue);
            } elseif ($component->negate) {
                $context->whereNotBetween($field, [$component->firstRangeValue, $component->secondRangeValue]);
            } else {
                $context->whereBetween($field, [$component->firstRangeValue, $component->secondRangeValue]);
            }
        }

        // Add the message to the bag.
        $transformation->setMessage($context);

        // Return the message.
        return $transformation;
    }

    /**
     * Transforms the data into a search comparison.
     *
     * @param string $type
     * @param string $value
     * @param string $field
     * @param boolean $negate
     * @return array
     */
    private function transformIntoSearchComparison(string $type, string $value, string $field, $negate = false, string $operator = '=') {
        $query = [];
        $comparator = $operator;

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

        return [
            $field,
            $comparator,
            $value
        ];
    }

}
