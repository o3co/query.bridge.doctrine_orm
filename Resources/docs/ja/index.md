# DoctrineOrm Bridge　コンポーネント

DoctrineOrm Bridgeは、Queryコンポーネントが作成するSimpleExpressionから、DoctrineOrm Queryを生成するためのPersisterを提供します。

DoctrineOrmPersisterを用いることで、CQLから、Doctrine Entityを取得することが可能です。

## 使用例

```php

$persister = new DoctrineOrmPersister();

// CriteriaParserから、Doctrine の検索結果を取得する。

$criteriaParser = new CriteriaParser(null, $persister);
$query = $criteriaParser->parse(array('fieldOne' => 1, 'fieldTwo' => array('abc', 'def'), '_offset' => 0, '_size' => 10));
$results = $query->getResult();


// CQLを使って複雑なクエリ結果を取得する。
$parser = new CqlParser(array('condition' => 'q'), $persister);

$queryStr = build_http_query(array('q' => 'and:(field:=:1 field2:>:2)', 'size' => 5, 'offset' => 0));
$query = $parser->parse($queryStr);
$results = $query->getResult();
```

------------------------------

[Query Component](https://github.com/o3co/query/blob/master/README.md)