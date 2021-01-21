<?php

require_once __DIR__ . '/rabbit.php';

class Analytics
{

	/*
	 * Отправляет данные пользователя в базу аналитики
	 * @param int $user_id
	 * @param string $shop_uuid
	 * return bool
	 */
	public function setUser($user_id, $shop_uuid = null)
	{

		if (empty($user_id)) return false;
		$params = [
			'user_id' => $user_id,
			'magazine_uid' => $shop_uuid ?: null
		];

		$db = new Analytics_Db();
		$result = $db->insert('bender_terminal_magazine', $params);
		unset($db);
		return $result;
	}

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
				$params = json_decode($msg->body, true);
				$analytics = new self();
				$result = $analytics->setUser($params['user_id'], $params['shop_uuid'] ?? null);
			} catch (\Throwable $th) {
				throw $th;
				return;
			}
			if ($result) $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
			echo date('d.m.Y H:i:s') . " $result " . json_encode($params, JSON_UNESCAPED_UNICODE) . "\n";
		};
		try {
			$rabbit = new Rabbit(RABBIT_CHANNEL_NAME);
			$rabbit->consumer($callback, $tag);
		} catch (\Throwable $th) {
			echo $th->getMessage();
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
		Debug::print(['construct', !$this->_conn]);
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
			Debug::print(['query', $this->_conn, $sql]);
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
