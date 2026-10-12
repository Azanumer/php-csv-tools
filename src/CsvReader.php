<?php
/**
 * CsvReader — memory-safe streaming CSV reader with header mapping and row filtering.
 *
 * Reads one row at a time, so multi-GB exports never blow the PHP memory limit.
 * Header names are normalized to slugs (lowercase, underscores) for predictable keys.
 *
 *     $reader = new CsvReader('orders.csv');
 *     foreach ($reader->rows() as $row) {
 *         echo $row['order_id'] . ' => ' . $row['total'] . "\n";
 *     }
 *
 * Filter rows without loading the whole file:
 *
 *     $reader = new CsvReader('orders.csv', array(
 *         'filter' => function (array $row) {
 *             return (float) $row['total'] > 100;
 *         },
 *     ));
 *
 * Handles: custom delimiter/enclosure/escape, CRLF vs LF, BOM stripping,
 * skipped blank lines, and rows longer/shorter than the header (extra cells
 * land in `$row['_extra']`, missing columns become null).
 *
 * No dependencies, PHP 7.4+.
 */
class CsvReader {

	const VERSION = '1.0.0';

	/** @var resource|null */
	private $handle = null;
	/** @var array Header keys in order */
	private $headers = array();
	/** @var array Runtime options */
	private $options;
	/** @var int Count of rows already yielded (excludes header) */
	private $rowCount = 0;

	/**
	 * @param string $path   Path to the CSV file.
	 * @param array  $options  Optional:
	 *   'delimiter'  (default ',')
	 *   'enclosure'  (default '"')
	 *   'escape'     (default '\\')
	 *   'header'     true = first row is the header (default), false = rows are numeric
	 *   'skip_blank' skip fully-empty rows (default true)
	 *   'filter'     callable(array $row): bool — only yield rows that pass
	 */
	public function __construct($path, array $options = array()) {
		if (!is_readable($path)) {
			throw new InvalidArgumentException('CSV file not readable: ' . $path);
		}
		$this->options = array_merge(array(
			'delimiter'  => ',',
			'enclosure'  => '"',
			'escape'     => '\\',
			'header'     => true,
			'skip_blank' => true,
			'filter'     => null,
		), $options);
		$this->handle = fopen($path, 'rb');
		if ($this->handle === false) {
			throw new RuntimeException('Could not open CSV file: ' . $path);
		}
		$this->readHeaders();
	}

	public function __destruct() {
		if (is_resource($this->handle)) {
			fclose($this->handle);
		}
	}

	/**
	 * Yield rows one at a time as associative (or numeric) arrays.
	 *
	 * @return Generator
	 */
	public function rows() {
		while (($cells = $this->readLine()) !== null) {
			$this->rowCount++;
			$row = $this->mapRow($cells);
			$filter = $this->options['filter'];
			if ($filter !== null && !call_user_func($filter, $row)) {
				continue;
			}
			yield $row;
		}
	}

	/** How many data rows have been consumed so far (excludes header). */
	public function rowCount() {
		return $this->rowCount;
	}

	/** The parsed header keys (empty when 'header' is false). */
	public function headers() {
		return $this->headers;
	}

	private function readHeaders() {
		if ($this->options['header'] === false) {
			// No header: leave the stream at the start; rows are numeric.
			rewind($this->handle);
			return;
		}
		$first = $this->readLine();
		if ($first !== null) {
			$this->headers = array_map(array($this, 'normalizeKey'), $first);
		}
	}

	/** Read the next non-empty line as an array of cells, or null at EOF. */
	private function readLine() {
		while (!feof($this->handle)) {
			$cells = fgetcsv($this->handle, 0, $this->options['delimiter'],
				$this->options['enclosure'], $this->options['escape']);
			if ($cells === false) {
				return null;
			}
			$isBlank = (count($cells) === 1 && trim((string) $cells[0]) === '');
			if ($isBlank && $this->options['skip_blank']) {
				continue;
			}
			return $cells;
		}
		return null;
	}

	/** Map numeric cells onto headers; extra cells -> '_extra', missing -> null. */
	private function mapRow(array $cells) {
		if (!$this->options['header'] || empty($this->headers)) {
			return array_values($cells);
		}
		$row = array();
		$count = count($this->headers);
		for ($i = 0; $i < $count; $i++) {
			$row[$this->headers[$i]] = isset($cells[$i]) ? $cells[$i] : null;
		}
		if (count($cells) > $count) {
			$row['_extra'] = array_slice($cells, $count);
		}
		return $row;
	}

	/** Normalize "Order ID" -> "order_id", also strips a leading BOM. */
	private function normalizeKey($key) {
		$key = preg_replace('/^\xEF\xBB\xBF/', '', (string) $key);
		$key = strtolower(trim($key));
		$key = preg_replace('/[^a-z0-9]+/', '_', $key);
		return trim($key, '_');
	}
}
