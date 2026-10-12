<?php
/**
 * csv-example.php — demo of CsvReader + CsvWriter.
 *
 * Usage: php examples/csv-example.php
 */
require __DIR__ . '/../src/CsvReader.php';
require __DIR__ . '/../src/CsvWriter.php';

// 1) Write a sample CSV with a header and a BOM (Excel-friendly).
$writer = new CsvWriter(__DIR__ . '/orders.csv', array('bom' => true));
$writer->writeHeader(array('Order ID', 'Customer', 'Total', 'Status'));
$writer->writeRows(array(
	array(101, 'Ayesha Khan', 149.50, 'paid'),
	array(102, 'Bilal Ahmed', 59.99, 'pending'),
	array(103, 'Fatima Noor', 210.00, 'paid'),
	array(104, 'Danish Raza', 12.75, 'refunded'),
));
$writer->close();
echo "Wrote {$writer->rowCount()} data rows to orders.csv\n\n";

// 2) Read it back, streaming, with a filter for paid orders over 100.
$reader = new CsvReader(__DIR__ . '/orders.csv', array(
	'filter' => function (array $row) {
		return $row['status'] === 'paid' && (float) $row['total'] > 100;
	},
));
echo "Headers: " . implode(', ', $reader->headers()) . "\n";
foreach ($reader->rows() as $row) {
	printf("  #%d %s — %s\n", $row['order_id'], $row['customer'], $row['total']);
}
echo "Consumed {$reader->rowCount()} rows (header excluded)\n";
