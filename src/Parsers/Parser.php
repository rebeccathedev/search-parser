<?php

namespace peckrob\SearchParser\Parsers;

/**
 * An interface that defines how user-defined parsers work.
 */
interface Parser {

    /**
     * User defined parsers must implement this method and must return a 
     * SearchQueryComponent object.
     *
     * @param string $part
     * @return SearchQueryComponent 
     */
    public function parsePart(string $part);
}
