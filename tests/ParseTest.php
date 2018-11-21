<?php

namespace peckrob\SearchParser\SearchParser\Tests;

use peckrob\SearchParser\SearchParser;
use peckrob\SearchParser\SearchQuery;
use peckrob\SearchParser\SearchQueryComponent;

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
                'query' => 'from:foo@example.com',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => 'foo@example.com'
                    ]
                ]
            ],
            [
                'query' => '!from:foo@example.com',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => 'foo@example.com',
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
                'query' => 'from:foo@example.com "foo bar"',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => 'foo@example.com'
                    ],
                    [
                        'type' => 'text',
                        'field' => '',
                        'value' => 'foo bar'
                    ]
                ]
            ],
            [
                'query' => 'from:foo@example.com,bar@example.com',
                'return' => [
                    [
                        'type' => 'field',
                        'field' => 'from',
                        'value' => ['foo@example.com', 'bar@example.com']
                    ]
                ]
            ],
        ];
    }
}
