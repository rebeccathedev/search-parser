<?php

namespace RebeccaTheDev\SearchParser;

/**
 * A class that holds a query component.
 */
class SearchQueryComponent {
    // Constants for component types
    public const RANGE = "range";
    public const FIELD = "field";
    public const TEXT = "text";

    /**
     * A string that holds one of the constants above.
     */
    public string $type = '';

    /**
     * A string that holds the field name.
     */
    public string $field = '';

    /**
     * A string, array, or null that holds the field value.
     */
    public string|array|null $value = null;

    /**
     * A string that holds the first ranged value in a range query.
     */
    public ?string $firstRangeValue = null;

    /**
     * A string that holds the second range value in a range query.
     */
    public ?string $secondRangeValue = null;

    /**
     * A boolean that negates this query component.
     */
    public bool $negate = false;

    /**
     * A boolean that requires this query component.
     */
    public bool $require = false;

    /**
     * Returns whether this component is "empty".
     */
    public function isEmpty(): bool {
        return empty($this->type) &&
            empty($this->field) &&
            empty($this->value) &&
            empty($this->firstRangeValue) &&
            empty($this->secondRangeValue);
    }
}
