<?php

namespace peckrob\SearchParser\Transforms\SQL;

use peckrob\SearchParser\Transforms\Transform;
use peckrob\SearchParser\Transforms\Transformation;
use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;

/**
 * A class that converts a SearchQuery to a SQL query. Mostly used as an example
 * for what is possible.
 */
class SQL extends Transform {

    /**
     * Transforms a SearchQuery object into a string.
     *
     * @param SearchQuery   $query          The search query.
     * @return string
     */
    public function transform(SearchQuery $query) {

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
    public function transformComponent(SearchQueryComponent $component, string $default_field = null, $context = null) {

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
            $value = $component->value;
            
            // If the value is a string, this is a simple convert.
            if (is_string($value)) {
                $query = $this->transformIntoSearchComparison($component->type, $value, $field, $component->negate);
            
            // If the first group is an array, that means that this is a field
            // query with multiple values that should be OR'd.
            } else if (is_array($value)) {
                foreach ($value as $inner_value) {
                    $query[] = $this->transformIntoSearchComparison($component->type, $inner_value, $field, $component->negate);
                }
            }
        
        // Otherwise, we need to build a range query.
        } else {
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
    private function transformIntoSearchComparison(string $type, string $value, string $field, $negate = false) {

        $query = [];
        $comparator = "=";

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
            $comparator = $negate ? '!=' : '=';
        }

        // Return an array containing the properly formatted data.
        return ["`$field`", $comparator, $this->context->quote($value)];
    }
}
