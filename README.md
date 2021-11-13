# php_simple_query_builder

## Simply, Light, and Easy query builder

You can build query strings with chain-method style. Currently, this app is made for MySQL ONLY.

### usage

```php
// Initialize
$qb = new SimpleQueryBuilder($pdo);

// for SELECT
$qb->select('field1')
   ->select('field2')
   ->select('field3')
   ->from('table_name')
   ->where('id = 1');
$sql = $qb->build();
// SELECT field1, field2, field3 FROM table_name WHERE id = 1
```

```php
// for UPSERT
$qb->upsert('table_name')
   ->key('id',2) 
   ->set('name','Shingo Kitayama')
   ->set('height', 177.5);
$sql = $qb->build();
// INSERT INTO table_name (id, name, height) VALUES (2, 'Shingo Kitayama', 177.5) ON DUPLICATE KEY UPDATE name = 'Shingo Kitayama', height = 177.5
```

