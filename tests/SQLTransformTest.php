<?php

namespace rebeccathedev\SearchParser\SearchParser\Tests;

use rebeccathedev\SearchParser\SearchParser;
use rebeccathedev\SearchParser\Transforms\SQL\SQL;

class SQLTranformTest extends \PHPUnit\Framework\TestCase {

    /**
     * @dataProvider dataProvider
     */
    public function testParse($query, $return, $loose_mode = false, $default_field = 'foo') {

        // Mock a PDO stub to do escaping.
        $stub = $this->getMockBuilder(\PDO::class)
                     ->disableOriginalConstructor()
                     ->disableOriginalClone()
                     ->disableArgumentCloning()
                     ->disallowMockingUnknownTypes()
                     ->getMock();

        $stub->method('quote')
             ->will($this->returnCallback(function($e) {
                 return "'$e'";
             }));

        $parser = new SearchParser();
        $search = $parser->parse($query);

        if (is_bool($search)) {
            $this->assertEquals($search, $return);
        } else {
            $transform = new SQL($default_field, $stub);
            $transform->looseMode = $loose_mode;
            $tranform_data = $transform->transform($search);
            $this->assertEquals($tranform_data, $return);
        }
    }

    public function dataProvider() {
        return [
            [
                'query' => '',
                'return' => ''
            ],
            [
                'query' => 'from:me@rebeccapeck.org',
                'return' => "`from` = me@rebeccapeck.org'"
            ],
            [
                'query' => '!from:me@rebeccapeck.org',
                'return' => "`from` != me@rebeccapeck.org'"
            ],
            [
                'query' => 'range:1-10',
                'return' => "(`range` between '1' and '10')"
            ],
            [
                'query' => '"foo bar"',
                'return' => "`foo` = 'foo bar'"
            ],
            [
                'query' => '"foo bar"',
                'return' => "`foo` like '%foo bar%'",
                'loose_mode' => true
            ],
            [
                'query' => 'from:me@rebeccapeck.org "foo bar"',
                'return' => "`from` = me@rebeccapeck.org' and `foo` = 'foo bar'"
            ],
            [
                'query' => 'from:me@rebeccapeck.org,me@rebeccapeck.org',
                'return' => "(`from` = me@rebeccapeck.org' or `from` = me@rebeccapeck.org')"
            ]
        ];
    }
}
