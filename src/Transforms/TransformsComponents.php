<?php

namespace peckrob\SearchParser\Transforms;

use peckrob\SearchParser\SearchQueryComponent;

/**
 * An interface that defines a custom component transformer.
 */
interface TransformsComponents {

    /**
     * Transforms a component. It should return it's transformation as part of
     * a Transformation message.
     *
     * @param SearchQueryComponent $component
     * @param string $default_field
     * @param object $context
     * @return Transformation
     */
    public function transformComponent(SearchQueryComponent $component, string $default_field = null, $context = null);
}
