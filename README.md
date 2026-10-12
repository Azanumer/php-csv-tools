# PHP CSV Tools

Dependency-free, memory-safe CSV import/export for PHP. Streams row-by-row, so multi-GB files never touch your memory limit.

## Contents

| File | What it does |
|---|---|
| `src/CsvReader.php` | Streaming reader — header normalization (`"Order ID"` → `order_id`), BOM stripping, blank-line skipping, row filtering, short/long-row tolerance |
| `src/CsvWriter.php` | Streaming writer — optional Excel BOM, nested values JSON-encoded, `writeHeader()`/`writeRows()` |
| `examples/csv-example.php` | Working demo: write an orders CSV, read it back filtered |

## Usage

```php
require 'src/CsvReader.php';

$reader = new CsvReader('orders.csv', array(
    'filter' => function (array $row) {
        return (float) $row['total'] > 100;   // only rows you care about
    },
));
foreach ($reader->rows() as $row) {           // one row at a time
    printf("%s — %s\n", $row['order_id'], $row['total']);
}
```

```php
require 'src/CsvWriter.php';

$writer = new CsvWriter('php://output', array('bom' => true)); // direct to download
$writer->writeHeader(array('id', 'email'));
foreach ($users as $user) {
    $writer->writeRow(array($user['id'], $user['email']));
}
$writer->close();
```

Header keys are slugged for predictable access: `"Customer Name"` → `$row['customer_name']`. Rows with more cells than the header keep the extras in `$row['_extra']`; missing columns read as `null`.

Requires PHP 7.4+. MIT licensed.
