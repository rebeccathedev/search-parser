<?php

namespace rebeccathedev\SearchParser\SearchParser\Tests;

use rebeccathedev\SearchParser\SearchParser;
use rebeccathedev\SearchParser\SearchQuery;
use rebeccathedev\SearchParser\SearchQueryComponent;

class ParseTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider dataProvider
     */
    public function testParse($query, $return) {

        $parser = new SearchParser();
        $parsed_result = $parser->parse($query);

        if (empty($return)) {
            $this->assertEquals($return, $parsed_result);
        } else if (is_array($return)) {
            $query = new SearchQuery();

            foreach ($return as $ret) {
                $component = new SearchQueryComponent();
                foreach ($ret as $key => $value) {
                    $component->$key = $value;
                }
                $query->push($component);
            }

            $this->assertEquals($query, $parsed_result);
        }
    }

    public function dataProvider() {
        return [
            [
                'query' => '',
                'return' => false
            ],
            [
                'query' => 'from:me@rebeccapeck.org',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => me@rebeccapeck.org'
                    ]
                ]
            ],
            [
                'query' => '!from:me@rebeccapeck.org',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => me@rebeccapeck.org',
                        'negate' => true
                    ]
                ]
            ],
            [
                'query' => 'between:1-10',
                'return' => [
                    [
                        'type' => 'range',
                        'field' => 'between',
                        'firstRangeValue' => '1',
                        'secondRangeValue' => '10'
                    ]
                ]
            ],
            [
                'query' => '"foo bar"',
                'return' => [
                    [
                        'type' => 'text',
                        'field' => '',
                        'value' => 'foo bar'
                    ]
                ]
            ],
            [
                'query' => 'from:me@rebeccapeck.org "foo bar"',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => me@rebeccapeck.org'
                    ],
                    [
                        'type' => 'text',
                        'field' => '',
                        'value' => 'foo bar'
                    ]
                ]
            ],
            [
                'query' => 'from:me@rebeccapeck.org,me@rebeccapeck.org',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => [me@rebeccapeck.org', me@rebeccapeck.org']
                    ]
                ]
            ],
        ];
    }
}
