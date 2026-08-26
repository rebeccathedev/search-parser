<?php

namespace RebeccaTheDev\SearchParser\SearchParser\Tests;

use RebeccaTheDev\SearchParser\SearchParser;
use RebeccaTheDev\SearchParser\Transforms\Eloquent\Eloquent as ElqouentTransform;
use PHPUnit\Framework\Attributes\DataProvider;

class EloquentTransformTest extends \PHPUnit\Framework\TestCase {

    #[DataProvider('dataProvider')]
    public function testParse($query, $return, $loose_mode = false, $default_field = 'foo') {

        $builder = new RecordingBuilder();

        $parser = new SearchParser();
        $data = $parser->parse($query);

        if (is_bool($data)) {
            $this->assertEquals($data, $return);
        } else {
            $transform = new ElqouentTransform($default_field, $builder);
            $transform->looseMode = $loose_mode;
            $transform->transform($data);

            $expectedCalls = [];
            foreach ($return as $expectation) {
                if (!empty($expectation['with'])) {
                    $expectedCalls[] = [$expectation['method'], $expectation['with']];
                } else {
                    foreach ($expectation['withConsecutive'] as $arguments) {
                        $expectedCalls[] = [$expectation['method'], $arguments];
                    }
                }
            }

            $this->assertSame($expectedCalls, $builder->calls);
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

class RecordingBuilder {
    public array $calls = [];

    public function __call(string $method, array $arguments): self {
        $this->calls[] = [$method, $arguments];

        return $this;
    }
}
