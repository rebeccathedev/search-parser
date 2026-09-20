# 🔍 SearchParser

A powerful search query parser that transforms freeform search queries into structured objects for querying SQL databases, Eloquent models, or other backends.

## ✨ Features

- 🎯 Parse Google-style search queries with field names, ranges, negation, and more
- 🗄️ Built-in transforms for SQL (PDO) and Laravel Eloquent
- 🔌 Extensible parser system for custom query types
- 🔒 Field filtering and mapping for security
- 🔎 Loose mode for fuzzy matching
- 🧠 Boolean expressions with `AND`, `OR`, `NOT`, and parentheses
- 🩺 Structured parse diagnostics and strict mode
- 🧱 Typed, allowlisted field schemas with column mapping
- 🔐 Parameterized SQL output and Elasticsearch DSL
- 🚀 PHP 8.5+ with modern type safety

## 🚀 Quick Example

```php
use RebeccaTheDev\SearchParser\SearchParser;

$parser = new SearchParser();
$query = $parser->parse('from:me@rebeccapeck.org "bar baz" !meef date:2018/01/01..2018/08/01');
```

This tokenizes the search into a `SearchQuery` object with structured components:

```
RebeccaTheDev\SearchParser\SearchQuery Object
(
    [position:RebeccaTheDev\SearchParser\SearchQuery:private] => 0
    [data:protected] => Array
        (
            [0] => RebeccaTheDev\SearchParser\SearchQueryComponent Object
                (
                    [type] => field
                    [field] => from
                    [value] => me@rebeccapeck.org
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [1] => RebeccaTheDev\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => bar baz
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [2] => RebeccaTheDev\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => meef
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] => 1
                )

            [3] => RebeccaTheDev\SearchParser\SearchQueryComponent Object
                (
                    [type] => range
                    [field] => date
                    [value] =>
                    [firstRangeValue] => 2018/01/01
                    [secondRangeValue] => 2018/08/01
                    [negate] =>
                )

            [4] => RebeccaTheDev\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => #hashtag
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

        )

)
```

## 📦 Installation

```bash
composer require rebeccathedev/search-parser
```

**Requirements:** PHP 8.5+

No external dependencies required for core functionality. Eloquent transform requires `illuminate/database`.

## 📖 Usage

### Basic Parsing

```php
use RebeccaTheDev\SearchParser\SearchParser;

$parser = new SearchParser();
$query = $parser->parse('from:me@rebeccapeck.org "exact phrase" !excluded');
```

The `SearchQuery` object is iterable:

```php
foreach ($query as $component) {
    echo $component->type;  // 'field', 'text', 'range'
    echo $component->value;
}
```

For diagnostics and the boolean expression tree, use `parseResult()`:

```php
$result = $parser->parseResult('status:active AND (role:admin OR NOT role:editor)');

if (!$result->isValid()) {
    foreach ($result->diagnostics as $diagnostic) {
        echo "{$diagnostic->message} at {$diagnostic->offset}";
    }
}
```

Supported field operators include comparisons, explicit ranges, open ranges,
null checks, and existence checks:

```text
age:>=21 price:<100 created:2026-01-01.. score:..20
deleted:null email:*
```

The legacy numeric `field:1-10` range syntax remains supported. Prefer `..`
for new integrations because it is unambiguous with dates and hyphenated text.

Backslashes escape syntax characters, including quotes, colons, commas, and
parentheses.

### Typed field schemas

Schemas combine field allowlisting, value coercion, validation, and safe mapping:

```php
use RebeccaTheDev\SearchParser\Schema\{Field, Schema};

$schema = new Schema([
    'age' => Field::integer(),
    'active' => Field::boolean(),
    'created' => Field::date('created_at'),
    'status' => Field::enum(['draft', 'published']),
    'author' => Field::integer('user_id'),
]);

$validated = $schema->apply($query);
```

### 🔧 Custom Parsers

Extend the parser by implementing the `Parser` interface:

```php
use RebeccaTheDev\SearchParser\Parsers\Parser;
use RebeccaTheDev\SearchParser\SearchQueryComponent;

class HashtagParser implements Parser {
    public function parsePart(string $part): SearchQueryComponent {
        $component = new SearchQueryComponent();

        if (preg_match('!#(.*)!', $part, $match)) {
            $component->type = 'hashtag';
            $component->value = $match[1];
        }

        return $component;
    }
}

// Use it
$parser = new SearchParser();
$parser->addParser(new HashtagParser());
$query = $parser->parse('search #trending');
```

See `src/Parsers/Hashtag.php` for a working example. Note: parsers don't fall through - if your parser handles a part, processing moves to the next part.

## 🔄 Transforms

Transform parsed queries into SQL WHERE clauses or Eloquent query builders.

### 💾 SQL Transform

```php
use RebeccaTheDev\SearchParser\Transforms\SQL\SQL;

$pdo = new PDO("sqlite:/tmp/database.db");
$transform = new SQL('default_field', $pdo);

$query = $parser->parse('from:me@rebeccapeck.org "bar baz" !meef date:2018/01/01..2018/08/01');
$where = $transform->transform($query);

// Result:
// `from` = me@rebeccapeck.org' and `default_field` = 'bar baz' and
// `default_field` != 'meef' and (`date` between '2018/01/01' and '2018/08/01')
```

For application queries, prefer bound output over an interpolated clause:

```php
$query = $parser->parseResult('status:active AND age:>=21')->query;
$result = (new SQL('default_field'))->transformBound($query);

// $result->clause  => "(`status` = :p1 AND `age` >= :p2)"
// $result->bindings => [':p1' => 'active', ':p2' => '21']
```

`transformBound()` validates field identifiers and does not require a PDO
connection. The original `transform()` API remains available for compatibility.

### ✨ Eloquent Transform

```php
use RebeccaTheDev\SearchParser\Transforms\Eloquent\Eloquent;

$users = User::query();
$transform = new Eloquent('name', $users);

$query = $parser->parse('status:active age:25-35');
$users = $transform->transform($query)->get();
```

### 🔎 Loose Mode

Enable fuzzy matching with `LIKE` queries:

```php
$transform = new SQL('default_field', $pdo);
$transform->looseMode = true;
$where = $transform->transform($query);

// Result:
// `from` = me@rebeccapeck.org' and `default_field` like '%bar baz%' and
// `default_field` not like '%meef%' and (`date` between '2018/01/01' and '2018/08/01')
```

### 🎨 Custom Component Transforms

Add custom transforms for your custom parsers:

```php
use RebeccaTheDev\SearchParser\Transforms\SQL\Hashtag;

$pdo = new PDO("sqlite:/tmp/database.db");
$transform = new SQL('default_field', $pdo);
$transform->addComponentTransform(new Hashtag('default_field', $pdo));

$query = $parser->parse('search #trending');
$where = $transform->transform($query);

// Result: `default_field` = 'search' and `hashtag` = 'trending'
```

See `src/Transforms/SQL/Hashtag.php` for a working example.

### Elasticsearch Transform

```php
use RebeccaTheDev\SearchParser\Transforms\Elasticsearch\Elasticsearch;

$query = $parser->parseResult('status:active OR age:>=21')->query;
$dsl = (new Elasticsearch('content'))->transform($query);
```

The adapter produces Elasticsearch `bool`, `term`, `range`, `wildcard`, and
`exists` queries while preserving boolean grouping.

## 🛡️ Filters

### ⚠️ Security Note

**Important:** The SQL transform escapes *values* but not *field names*. Always allowlist allowed fields before passing queries to transforms. Never trust user input for field names.

### 🎯 FieldFilter

Allowlist allowed fields for security:

```php
use RebeccaTheDev\SearchParser\Filters\{Filter, FieldFilter};

$filter = new Filter();
$fieldFilter = new FieldFilter();
$fieldFilter->validFields = ['from', 'to', 'subject', 'date'];
$filter->addFilter($fieldFilter);

$query = $parser->parse('from:me@rebeccapeck.org invalid:malicious subject:test');
$filtered = $filter->filter($query);

// Only 'from' and 'subject' fields are kept, 'invalid' is removed
```

### 🗺️ FieldNameMapper

Map user-facing field names to database column names:

```php
use RebeccaTheDev\SearchParser\Filters\{Filter, FieldNameMapper};

$filter = new Filter();
$mapper = new FieldNameMapper();
$mapper->mappingFields = [
    'date' => 'created_at',
    'author' => 'user_id'
];
$filter->addFilter($mapper);

$query = $parser->parse('date:2024-01-01-2024-12-31 author:123');
$filtered = $filter->filter($query);

// 'date' becomes 'created_at', 'author' becomes 'user_id'
```

### ⚙️ Custom Filters

Implement the `FiltersQueries` interface:

```php
use RebeccaTheDev\SearchParser\Filters\FiltersQueries;
use RebeccaTheDev\SearchParser\SearchQuery;

class MyCustomFilter implements FiltersQueries {
    public function filter(SearchQuery $query): SearchQuery {
        foreach ($query as $component) {
            // Your custom filtering logic
        }
        return $query;
    }
}

$filter = new Filter();
$filter->addFilter(new MyCustomFilter());
```

Useful `SearchQuery` methods:
- `remove(SearchQueryComponent $item)` - Remove a component
- `replace(SearchQueryComponent $old, SearchQueryComponent $new)` - Replace a component
- `merge(SearchQuery $query)` - Merge two queries

## 🧪 Testing

```bash
composer install
./vendor/bin/phpunit
```

Some tests may be skipped if optional dependencies (like Eloquent) aren't installed.

## 📄 License

MIT License - see LICENSE file for details.

## 👩‍💻 Author

Made with 🩷 by [Rebecca Peck](https://github.com/rebeccathedev)


[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/Q3W726YTHU)
