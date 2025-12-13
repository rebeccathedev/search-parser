<?php

namespace RebeccaTheDev\SearchParser;

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
    public function rewind(): void {
        $this->position = 0;
    }

    /**
     * Gets the current item.
     *
     * @return mixed
     */
    public function current(): mixed {
        return $this->data[$this->position];
    }

    /**
     * Gets the current key.
     *
     * @return mixed
     */
    public function key(): mixed {
        return $this->position;
    }

    /**
     * Advances the pointer.
     *
     * @return void
     */
    public function next(): void {
        ++$this->position;
    }

    /**
     * Whether the pointer location is valid.
     *
     * @return bool
     */
    public function valid(): bool {
        return isset($this->data[$this->position]);
    }

    /**
     * Merges another SearchQuery with this one.
     *
     * @param SearchQuery $query
     * @return void
     */
    public function merge(SearchQuery $query) {
        foreach($query as $component) {
            $this->push($component);
        }
    }

    /**
     * Removes a given item from the query.
     *
     * @param SearchQueryComponent $item
     * @return void
     */
    public function remove(SearchQueryComponent $item) {
        foreach ($this->data as $key => $component) {
            if ($component == $item) {
                unset($this->data[$key]);
                return;
            }
        }
    }

    /**
     * Replaces a given item with a new one.
     *
     * @param SearchQueryComponent $old
     * @param SearchQueryComponent $new
     * @return void
     */
    public function replace(SearchQueryComponent $old, SearchQueryComponent $new) {
        foreach ($this->data as $key => $component) {
            if ($component == $old) {
                $this->data[$key] = $new;
                return;
            }
        }
    }
}
