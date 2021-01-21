<?php

require_once __DIR__ . '/rabbit.php';

class Analytics
{
	/**
	 * Слушатель очереди
	 *
	 * @param string $tag
	 * @return void
	 */
	public static function consumerNewUser($tag = '')
	{
		$consumer = Rabbit::checkConsumer(RABBIT_CHANNEL_NAME, $tag);
		if ($consumer) return;
		$callback = function ($msg) {
			$result = false;
			try {
				$data = json_decode($msg->body);
				$table = $data->table ?? '';
				$params = (array) $data->params ?? [];
				$db = new Analytics_Db();
				$result = (empty($table) || empty($params)) ? true : $db->insert($table, $params);
				unset($db);
			} catch (\Throwable $th) {
				Debug::print($th->getMessage());
				throw $th;
			} finally {
				if ($result) $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
				Debug::print([$data, $result]);
			}
		};
		try {
			$rabbit = new Rabbit(RABBIT_CHANNEL_NAME);
			$rabbit->consumer($callback, $tag);
		} catch (\Throwable $th) {
			Debug::print($th->getMessage());
			throw $th;
		}
	}
}

class Analytics_Db
{
	private $_conn = null;
	private $_sth = null;

	public function __construct()
	{
		try {
			$this->_conn = new mysqli(ANALYTICS_DB_HOST, ANALYTICS_DB_LOGIN, ANALYTICS_DB_PSWD, ANALYTICS_DB_NAME, ANALYTICS_DB_PORT);
			if ($this->_conn->connect_error) {
				Debug::print([
					'target' => __METHOD__,
					'message' => 'Ошибка подключения (' . $this->_conn->connect_errno . ') ' . $this->_conn->connect_error
				]);
			}
		} catch (Exception $ex) {
			Debug::print([
				'target' => __METHOD__,
				'message' => $ex->getMessage()
			]);
			exit;
		}
	}

	public function query($sql)
	{
		try {
			$this->_sth = $this->_conn->query($sql);
		} catch (Exception $ex) {
			Debug::print([
				'target' => __METHOD__,
				'message' => $ex->getMessage()
			]);
		} finally {
			if ($this->_conn) {
				$this->_conn->close();
			}
		}
		return $this->_sth;
	}

	public function insert($table, $params)
	{
		$params_keys = array_keys($params);
		foreach ($params as $v) {
			if (is_string($v)) {
				$v = $this->_conn->real_escape_string($v);
				$v = "'$v'";
			}
			$v = is_null($v) ? "null" : $v;
			$tmp[] = $v;
		}
		$sParam = join(',', $tmp);
		unset($tmp);
		$sFields = implode(',', $params_keys);
		return $this->query("INSERT INTO {$table} ({$sFields}) VALUES ({$sParam});");
	}

	public function fetchObject()
	{
		return $this->_sth ? $this->_sth->fetch_object() : false;
	}

	public function fetchAll($key = null)
	{
		if ($this->_sth) {
			$res = [];
			if ($key)
				while ($row = $this->_sth->fetch_object())
					$res[$row->{$key}] = $row;
			else
				while ($row = $this->_sth->fetch_object())
					$res[] = $row;
			return $res;
		}
		return false;
	}

	public function AffectedRows()
	{
		return $this->_sth ? $this->_sth->num_rows : false;
	}
}

class Debug
{
	public static function print($data)
	{
		echo date("Y-m-d H:i:s") . " " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
	}
}
