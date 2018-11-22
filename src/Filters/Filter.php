<?php

namespace peckrob\SearchParser\Filters;

use peckrob\SearchParser\SearchQuery;

/**
 * A class that implements filtering.
 */
class Filter {
    
    /**
     * Holds the filters.
     *
     * @var array
     */
    private $filters = [];

    /**
     * Filters results based on filters added to this object.
     *
     * @param SearchQuery $data
     * @return void
     */
    public function filter(SearchQuery $data) {
        if (!empty($this->filters)) {
            foreach ($this->filters as $filter) {
                $data = $filter->filter($data);
            }
        }

        return $data;
    }

    /**
     * Adds a filter
     *
     * @param FiltersQueries $filter
     * @return void
     */
    public function addFilter(FiltersQueries $filter) {
        $this->filters[] = $filter;
    }
}
