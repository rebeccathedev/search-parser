<?php

namespace RebeccaTheDev\SearchParser;

use RebeccaTheDev\SearchParser\Parsers\Parser;
use RebeccaTheDev\SearchParser\Expressions\ComponentExpression;
use RebeccaTheDev\SearchParser\Expressions\Expression;
use RebeccaTheDev\SearchParser\Expressions\LogicalExpression;
use RebeccaTheDev\SearchParser\Expressions\NotExpression;

/**
 * A class that parses queries into tokens.
 */
class SearchParser {

    /**
     * An array that holds any additional parsers to run after the main parser
     * has run.
     *
     * @var array<Parser>
     */
    private array $parsers = [];

    /**
     * Converts a query string using a standardized vocabulary into a parsed
     * Query object.
     *
     * @return SearchQuery|false Either a SearchQuery object or false if it couldn't parse
     *                           anything. Really false should only ever be returned for an
     *                           empty string.
     */
    public function parse(string $query): SearchQuery|false {
        $parsed = $this->parseResult($query)->query;
        // Keep legacy object equality stable; consumers that need the AST use
        // parseResult().
        if ($parsed instanceof SearchQuery) {
            $parsed->expression = null;
        }
        return $parsed;
    }

    /** Parses a query and returns non-fatal syntax diagnostics alongside it. */
    public function parseResult(string $query, bool $strict = false): ParseResult {

        // The only case we should ever return false is if we had an empty
        // string. Pretty much anything else is a valid search.
        if (empty($query)) {
            return new ParseResult(false);
        }

        // Create a new SearchQuery object.
        $search = new SearchQuery();

        [$tokens, $diagnostics] = $this->tokenize($query);
        $expressionTokens = [];

        foreach ($tokens as $token) {
            $match = $token['value'];
            $upper = strtoupper($match);
            if (in_array($upper, ['AND', 'OR', 'NOT'], true) || $match === '(' || $match === ')') {
                $expressionTokens[] = $upper;
                continue;
            }

            $component = null;
            foreach ($this->parsers as $parser) {
                $parsed = $parser->parsePart($match);
                if (!$parsed->isEmpty()) {
                    $component = $parsed;
                    break;
                }
            }

            $component ??= $this->parsePart($match);
            $search->push($component);
            $expressionTokens[] = new ComponentExpression($component);
        }

        try {
            $search->expression = $this->buildExpression($expressionTokens);
        } catch (\InvalidArgumentException $exception) {
            $diagnostics[] = new ParseDiagnostic($exception->getMessage(), 0);
        }

        if ($strict && !empty($diagnostics)) {
            return new ParseResult(false, $diagnostics);
        }

        return new ParseResult($search, $diagnostics);
    }

    /**
     * Parses a part of a query into a SearchQueryComponent
     */
    public function parsePart(string $part): SearchQueryComponent {
        // Create a new component.
        $component = new SearchQueryComponent();

        // If it starts with a ! or a -, that means we are negating whatever
        // that token is. If it starts with a +, that means we are requiring it.
        // Calling code can interpret those in any way.
        $firstChar = $part[0] ?? '';

        match ($firstChar) {
            '!', '-' => [
                $component->negate = true,
                $part = substr($part, 1)
            ],
            '+' => [
                $component->require = true,
                $part = substr($part, 1)
            ],
            default => null
        };

        // Now, we look for field names and corresponding values.
        if (preg_match('/^([^:]+):(?:["\'])?(.*?)(?:["\'])?$/', $part, $inner_matches)) {
            $component->field = $this->unescape($inner_matches[1]);
            $encodedValue = $inner_matches[2];
            if (strlen($encodedValue) >= 2 && in_array($encodedValue[0], ['"', "'"], true) && $encodedValue[-1] === $encodedValue[0]) {
                $encodedValue = substr($encodedValue, 1, -1);
            }
            $rawValue = $this->unescape($encodedValue);

            if (preg_match('/^(>=|<=|!=|>|<|=)(.*)$/', $rawValue, $comparison)) {
                $component->type = SearchQueryComponent::FIELD;
                $component->operator = $comparison[1];
                $component->value = $comparison[2];
                return $component;
            }

            if (in_array(strtolower($rawValue), ['null', 'missing'], true)) {
                $component->type = SearchQueryComponent::FIELD;
                $component->operator = 'is-null';
                return $component;
            }

            if ($rawValue === '*') {
                $component->type = SearchQueryComponent::FIELD;
                $component->operator = 'exists';
                return $component;
            }

            if (str_contains($rawValue, '..')) {
                [$first, $second] = array_pad(explode('..', $rawValue, 2), 2, '');
                $component->type = SearchQueryComponent::RANGE;
                $component->firstRangeValue = $first !== '' ? $first : null;
                $component->secondRangeValue = $second !== '' ? $second : null;

            // Preserve the original numeric range syntax without treating
            // ISO dates and other hyphenated values as ranges.
            } elseif (preg_match('/^(-?\d+(?:\.\d+)?)-(-?\d+(?:\.\d+)?)$/', $rawValue, $range)) {
                $component->type = SearchQueryComponent::RANGE;
                $component->firstRangeValue = $range[1];
                $component->secondRangeValue = $range[2];

            } else {
                $component->type = SearchQueryComponent::FIELD;
                $parts = preg_split('/(?<!\\\\),/', $encodedValue);
                $component->value = count($parts) > 1
                    ? array_map($this->unescape(...), $parts)
                    : $rawValue;
            }

        // This is just a standard text lookup.
        } else {
            $part = $this->unescape(trim($part, "\"'"));
            $component->type = SearchQueryComponent::TEXT;
            $component->value = $part;
        }

        return $component;
    }

    /**
     * Adds a parser to run after the main parser.
     */
    public function addParser(Parser $parser): void {
        $this->parsers[] = $parser;
    }

    /** @return array{list<array{value:string,offset:int}>, list<ParseDiagnostic>} */
    private function tokenize(string $query): array {
        $tokens = $diagnostics = [];
        $buffer = '';
        $start = 0;
        $quote = null;
        $escaped = false;
        $flush = static function () use (&$tokens, &$buffer, &$start): void {
            if ($buffer !== '') {
                $tokens[] = ['value' => $buffer, 'offset' => $start];
                $buffer = '';
            }
        };

        for ($index = 0, $length = strlen($query); $index < $length; $index++) {
            $character = $query[$index];
            if ($escaped) { $buffer .= '\\' . $character; $escaped = false; continue; }
            if ($character === '\\') { $escaped = true; continue; }
            if ($quote !== null) {
                $buffer .= $character;
                if ($character === $quote) { $quote = null; }
                continue;
            }
            if ($character === '"' || $character === "'") { if ($buffer === '') { $start = $index; } $quote = $character; $buffer .= $character; continue; }
            if ($character === '(' || $character === ')') { $flush(); $tokens[] = ['value' => $character, 'offset' => $index]; continue; }
            if (ctype_space($character)) { $flush(); continue; }
            if ($buffer === '') { $start = $index; }
            $buffer .= $character;
        }
        if ($escaped) { $buffer .= '\\'; }
        $flush();
        if ($quote !== null) { $diagnostics[] = new ParseDiagnostic('Unclosed quoted string.', $start); }
        return [$tokens, $diagnostics];
    }

    /** @param list<Expression|string> $tokens */
    private function buildExpression(array $tokens): ?Expression {
        if ($tokens === []) { return null; }
        $output = $operators = [];
        $previousWasOperand = false;
        $precedence = ['OR' => 1, 'AND' => 2, 'NOT' => 3];
        foreach ($tokens as $token) {
            if ($token instanceof Expression) {
                if ($previousWasOperand) { $this->pushOperator('AND', $operators, $output, $precedence); }
                $output[] = $token; $previousWasOperand = true; continue;
            }
            if ($token === '(') {
                if ($previousWasOperand) { $this->pushOperator('AND', $operators, $output, $precedence); }
                $operators[] = $token; $previousWasOperand = false; continue;
            }
            if ($token === ')') {
                while (($operator = array_pop($operators)) !== null && $operator !== '(') { $output[] = $operator; }
                if ($operator === null) { throw new \InvalidArgumentException('Unmatched closing parenthesis.'); }
                $previousWasOperand = true; continue;
            }
            $this->pushOperator($token, $operators, $output, $precedence);
            $previousWasOperand = false;
        }
        while (($operator = array_pop($operators)) !== null) {
            if ($operator === '(') { throw new \InvalidArgumentException('Unclosed parenthesis.'); }
            $output[] = $operator;
        }
        $stack = [];
        foreach ($output as $token) {
            if ($token instanceof Expression) { $stack[] = $token; continue; }
            if ($token === 'NOT') { $operand = array_pop($stack) ?? throw new \InvalidArgumentException('NOT requires an expression.'); $stack[] = new NotExpression($operand); continue; }
            $right = array_pop($stack); $left = array_pop($stack);
            if (!$left || !$right) { throw new \InvalidArgumentException("$token requires two expressions."); }
            $stack[] = new LogicalExpression($token, $left, $right);
        }
        return count($stack) === 1 ? $stack[0] : throw new \InvalidArgumentException('Invalid boolean expression.');
    }

    private function pushOperator(string $operator, array &$operators, array &$output, array $precedence): void {
        while (($top = end($operators)) !== false && $top !== '(' && ($operator === 'NOT' ? $precedence[$top] > $precedence[$operator] : $precedence[$top] >= $precedence[$operator])) { $output[] = array_pop($operators); }
        $operators[] = $operator;
    }

    private function unescape(string $value): string {
        return preg_replace('/\\\\(.)/s', '$1', $value) ?? $value;
    }
}
