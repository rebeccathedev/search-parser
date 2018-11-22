<?php

namespace rebeccathedev\SearchParser\Parsers;

use rebeccathedev\SearchParser\SearchQuery;
use rebeccathedev\SearchParser\SearchQueryComponent;

/**
 * An example parser that will parse out social media style hashtags.
 */
class Hashtag implements Parser {

    /**
     * Parses a part.
     *
     * @param string $part A query part
     * @return SearchQueryComponent
     */
    public function parsePart(string $part) {
        $component = new SearchQueryComponent();

        if (preg_match('!\#(.*)!', $part, $match)) {
            $component->type = "hashtag";
            $component->value = $match[1];
        }

        return $component;
    }
}
