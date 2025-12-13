<?php

namespace RebeccaTheDev\SearchParser\Parsers;

use RebeccaTheDev\SearchParser\SearchQueryComponent;

/**
 * An interface that defines how user-defined parsers work.
 */
interface Parser {

    /**
     * User defined parsers must implement this method and must return a
     * SearchQueryComponent object.
     */
    public function parsePart(string $part): SearchQueryComponent;
}
