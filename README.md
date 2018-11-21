SearchParser
============
SearchParser is a parser that converts a freeform query into an intermediate object, that can then be converted to query many backends (SQL, ElasticSearch, etc). It supports a freeform natural-language search as commonly found on many sites across the web.

For example, the following query:

```
from:me@rebeccapeck.org "bar baz" !meef date:2018/01/01-2018/08/01
```

Is tokenized into a `SearchQuery` object containing a series of `SearchQueryComponents` that represent each logical component of the search query:

```
$q = new \rebeccathedev\SearchParser\SearchParser();
$x = $q->parse($query);
print_r($x);

rebeccathedev\SearchParser\SearchQuery Object
(
    [position:rebeccathedev\SearchParser\SearchQuery:private] => 0
    [data:protected] => Array
        (
            [0] => rebeccathedev\SearchParser\SearchQueryComponent Object
                (
                    [type] => field
                    [field] => from
                    [value] => me@rebeccapeck.org
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [1] => rebeccathedev\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => bar baz
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] =>
                )

            [2] => rebeccathedev\SearchParser\SearchQueryComponent Object
                (
                    [type] => text
                    [field] =>
                    [value] => meef
                    [firstRangeValue] =>
                    [secondRangeValue] =>
                    [negate] => 1
                )

            [3] => rebeccathedev\SearchParser\SearchQueryComponent Object
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
