<?php

include 'vendor/autoload.php';
$query = 'from:foo@example.com "bar baz" !meef date:2018/01/01-2018/08/01 #hashtag';
$q = new \peckrob\SearchParser\SearchParser();
$q->addParser(new \peckrob\SearchParser\Parsers\Hashtag());
$x = $q->parse($query);

$pdo = new PDO("sqlite:/tmp/foo.sql");
$transform = new \peckrob\SearchParser\Transforms\SQL\SQL("default_field", $pdo);
$transform->addComponentTransform(new \peckrob\SearchParser\Transforms\SQL\Hashtag("default_field", $pdo));
$where = $transform->transform($x);

var_dump($where);

/*$filter = new \peckrob\SearchParser\Filters\Filter();
$field_filter = new \peckrob\SearchParser\Filters\FieldFilter();
$field_filter->validFields = ['from'];
$filter->addFilter($field_filter);
$filter->filter($x);*/

print_r($x);

/*
$pdo = new PDO("sqlite:/tmp/foo.sql");

$transform = new \peckrob\SearchParser\Transforms\SQL\SQL("default_field", $pdo);
$transform->looseMode = true;

$hashtag_transform = new \peckrob\SearchParser\Transforms\SQL\Hashtag("default_field", $pdo);
$transform->addComponentTransform($hashtag_transform);

$where = $transform->transform($x);
var_dump($where);
*/
