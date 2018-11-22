<?php

namespace rebeccathedev\SearchParser\Filters;

use rebeccathedev\SearchParser\SearchQuery;

/**
 * A class that filters out unknown field names.
 */
class FieldFilter implements FiltersQueries {

    /**
     * Holds the valid fields.
     *
     * @var array
     */
    public $validFields = [];

    /**
     * Runs the filter.
     *
     * @param SearchQuery $query
     * @return void
     */
    public function filter(SearchQuery $query) {
        foreach ($query as $key => $component) {
            if (!empty($component->field) && !in_array($component->field, $this->validFields)) {
                $query->remove($component);
            }
        }

        return $query;
    }
}
