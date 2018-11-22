<?php

namespace peckrob\SearchParser\Transforms\Eloqent;

use peckrob\SearchParser\Transforms\TransformsComponents;
use peckrob\SearchParser\Transforms\Transformation;
use peckrob\SearchParser\SearchQueryComponent;

class Hashtag implements TransformsComponents {
    public $hashtagField = 'hashtag';

    public function transformComponent(SearchQueryComponent $component, string $default_field = null, $context = null) {
        $transformation = new Transformation();
        
        if ($component->type == 'hashtag') {
            $context->where($hashtagField, $component->value);
            $transformation->setMessage($context);
        }

        return $transformation;
    }
}
