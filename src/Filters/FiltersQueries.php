<?php

namespace peckrob\SearchParser\Filters;

use peckrob\SearchParser\SearchQuery;

/**
 * An interface that defines a custom filter.
 */
interface FiltersQueries {

    /**
     * Filters receive a copy of the search query.
     *
     * @param SearchQuery $query
     * @return void
     */
    public function filter(SearchQuery $query);
}
