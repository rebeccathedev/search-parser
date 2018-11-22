<?php

namespace rebeccathedev\SearchParser\SearchParser\Tests;

use rebeccathedev\SearchParser\SearchParser;
use rebeccathedev\SearchParser\SearchQuery;
use rebeccathedev\SearchParser\SearchQueryComponent;
use rebeccathedev\SearchParser\Filters\Filter;
use rebeccathedev\SearchParser\Filters\FieldFilter;
use rebeccathedev\SearchParser\Filters\FieldNameMapper;

class FilterTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider dataProvider
     */
    public function testFilter($query, $component_filter_type, $filter_args, $return) {

        $parser = new SearchParser();
        $parsed_result = $parser->parse($query);

        $filter = new Filter();
        $component_filter = new $component_filter_type();
        
        foreach ($filter_args as $filter_arg => $value) {
            $component_filter->$filter_arg = $value;
        }

        $query = new SearchQuery();
        foreach ($return as $ret) {
            $component = new SearchQueryComponent();
            foreach ($ret as $key => $value) {
                $component->$key = $value;
            }
            $query->push($component);
        }

        $filter->addFilter($component_filter);

        $result = $filter->filter($parsed_result);
        $result->rewind();

        $this->assertEquals($query, $result);
    }

    public function dataProvider() {
        return [
            [
                'query' => 'from:me@rebeccapeck.org to:me@rebeccapeck.org',
                'filter' => 'rebeccathedev\SearchParser\Filters\FieldFilter',
                'filter_args' => [
                    'validFields' => ['from']
                ],
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => me@rebeccapeck.org'
                    ]
                ]
            ],
            [
                'query' => 'from:me@rebeccapeck.org',
                'filter' => 'rebeccathedev\SearchParser\Filters\FieldNameMapper',
                'filter_args' => [
                    'mappingFields' => [
                        'from' => 'recipient'
                    ]
                ],
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'recipient',
                        'value' => me@rebeccapeck.org'
                    ]
                ]
            ]
        ];
    }
}
