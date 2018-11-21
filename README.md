SearchParser
============
SearchParser is a parser that converts a freeform query into an intermediate object, that can then be converted to query many backends (SQL, ElasticSearch, etc). It supports a freeform natural-language search as commonly found on many sites across the web.

For example, the following query:

```
from:foo@example.com "bar baz" !meef date:2018/01/01-2018/08/01
```

Is tokenized into a `SearchQuery` object containing a series of `SearchQueryComponents` that represent each logical component of the search query:

```
$q = new \peckrob\SearchParser\SearchParser();
$x = $q->parse($query);
print_r($x);

peckrob\SearchParser\SearchQuery Object
(
    [position:peckrob\SearchParser\SearchQuery:private] => 0
    [data:protected] => Array
        (
            [0] => peckrob\SearchParser\SearchQueryComponent Object
                (
                    [type] => field
                    [field] => from
                    [value] => foo@example.com
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [1] => peckrob\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => bar baz
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [2] => peckrob\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => meef
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] => 1
                )

            [3] => peckrob\SearchParser\SearchQueryComponent Object
                (
                    [type] => range
                    [field] => date
                    [value] =>
                    [firstRangeValue] => 2018/01/01
                    [secondRangeValue] => 2018/08/01
                    [negate] =>
                )

        )
)
```

## License

MIT
