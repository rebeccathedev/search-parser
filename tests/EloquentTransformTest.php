<?php

namespace RebeccaTheDev\SearchParser\SearchParser\Tests;

use RebeccaTheDev\SearchParser\SearchParser;
use RebeccaTheDev\SearchParser\Transforms\Eloquent\Eloquent as ElqouentTransform;
use RebeccaTheDev\SearchParser\Parsers\Hashtag as HashtagParser;
use RebeccaTheDev\SearchParser\Transforms\Eloquent\Hashtag as HashtagTransform;
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

    public function testMultipleFieldValuesAreGrouped(): void {
        $builder = new RecordingBuilder();
        $parser = new SearchParser();
        $query = $parser->parse('status:active role:admin,editor verified:true');

        $transform = new ElqouentTransform('name', $builder);
        $transform->transform($query);

        $this->assertSame([
            ['where', ['status', '=', 'active']],
            ['whereGroup', [
                ['where', ['role', '=', 'admin']],
                ['orWhere', ['role', '=', 'editor']],
            ]],
            ['where', ['verified', '=', 'true']],
        ], $builder->calls);
    }

    public function testHashtagComponentTransform(): void {
        $builder = new RecordingBuilder();
        $parser = new SearchParser();
        $parser->addParser(new HashtagParser());
        $query = $parser->parse('#php');

        $transform = new ElqouentTransform('name', $builder);
        $hashtagTransform = new HashtagTransform();
        $hashtagTransform->hashtagField = 'tag_name';
        $transform->addComponentTransform($hashtagTransform);
        $transform->transform($query);

        $this->assertSame([
            ['where', ['tag_name', 'php']],
        ], $builder->calls);
    }

    public function testBooleanExpressionTreeIsGrouped(): void {
        $builder = new RecordingBuilder();
        $query = (new SearchParser())->parseResult('status:active AND (role:admin OR NOT role:editor)')->query;

        (new ElqouentTransform('name', $builder))->transform($query);

        $this->assertSame([
            ['where', ['status', '=', 'active']],
            ['whereGroup', [
                ['whereGroup', [['where', ['role', '=', 'admin']]]],
                ['orWhereGroup', [['whereNotGroup', [['where', ['role', '=', 'editor']]]]]],
            ]],
        ], $builder->calls);
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
                        'method' => 'whereGroup',
                        'count' => 1,
                        'with' => [
                            ['where', ['from', '=', me@rebeccapeck.org']],
                            ['orWhere', ['from', '=', me@rebeccapeck.org']],
                        ]
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
            [
                'query' => 'age:>=21',
                'return' => [[
                    'method' => 'where',
                    'count' => 1,
                    'with' => ['age', '>=', '21'],
                ]],
            ],
            [
                'query' => 'created:2026-01-01..',
                'return' => [[
                    'method' => 'where',
                    'count' => 1,
                    'with' => ['created', '>=', '2026-01-01'],
                ]],
            ],
            [
                'query' => 'deleted:null',
                'return' => [[
                    'method' => 'whereNull',
                    'count' => 1,
                    'with' => ['deleted'],
                ]],
            ],
        ];
    }
}

class RecordingBuilder {
    public array $calls = [];

    public function __call(string $method, array $arguments): self {
        if (in_array($method, ['where', 'orWhere', 'whereNot'], true) && isset($arguments[0]) && $arguments[0] instanceof \Closure) {
            $nested = new self();
            $arguments[0]($nested);
            $this->calls[] = [$method . 'Group', $nested->calls];

            return $this;
        }

        $this->calls[] = [$method, $arguments];

        return $this;
    }
}
