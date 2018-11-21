<?php

namespace peckrob\SearchParser;

/**
 * A class that holds a tokenized query.
 */
class SearchQuery implements \Iterator {
    /**
     * Internal var that holds the iterator position.
     *
     * @var integer
     */
    private $position = 0;

    /**
     * Internal var that holds the data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Pushes a new query component into the query.
     *
     * @param SearchQueryComponent $item
     * @return void
     */
    public function push(SearchQueryComponent $item) {
        $this->data[] = $item;
    }

    /**
     * Restores the pointer to the end.
     *
     * @return void
     */
    public function rewind() {
        $this->position = 0;
    }

    /**
     * Gets the current item.
     *
     * @return void
     */
    public function current() {
        return $this->data[$this->position];
    }

    /**
     * Gets the current key.
     *
     * @return void
     */
    public function key() {
        return $this->position;
    }

    /**
     * Advances the pointer.
     *
     * @return void
     */
    public function next() {
        ++$this->position;
    }

    /**
     * Whether the pointer location is valid.
     *
     * @return void
     */
    public function valid() {
        return isset($this->data[$this->position]);
    }
}
