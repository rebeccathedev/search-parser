<?php

namespace peckrob\SearchParser\Transforms;

use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;
use Illuminate\Database\Eloquent\Model;

/**
 * A class that converts a SearchQuery to a Laravel Eloquent model query. 
 */
class Eloquent {
    public function transform(SearchQuery $query, string $default_field, Model $model) {
        // Loop through the query components.
        foreach ($query as $component) {

            // If we don't have a field, we need to use the default field.
            $field = $component->field;
            if (empty($field)) {
                $field = $default_field;
            }

            if ($component->type != SearchQueryComponent::RANGE) {
                $value = $component->value;
                if (is_array($value)) {
                    $first = true;
                    foreach ($value as $inner_value) {
                        if ($first) {
                            $model->where($field, $inner_value);
                            $first = false;
                        } else {
                            $model->orWhere($field, $inner_value);
                        }
                    }
                } else if (is_string($value)) {
                    if ($component->negate) {
                        $model->whereNot($field, $value);
                    } else {
                        $model->where($field, $value);
                    }
                }
                
            } else {
                if ($component->negate) {
                    $model->whereNotBetween($field, [$component->firstRangeValue, $component->secondRangeValue]);
                } else {
                    $model->whereBetween($field, [$component->firstRangeValue, $component->secondRangeValue]);
                }
            }
        }

        return $model;
    }
}
