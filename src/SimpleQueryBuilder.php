<?php
	/**
	 * Created by PhpStorm.
	 * Project: PHPSimpleQueryBuilder
	 * User: kitayama
	 * Date: 2021/11/13
	 * Time: 16:24
	 */
	
	namespace SQB;
	
	use PDO;
	use Exception;
	
	/**
	 * @method SimpleQueryBuilder and (string $string) $where[]に蓄積する
	 */
	class SimpleQueryBuilder {
		
		private array  $select          = [];
		private array  $params          = [];
		private array  $where           = [];
		private string $from            = '';
		private array  $order           = [];
		private string $order_direction = 'ASC';
		private string $method;
		private array  $set             = [];
		private array  $fields          = [];
		private array  $values          = [];
		private string $key;
		private string $key_value;
		private PDO    $pdo;
		private array  $group           = [];
		
		public function __construct(?PDO $pdo) {
			$this->params = $this->parse(func_get_args());
			if (isset($pdo)) {
				$this->pdo = $pdo;
			}
		}
		
		private function parse(array $arguments): array {
			$params = [];
			foreach ($arguments as $arg) {
				if (is_array($arg)) {
					$params[] = implode(',', $this->parse($arg));
				} else if (is_object($arg) && get_class($arg) === get_class($this)) {
					$params[] = sprintf('(%s)', $arg->build());
				} else {
					$params[] = $arg;
				}
			}
			return $params;
		}
		
		public function __call($name, $arguments) {
			if (strtolower($name) == 'and') {
				array_push($this->where, implode(' ', $arguments));
			} else {
				$this->params = array_merge(
					$this->params, $this->parse(array_merge(array_map('strtoupper', explode('_', $name)), $arguments)));
			}
			return $this;
		}
		
		public function order_direction(string $direction): self {
			$this->order_direction = $direction;
			return $this;
		}
		
		public function order(?string $order_column): self {
			if (!empty($order_column)) {
				array_push($this->order, $order_column);
			}
			return $this;
		}
		
		public function group(?string $group_column): self {
			if (!empty($group_column)) {
				array_push($this->group, $group_column);
			}
			return $this;
		}
		
		
		/**
		 * @param string|null $arg
		 *
		 * @return SimpleQueryBuilder
		 */
		public function where(?string $arg): self {
			if (!empty($arg)) {
				array_push($this->where, $arg);
			}
			return $this;
		}
		
		/**
		 * SQLステートメント書き出し
		 * 20210921 改修
		 *
		 * @return string
		 * @throws Exception
		 */
		public function build(): string {
			$ret = 'not implemented for ' . $this->method;
			if (empty($this->select)) {
				//				throw new Exception("QueryBuilder: no SELECT field specified.");
			}
			if (empty($this->from)) {
				//				throw new Exception("QueryBuilder: no FROM table specified.");
			}
			
			$update_values_arr = [];
			for ($i = 0; $i < sizeof($this->fields); $i++) {
				array_push($update_values_arr, "{$this->fields[$i]} = {$this->values[$i]}");
			}
			$update_values = implode(', ', $update_values_arr);
			// Generate SELECT
			if ($this->method == 'select') {
				$ret = "SELECT ";
				$ret .= implode(', ', $this->select);
				$ret .= " FROM $this->from";
				if (!empty($this->where)) {
					// Generate WHERE
					$ret .= " WHERE ";
					$ret .= implode(' AND ', $this->where);
				}
				if (!empty($this->group)) {
					// Generate GROUP
					$ret .= " GROUP BY ";
					$ret .= implode(', ', $this->group);
				}
				if (!empty($this->order)) {
					// Generate ORDER
					$ret .= " ORDER BY ";
					$ret .= implode(', ', $this->order);
					$ret .= " $this->order_direction";
				}
			} else if ($this->method == 'update') {
				$ret = "UPDATE " . $this->from . ' SET ';
				$ret .= $update_values;
				$ret .= " WHERE " . implode(' AND ', $this->where);
			} else if ($this->method === 'upsert') {
				if (sizeof($this->fields) < 1 || sizeof($this->values) < 1) {
					throw new Exception('upsert params are not specified yet');
				}
				$insert_fields = implode(', ', $this->fields);
				$insert_values = implode(', ', $this->values);
				
				if (empty($this->key) || empty($this->key_value)) {
					$ret = "INSERT INTO $this->from ($insert_fields)";
					$ret .= " VALUES ($insert_values)";
				} else {
					$ret = "INSERT INTO $this->from ($this->key, $insert_fields)";
					$ret .= " VALUES ($this->key_value, $insert_values)";
				}
				$ret .= " ON DUPLICATE KEY UPDATE $update_values";
			}
			return $ret;
		}
		
		/**
		 * @param string $arg
		 *
		 * @return SimpleQueryBuilder
		 * @throws Exception
		 */
		public function select(string $arg): self {
			if (!empty($this->method) && $this->method != 'select') {
				throw new Exception('method has been chosen already:' . $this->method);
			}
			$this->method = 'select';
			// $arg_list     = explode(',', str_replace(' ', '', $arg));
			$arg_list = explode(',', str_replace('  ', ' ', $arg));
			foreach ($arg_list as $v) {
				array_push($this->select, $v);
			}
			return $this;
		}
		
		public function select_long(string $arg): self {
			if (!empty($this->method) && $this->method != 'select') {
				throw new Exception('method has been chosen already:' . $this->method);
			}
			$this->method = 'select';
			array_push($this->select, $arg);
			return $this;
		}
		
		public function update(string $arg): self {
			$this->set_method('update');
			$this->from = $arg;
			return $this;
		}
		
		private function set_method(string $method) {
			if (empty($this->method)) {
				$this->method = $method;
			} else {
				throw new Exception('method has been chosen already:' . $this->method);
			}
		}
		
		public function upsert(string $arg): self {
			$this->set_method('upsert');
			$this->from = $arg;
			return $this;
		}
		
		public function key(string $key, string $value): self {
			if ($this->method !== 'upsert') {
				throw new Exception('key() must be called with upsert method');
			}
			$this->key       = $key;
			$this->key_value = $value;
			return $this;
		}
		
		public function set(string $arg1, string|int|float $arg2): self {
			if (gettype($arg2) == 'string') {
				$arg2 = $this->pdo->quote($arg2);
			}
			array_push($this->fields, $arg1);
			array_push($this->values, $arg2);
			return $this;
		}
		
		/**
		 * @param string $arg
		 *
		 * @return SimpleQueryBuilder
		 */
		public function from(string $arg): self {
			$this->from = $arg;
			return $this;
		}
	}