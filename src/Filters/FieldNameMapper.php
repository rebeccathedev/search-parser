<?php

namespace peckrob\SearchParser\Filters;

use peckrob\SearchParser\SearchQuery;

/**
 * A filter that can map field names to other names.
 */
class FieldNameMapper implements FiltersQueries {

    /**
     * Holds the mapping array.
     *
     * @var array
     */
    public $mappingFields = [];

    /**
     * Modifies the query.
     *
     * @param SearchQuery $query
     * @return void
     */
    public function filter(SearchQuery $query) {
        foreach ($query as $key => $component) {
            if (!empty($component->field) && in_array($component->field, array_keys($this->mappingFields))) {
                $old = $component;
                $component->field = $this->mappingFields[$old->field];
                $query->replace($old, $component);
            }
        }

        return $query;
    }
}
