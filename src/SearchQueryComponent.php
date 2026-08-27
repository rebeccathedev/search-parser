<?php

namespace RebeccaTheDev\SearchParser;

/**
 * A class that holds a query component.
 */
class SearchQueryComponent implements \JsonSerializable {
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
    public string|int|float|bool|array|null $value = null;

    /**
     * A string that holds the first ranged value in a range query.
     */
    public string|int|float|bool|null $firstRangeValue = null;

    /**
     * A string that holds the second range value in a range query.
     */
    public string|int|float|bool|null $secondRangeValue = null;

    /**
     * A boolean that negates this query component.
     */
    public bool $negate = false;

    /**
     * A boolean that requires this query component.
     */
    public bool $require = false;

    /** Comparison operator such as =, !=, >, >=, <, <=, is-null or exists. */
    public string $operator = '=';

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

    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
