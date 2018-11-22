<?php

namespace peckrob\SearchParser\Transforms\SQL;

use peckrob\SearchParser\Transforms\TransformsComponents;
use peckrob\SearchParser\Transforms\Transformation;
use peckrob\SearchParser\SearchQueryComponent;

/**
 * An example class that transforms our custom hashtag type.
 */
class Hashtag implements TransformsComponents {

    /**
     * The hashtag SQL column.
     *
     * @var string
     */
    public $hashtagField = 'hashtag';

    /**
     * Transforms our custom type into a SQL array. If the type isn't a hashtag,
     * we return false, which allows the SQL transformer to continue to the next
     * transform or fall through.
     *
     * @param SearchQueryComponent $component
     * @param string $default_field
     * @param object $context
     * @return void
     */
    public function transformComponent(SearchQueryComponent $component, string $default_field = null, $context = null) {
        $transformation = new Transformation();

        if ($component->type == "hashtag") {
            $transformation->setMessage([
                $this->hashtagField, 
                '=', 
                $context->quote($component->value)
            ]);
        }

        return $transformation;
    }
}
