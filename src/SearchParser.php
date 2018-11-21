<?php

namespace peckrob\SearchParser;

/**
 * A class that parses queries into tokens.
 */
class SearchParser {

    /**
     * Converts a query string using a standardized vocabulary into a parsed
     * Query object.
     *
     * @param string $query A query to covert.
     * @return mixed Either a SearchQuery object or false if it couldn't parse
     *               anything. Really false should only ever be returned for an
     *               empty string.
     */
    public function parse(string $query) {
        
        // This regex tokenizes string into discrete parts. Word boundaries,
        // strings and field names are all respected.
        $regex = '!([^\s"\']+:["\'].*?["\']|[^\s"\']+|["\'][^"]*["\'])!';
        if (preg_match_all($regex, $query, $matches)) {

            // Create a new SearchQuery object.
            $search = new SearchQuery();

            foreach ($matches[0] as $match) {

                // Create a new component.
                $component = new SearchQueryComponent();

                // If it starts with a !, that means we are negating whatever
                // that token is.
                if (substr($match, 0, 1) == '!') {
                    $component->negate = true;
                    $match = substr($match, 1, strlen($match) - 1);
                }

                // Now, we look for field names and corresponding values.
                if (preg_match('/(.*?):["\']?(.*?)(?:-(.*))?["\']?$/', $match, $inner_matches)) {
                    $component->field = $inner_matches[1];

                    // A match of 3 means that this is a ranged query, like
                    // field:date-date.
                    if (count($inner_matches) > 3) {
                        $component->type = SearchQueryComponent::RANGE;
                        $component->firstRangeValue = $inner_matches[2];
                        $component->secondRangeValue = $inner_matches[3];
                    
                    // Otherwise, it is a standard value query.
                    } else {
                        $component->type = SearchQueryComponent::FIELD;

                        // If a value has a comma in it, that means we want to
                        // get any of those values, so we store them as an array
                        // on the value.
                        if (strstr($inner_matches[2], ',')) {
                            $component->value = explode(',', $inner_matches[2]);

                        // Othwerwise, just a standard value.
                        } else {
                            $component->value = $inner_matches[2];
                        }
                    }
                    
                // This is just a standard text lookup.
                } else {
                    $match = str_replace(['"', "'"], "", $match);
                    $component->type = SearchQueryComponent::TEXT;
                    $component->value = $match;
                }

                // Push the path component into the query.
                $search->push($component);
            }

            // Return the completed query.
            return $search;
        }

        // Return false if we have no query to return.
        return false;
    }
}
