<?php

namespace RebeccaTheDev\SearchParser\Transforms\Eloquent;

use RebeccaTheDev\SearchParser\Transforms\TransformsComponents;
use RebeccaTheDev\SearchParser\Transforms\Transformation;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

class Hashtag implements TransformsComponents {
    public $hashtagField = 'hashtag';

    public function transformComponent(SearchQueryComponent $component, ?string $default_field = null, $context = null) {
        $transformation = new Transformation();
        
        if ($component->type == 'hashtag') {
            $context->where($this->hashtagField, $component->value);
            $transformation->setMessage($context);
        }

        return $transformation;
    }
}
