<?php

namespace rebeccathedev\SearchParser\Transforms\Eloqent;

use rebeccathedev\SearchParser\Transforms\TransformsComponents;
use rebeccathedev\SearchParser\Transforms\Transformation;
use rebeccathedev\SearchParser\SearchQueryComponent;

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
