<?php
/**
 * CsvWriter — streaming CSV writer: rows go straight to the stream, one at a time.
 *
 *     $writer = new CsvWriter('php://output', array('bom' => true));
 *     $writer->writeRow(array('order_id', 'total'));
 *     foreach ($orders as $order) {
 *         $writer->writeRow(array($order['id'], $order['total']));
 *     }
 *     $writer->close();
 *
 * The BOM option writes "\xEF\xBB\xBF" first so Excel opens UTF-8 exports correctly.
 *
 * No dependencies, PHP 7.4+.
 */
class CsvWriter {

	/** @var resource|null */
	private $handle = null;
	/** @var array */
	private $options;
	/** @var int Rows written */
	private $rowCount = 0;

	/**
	 * @param string $path   File path, or a stream like 'php://output' / 'php://memory'.
	 * @param array  $options Optional:
	 *   'delimiter'  (default ',')
	 *   'enclosure'  (default '"')
	 *   'escape'     (default '\\')
	 *   'bom'        prepend UTF-8 BOM for Excel (default false)
	 *   'line_ending' "\n" or "\r\n" (default PHP_EOL)
	 */
	public function __construct($path, array $options = array()) {
		$this->options = array_merge(array(
			'delimiter'   => ',',
			'enclosure'   => '"',
			'escape'      => '\\',
			'bom'         => false,
			'line_ending' => PHP_EOL,
		), $options);
		$this->handle = fopen($path, 'wb');
		if ($this->handle === false) {
			throw new RuntimeException('Could not open CSV for writing: ' . $path);
		}
		if ($this->options['bom']) {
			fwrite($this->handle, "\xEF\xBB\xBF");
		}
	}

	public function __destruct() {
		$this->close();
	}

	/**
	 * Write one row (array of scalars). Nested values are JSON-encoded,
	 * booleans become 1/0, null becomes ''.
	 */
	public function writeRow(array $row) {
		$clean = array_map(array($this, 'cellToString'), array_values($row));
		$ok = fputcsv($this->handle, $clean, $this->options['delimiter'],
			$this->options['enclosure'], $this->options['escape']);
		if ($ok === false) {
			throw new RuntimeException('Failed writing CSV row #' . ($this->rowCount + 1));
		}
		$this->rowCount++;
	}

	/** Write many rows; $rows may be an array or a generator. */
	public function writeRows($rows) {
		foreach ($rows as $row) {
			$this->writeRow((array) $row);
		}
	}

	/** Write the header row from an array of column names. */
	public function writeHeader(array $columns) {
		$this->writeRow($columns);
	}

	public function rowCount() {
		return $this->rowCount;
	}

	public function close() {
		if (is_resource($this->handle)) {
			fclose($this->handle);
			$this->handle = null;
		}
	}

	private function cellToString($value) {
		if ($value === null) {
			return '';
		}
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		if (is_array($value) || is_object($value)) {
			return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		return (string) $value;
	}
}
