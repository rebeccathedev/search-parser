<?php

namespace RebeccaTheDev\SearchParser\Parsers;

use RebeccaTheDev\SearchParser\SearchQuery;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

/**
 * An example parser that will parse out social media style hashtags.
 */
class Hashtag implements Parser {

    /**
     * Parses a part.
     */
    public function parsePart(string $part): SearchQueryComponent {
        $component = new SearchQueryComponent();

        if (preg_match('!\#(.*)!', $part, $match)) {
            $component->type = "hashtag";
            $component->value = $match[1];
        }

        return $component;
    }
}
