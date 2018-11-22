<?php

namespace peckrob\SearchParser\SearchParser\Tests;

use peckrob\SearchParser\SearchParser;
use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;
use peckrob\SearchParser\Filters\Filter;
use peckrob\SearchParser\Filters\FieldFilter;
use peckrob\SearchParser\Filters\FieldNameMapper;

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
                'query' => 'from:foo@example.com to:bar@example.com',
                'filter' => 'peckrob\SearchParser\Filters\FieldFilter',
                'filter_args' => [
                    'validFields' => ['from']
                ],
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => 'foo@example.com'
                    ]
                ]
            ],
            [
                'query' => 'from:foo@example.com',
                'filter' => 'peckrob\SearchParser\Filters\FieldNameMapper',
                'filter_args' => [
                    'mappingFields' => [
                        'from' => 'recipient'
                    ]
                ],
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'recipient',
                        'value' => 'foo@example.com'
                    ]
                ]
            ]
        ];
    }
}
