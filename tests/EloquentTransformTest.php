<?php

namespace peckrob\SearchParser\SearchParser\Tests;

use peckrob\SearchParser\SearchParser;
use peckrob\SearchParser\Transforms\Eloquent;
use Illuminate\Database\Eloquent\Builder;

class EloquentTranformTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider dataProvider
     */
    public function testParse($query, $return, $default_field = 'foo') {

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
            $transform = new Eloquent();
            $transform->transform($data, $default_field, $mock);
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
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo('foo@example.com')]
                    ]
                ]
            ],
            [
                'query' => '!from:foo@example.com',
                'return' => [
                    [
                        'method' => 'whereNot',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo('foo@example.com')]
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
                'query' => 'from:foo@example.com "foo bar"',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 2,
                        'withConsecutive' => [
                            [$this->equalTo('from'), $this->equalTo('='), $this->equalTo('foo@example.com')],
                            [$this->equalTo('foo'), $this->equalTo('='), $this->equalTo('foo bar')]
                        ]
                    ]
                ]
            ],
            [
                'query' => 'from:foo@example.com,bar@example.com',
                'return' => [
                    [
                        'method' => 'where',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo('foo@example.com')]
                    ],
                    [
                        'method' => 'orWhere',
                        'count' => 1,
                        'with' => [$this->equalTo('from'), $this->equalTo('='), $this->equalTo('bar@example.com')]
                    ]
                ]
            ],
        ];
    }
}
