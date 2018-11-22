<?php

namespace peckrob\SearchParser;

/**
 * A class that holds a query componet.
 */
class SearchQueryComponent {
    // Some constants to make live easier.
    const RANGE = "range";
    const FIELD = "field";
    const TEXT = "text";

    /**
     * A string that holds one of the constants above.
     *
     * @var string
     */
    public $type;

    /**
     * A strig that holds the field name.
     *
     * @var string
     */
    public $field;

    /**
     * A string that holds the field value.
     *
     * @var string
     */
    public $value;

    /**
     * A string that holds the first ranged value in a range query.
     *
     * @var string
     */
    public $firstRangeValue;

    /**
     * A string that holds the second range value in a range query.
     *
     * @var string
     */
    public $secondRangeValue;

    /**
     * A boolean that negates this query component.
     *
     * @var boolean
     */
    public $negate = false;

    /**
     * Returns whether this component is "empty".
     *
     * @return boolean
     */
    public function isEmpty() {
        return empty($this->type) && 
            empty($this->field) &&
            empty($this->value) &&
            empty($this->firstRangeValue) &&
            empty($this->secondRangeValue);
    }
}
