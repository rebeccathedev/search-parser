<?php

namespace peckrob\SearchParser;

use peckrob\SearchParser\Parsers\Parser;

/**
 * A class that parses queries into tokens.
 */
class SearchParser {

    /**
     * An array that holds any additional parsers to run after the main parser
     * has run.
     *
     * @var array
     */
    private $parsers = [];

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

        // The only case we should ever return false is if we had an empty 
        // string. Pretty much anything else is a valid search.
        if (empty($query)) {
            return false;
        }

        // Create a new SearchQuery object.
        $search = new SearchQuery();
        
        // This regex tokenizes string into discrete parts. Word boundaries,
        // strings and field names are all respected.
        $regex = '!([^\s"\']+:["\'].*?["\']|[^\s"\']+|["\'][^"]*["\'])!';
        if (preg_match_all($regex, $query, $matches)) {

            foreach ($matches[0] as $match) {

                // If we have any user-defined parsers, run those first.
                if (!empty($this->parsers)) {
                    foreach ($this->parsers as $parser) {

                        // Run the custom parser.
                        $component = $parser->parsePart($match);

                        // If we were able to parse something for this part, add
                        // it to the SearchQuery and continue with the next 
                        // part.
                        if (!$component->isEmpty()) {
                            $search->push($component);
                            continue 2;
                        }
                    }
                }

                // Now run the built-in parser.
                $component = $this->parsePart($match);

                // Push the path component into the query.
                $search->push($component);
            }
        }


        // Return the completed search;
        return $search;
    }

    /**
     * Parses a part of a query into a SearchQueryComponent
     *
     * @param string $part  A query part.
     * @return SearchQueryComponent
     */
    public function parsePart(string $part) {
        // Create a new component.
        $component = new SearchQueryComponent();

        // If it starts with a !, that means we are negating whatever
        // that token is.
        if (substr($part, 0, 1) == '!') {
            $component->negate = true;
            $part = substr($part, 1, strlen($part) - 1);
        }

        // Now, we look for field names and corresponding values.
        if (preg_match('/(.*?):["\']?(.*?)(?:-(.*))?["\']?$/', $part, $inner_matches)) {
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
            $part = str_replace(['"', "'"], "", $part);
            $component->type = SearchQueryComponent::TEXT;
            $component->value = $part;
        }

        return $component;
    }

    /**
     * Adds a parser to run after 
     *
     * @param Parser $parser
     * @return void
     */
    public function addParser(Parser $parser) {
        $this->parsers[] = $parser;
    }
}
