<?php

namespace rebeccathedev\SearchParser\SearchParser\Tests;

use rebeccathedev\SearchParser\SearchParser;
use rebeccathedev\SearchParser\Transforms\Eloquent\Eloquent as ElqouentTransform;
use Illuminate\Database\Eloquent\Builder;

class EloquentTranformTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider dataProvider
     */
    public function testParse($query, $return, $loose_mode = false, $default_field = 'foo') {

        if (!class_exists('Illuminate\Database\Eloquent\Builder')) {
            $this->markTestSkipped(
                'Eloquent is not installed.'
              );
        }

        $mock = $this->getMockBuilder(Builder::class)
            ->setMethods(['where', 'orWhere', 'whereNot', 'whereBetween', 'whereNotBetween'])
            ->getMock();

        if (is_array($return)) {
            foreach ($return as $r) {
                $m = $mock->expects($this->exactly($r['count']))
                    ->method($r['method']);
                
                if (!empty($r['with'])) {
                    \call_user_func_array([$m, 'with'], $r['with']);
                } else if (!empty($r['withConsecutive'])) {
                    \call_user_func_array([$m, 'withConsecutive'], $r['withConsecutive']);
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
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo(me@rebeccapeck.org')]
                    ]
                ]
            ],
            [
                'query' => '!from:me@rebeccapeck.org',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('!='), $this->equalTo(me@rebeccapeck.org')]
                    ]
                ]
            ],
            [
                'query' => 'range:1-10',
                'return' => [
                    [
                        'method' => 'whereBetween',
                        'count' => 1,
                        'with' => [$this->equalTo('range'), $this->equalTo(['1', '10'])]
                    ]
                ]
            ],
            [
                'query' => '"foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('foo'), $this->equalTo('='), $this->equalTo('foo bar')]
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
                            [$this->equalTo('from'), $this->equalTo('='), $this->equalTo(me@rebeccapeck.org')],
                            [$this->equalTo('foo'), $this->equalTo('='), $this->equalTo('foo bar')]
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
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo(me@rebeccapeck.org')]
                    ],
                    [
                        'method' => 'orWhere',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo(me@rebeccapeck.org')]
                    ]
                ]
            ],
            [
                'query' => '"foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('foo'), $this->equalTo('like'), $this->equalTo('%foo bar%')]
                    ]
                ],
                'loose_mode' => true
            ],
        ];
    }
}
