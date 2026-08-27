<?php

namespace RebeccaTheDev\SearchParser\SearchParser\Tests;

use PHPUnit\Framework\TestCase;
use RebeccaTheDev\SearchParser\Expressions\LogicalExpression;
use RebeccaTheDev\SearchParser\Expressions\NotExpression;
use RebeccaTheDev\SearchParser\Schema\Field;
use RebeccaTheDev\SearchParser\Schema\Schema;
use RebeccaTheDev\SearchParser\SearchParser;
use RebeccaTheDev\SearchParser\Transforms\Elasticsearch\Elasticsearch;
use RebeccaTheDev\SearchParser\Transforms\SQL\SQL;

final class RoadmapFeaturesTest extends TestCase {
    public function testDiagnosticsAndStrictMode(): void {
        $parser = new SearchParser();
        $result = $parser->parseResult('title:"unclosed');

        self::assertFalse($result->isValid());
        self::assertSame('Unclosed quoted string.', $result->diagnostics[0]->message);
        self::assertNotFalse($result->query);
        self::assertFalse($parser->parseResult('title:"unclosed', strict: true)->query);
    }

    public function testEscapedSyntaxIsPreservedAsLiteralText(): void {
        $query = (new SearchParser())->parse('title:"She said \\"hello\\"" path:foo\\:bar label:red\\,blue');
        $components = $query->toArray();

        self::assertSame('She said "hello"', $components[0]->value);
        self::assertSame('foo:bar', $components[1]->value);
        self::assertSame('red,blue', $components[2]->value);
    }

    public function testComparisonsExplicitAndOpenRanges(): void {
        $query = (new SearchParser())->parse('age:>=21 price:<100 created:2026-01-01.. score:..20');
        $components = $query->toArray();

        self::assertSame('>=', $components[0]->operator);
        self::assertSame('21', $components[0]->value);
        self::assertSame('<', $components[1]->operator);
        self::assertSame('2026-01-01', $components[2]->firstRangeValue);
        self::assertNull($components[2]->secondRangeValue);
        self::assertNull($components[3]->firstRangeValue);
        self::assertSame('20', $components[3]->secondRangeValue);
    }

    public function testNullAndExistenceOperators(): void {
        $components = (new SearchParser())->parse('deleted:null email:* !phone:missing')->toArray();
        self::assertSame('is-null', $components[0]->operator);
        self::assertSame('exists', $components[1]->operator);
        self::assertSame('is-null', $components[2]->operator);
        self::assertTrue($components[2]->negate);
    }

    public function testBooleanExpressionPrecedenceAndGrouping(): void {
        $result = (new SearchParser())->parseResult('status:active AND (role:admin OR NOT role:editor)');

        self::assertTrue($result->isValid());
        self::assertInstanceOf(LogicalExpression::class, $result->query->expression);
        self::assertSame('AND', $result->query->expression->operator);
        self::assertSame('OR', $result->query->expression->right->operator);
        self::assertInstanceOf(NotExpression::class, $result->query->expression->right->right);
    }

    public function testRepeatedNotOperatorsAreRightAssociative(): void {
        $result = (new SearchParser())->parseResult('NOT NOT status:active');
        self::assertTrue($result->isValid());
        self::assertInstanceOf(NotExpression::class, $result->query->expression);
        self::assertInstanceOf(NotExpression::class, $result->query->expression->expression);
    }

    public function testSchemaMapsAndCoercesFields(): void {
        $query = (new SearchParser())->parse('age:21 active:true created:2026-08-27 status:published author:42');
        $schema = new Schema([
            'age' => Field::integer(),
            'active' => Field::boolean(),
            'created' => Field::date('created_at'),
            'status' => Field::enum(['draft', 'published']),
            'author' => Field::integer('user_id'),
        ]);
        $result = $schema->apply($query);
        $components = $query->toArray();

        self::assertTrue($result->isValid());
        self::assertSame(21, $components[0]->value);
        self::assertTrue($components[1]->value);
        self::assertSame('created_at', $components[2]->field);
        self::assertSame('user_id', $components[4]->field);
        self::assertSame(42, $components[4]->value);
    }

    public function testSchemaReportsUnknownAndInvalidValues(): void {
        $query = (new SearchParser())->parse('age:nope secret:value');
        $result = (new Schema(['age' => Field::integer()]))->apply($query);

        self::assertFalse($result->isValid());
        self::assertCount(2, $result->diagnostics);
    }

    public function testQuerySerializationAndCount(): void {
        $result = (new SearchParser())->parseResult('one two');

        self::assertCount(2, $result->query);
        self::assertCount(2, $result->query->toArray());
        self::assertSame(2, count(json_decode(json_encode($result->query), true)['components']));
    }

    public function testParameterizedSqlHonorsBooleanAstAndOperators(): void {
        $query = (new SearchParser())->parseResult('status:active AND (age:>=21 OR deleted:null)')->query;
        $result = (new SQL('name'))->transformBound($query);

        self::assertSame('(`status` = :p1 AND (`age` >= :p2 OR `deleted` IS NULL))', $result->clause);
        self::assertSame([':p1' => 'active', ':p2' => '21'], $result->bindings);
    }

    public function testParameterizedSqlSupportsOpenRangesAndRejectsIdentifiers(): void {
        $parser = new SearchParser();
        $result = (new SQL('name'))->transformBound($parser->parse('created:2026-01-01..'));
        self::assertSame('`created` >= :p1', $result->clause);
        self::assertSame([':p1' => '2026-01-01'], $result->bindings);

        $this->expectException(\InvalidArgumentException::class);
        (new SQL('name'))->transformBound($parser->parse('bad-field:value'));
    }

    public function testNegatedComparisonsAndListsUseLogicalComplements(): void {
        $parser = new SearchParser();
        $comparison = (new SQL('name'))->transformBound($parser->parse('!age:>=21'));
        $list = (new SQL('name'))->transformBound($parser->parse('!role:admin,editor'));

        self::assertSame('`age` < :p1', $comparison->clause);
        self::assertSame('(`role` != :p1 AND `role` != :p2)', $list->clause);
    }

    public function testElasticsearchAdapterUsesBooleanDsl(): void {
        $query = (new SearchParser())->parseResult('status:active OR age:>=21')->query;
        $result = (new Elasticsearch('content'))->transform($query);

        self::assertSame(1, $result['query']['bool']['minimum_should_match']);
        self::assertSame(['term' => ['status' => 'active']], $result['query']['bool']['should'][0]);
        self::assertSame(['range' => ['age' => ['gte' => '21']]], $result['query']['bool']['should'][1]);
    }
}
