<?php

namespace peckrob\SearchParser\Transforms\Eloquent;

use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;
use peckrob\SearchParser\Transforms\Transform;
use peckrob\SearchParser\Transforms\Transformation;
use Illuminate\Database\Eloquent\Builder;

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
    public function transform(SearchQuery $query) {
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

    /**
     * Transforms a SearchQueryComponent and sets the appropriate methods on an
     * Eloquent Builder object.
     *
     * @param SearchQueryComponent $component
     * @param string $default_field
     * @param object $context
     * @return void
     */
    public function transformComponent(SearchQueryComponent $component, string $default_field = null, $context = null) {

        $transformation = new Transformation();

        // If we don't have a field, we need to use the default field.
        $field = $component->field;
        if (empty($field)) {
            $field = $default_field;
        }

        // If the component is anything other than a ranged query, we treat them
        // the same.
        if ($component->type != SearchQueryComponent::RANGE) {
            $value = $component->value;

            // If the value is an array, that means we are OR'ing a bunch of the
            // same fields together.
            if (is_array($value)) {

                // We have to keep track of this because of the way Eloquent
                // query builder works.
                $first = true;
                foreach ($value as $inner_value) {

                    // Transform the search to check for various things.
                    list($field, $comparator, $inner_value) = 
                        $this->transformIntoSearchComparison(
                            $component->type,
                            $inner_value,
                            $field,
                            $component->negate
                        );

                    // Now, call the correct method.
                    if ($first) {
                        $context->where($field, $comparator, $inner_value);
                        $first = false;
                    } else {
                        $context->orWhere($field, $comparator, $inner_value);
                    }
                }
                
            // Just a standard query otherwise.
            } else if (is_string($value)) {

                // Check for the right comparison.
                list($field, $comparator, $value) = 
                    $this->transformIntoSearchComparison(
                        $component->type,
                        $value,
                        $field,
                        $component->negate
                    );

                // Call the standard where.
                $context->where($field, $comparator, $value);
            }
            
        // On a range query, call the correct methods.
        } else {
            if ($component->negate) {
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
    private function transformIntoSearchComparison(string $type, string $value, string $field, $negate = false) {
        $query = [];
        $comparator = "=";

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
            $comparator = $negate ? '!=' : '=';
        }

        return [
            $field,
            $comparator,
            $value
        ];
    }

}
