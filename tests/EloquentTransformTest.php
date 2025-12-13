<?php

namespace RebeccaTheDev\SearchParser\SearchParser\Tests;

use RebeccaTheDev\SearchParser\SearchParser;
use RebeccaTheDev\SearchParser\Transforms\Eloquent\Eloquent as ElqouentTransform;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;

class EloquentTransformTest extends \PHPUnit\Framework\TestCase {

    #[DataProvider('dataProvider')]
    public function testParse($query, $return, $loose_mode = false, $default_field = 'foo') {

        if (!class_exists('Illuminate\Database\Eloquent\Builder')) {
            $this->markTestSkipped(
                'Eloquent is not installed.'
              );
        }

        $mock = $this->getMockBuilder(Builder::class)
            ->onlyMethods(['where', 'orWhere', 'whereNot', 'whereBetween', 'whereNotBetween'])
            ->getMock();

        if (is_array($return)) {
            foreach ($return as $r) {
                $m = $mock->expects($this->exactly($r['count']))
                    ->method($r['method']);

                if (!empty($r['with'])) {
                    $expectations = array_map(function($value) {
                        return $this->equalTo($value);
                    }, $r['with']);
                    \call_user_func_array([$m, 'with'], $expectations);
                } else if (!empty($r['withConsecutive'])) {
                    $consecutiveExpectations = array_map(function($callArgs) {
                        return array_map(function($value) {
                            return $this->equalTo($value);
                        }, $callArgs);
                    }, $r['withConsecutive']);
                    \call_user_func_array([$m, 'withConsecutive'], $consecutiveExpectations);
                }

            }
        }

        $parser = new SearchParser();
        $data = $parser->parse($query);

        if (is_bool($data)) {
            $this->assertEquals($data, $return);
        } else {
            $transform = new ElqouentTransform($default_field, $mock);
            $transform->looseMode = $loose_mode;
            $transform->transform($data);
        }
    }

    public static function dataProvider() {
        return [
            [
                'query' => '',
                'return' => false
            ],
            [
                'query' => 'from:me@rebeccapeck.org',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => ['from', '=', me@rebeccapeck.org']
                    ]
                ]
            ],
            [
                'query' => '!from:me@rebeccapeck.org',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => ['from', '!=', me@rebeccapeck.org']
                    ]
                ]
            ],
            [
                'query' => 'range:1-10',
                'return' => [
                    [
                        'method' => 'whereBetween',
                        'count' => 1,
                        'with' => ['range', ['1', '10']]
                    ]
                ]
            ],
            [
                'query' => '"foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => ['foo', '=', 'foo bar']
                    ]
                ]
            ],
            [
                'query' => 'from:me@rebeccapeck.org "foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 2,
                        'withConsecutive' => [
                            ['from', '=', me@rebeccapeck.org'],
                            ['foo', '=', 'foo bar']
                        ]
                    ]
                ]
            ],
            [
                'query' => 'from:me@rebeccapeck.org,me@rebeccapeck.org',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => ['from', '=', me@rebeccapeck.org']
                    ],
                    [
                        'method' => 'orWhere',
                        'count' => 1,
                        'with' => ['from', '=', me@rebeccapeck.org']
                    ]
                ]
            ],
            [
                'query' => '"foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => ['foo', 'like', '%foo bar%']
                    ]
                ],
                'loose_mode' => true
            ],
        ];
    }
}
