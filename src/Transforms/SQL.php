<?php

namespace peckrob\SearchParser\Transforms;

use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;

/**
 * A class that converts a SearchQuery to a SQL query. Mostly used as an example
 * for what is possible.
 */
class SQL {

    /**
     * Transforms a SearchQuery object into a string.
     *
     * @param SearchQuery   $query          The search query.
     * @param string        $default_field  A default search field to be used
     *                                      with freeform text fields.
     * @param \PDO          $pdo            An instance of a PDO connection for
     *                                      escaping purposes.
     * @return string
     */
    public function transform(SearchQuery $query, string $default_field, \PDO $pdo) {

        // Holds all the ANDS.
        $ands = [];

        // Loop through the query components.
        foreach ($query as $component) {
            
            // Convert each component to a query array.
            $group = $this->convertToQuery($component, $default_field, $pdo);

            // If this evaluated to empty, continue.
            if (empty($group)) {
                continue;
            }

            // If the first group is an array, that means that this is a field
            // query with multiple values that should be OR'd.
            if (is_array($group[0])) {
                $ors = [];
                foreach ($group as $inner_group) {
                    $ors[] = implode(" ", $inner_group);
                }

                // Group them together.
                $ands[] = "(" . implode(" or ", $ors) . ")";
            
            // Otherwise, standard AND query.
            } else {
                $ands[] = implode(" ", $group);
            }
        }

        // Implode them all together and return.
        return implode(" and ", $ands);
    }

    /**
     * Converts an individual component to a query array that can be flattened.
     *
     * @param SearchQuery   $query          The search query.
     * @param string        $default_field  A default search field to be used
     *                                      with freeform text fields.
     * @param \PDO          $pdo            An instance of a PDO connection for
     *                                      escaping purposes.
     * @return void
     */
    public function convertToQuery(SearchQueryComponent $component, string $default_field, \PDO $pdo) {

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
                $query = $this->convertToQueryArray($value, $field, $pdo, $component->negate);
            
            // If the first group is an array, that means that this is a field
            // query with multiple values that should be OR'd.
            } else if (is_array($value)) {
                foreach ($value as $inner_value) {
                    $query[] = $this->convertToQueryArray($inner_value, $field, $pdo, $component->negate);
                }
            }
        
        // Otherwise, we need to build a range query.
        } else {
            $comparator = $component->negate ? 'not between' : 'between';
            $query[] = [
                "`$field`", 
                $comparator, 
                "'" . $pdo->quote($component->firstRangeValue) . "'",
                "and",
                "'" . $pdo->quote($component->secondRangeValue) . "'"
            ];
        }
        
        // Return the query.
        return $query;
    }

    /**
     * Converts the arguments to an array.
     *
     * @param SearchQuery   $value          The search value.
     * @param string        $field          The field
     * @param \PDO          $pdo            An instance of a PDO connection for
     *                                      escaping purposes.
     * @param boolean       $negate         Whether the query is negated.
     * @return void
     */
    public function convertToQueryArray(string $value, string $field, \PDO $pdo, $negate = false) {
        $query = [];
        $comparator = "=";
        if (strstr($value, "*")) {
            $comparator = $negate ? 'not like' : 'like';
            $value = \str_replace("*", "%", $value);
            
        } else {
            $comparator = $negate ? '!=' : '=';
        }

        return ["`$field`", $comparator, "'" . $pdo->quote($value) . "'"];
    }
}
