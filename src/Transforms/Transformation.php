<?php

namespace peckrob\SearchParser\Transforms;

/**
 * Transformation is a simple message bag like class that can encapsulate 
 * transformations to make them easier to pass around.
 */
class Transformation {

    /**
     * Whether the message bag has changed.
     *
     * @var boolean
     */
    private $dirty = false;

    /**
     * Holds the message.
     *
     * @var mixed
     */
    private $message = null;

    /**
     * Sets the message.
     *
     * @param mixed $message
     * @return void
     */
    public function setMessage($message) {
        $this->dirty = true;
        $this->message = $message;
    }

    /**
     * Gets the message.
     *
     * @return mixed
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * Returns whether the message bag has changed.
     *
     * @return boolean
     */
    public function isDirty() {
        return $this->dirty;
    }
}
