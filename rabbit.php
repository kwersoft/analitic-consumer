<?php
require_once __DIR__ . '/vendor/autoload.php';

//Необходимые классы
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Класс для работы с rabbitmq.kwersoft.ru.
 */
class Rabbit
{

	/**
	 * Создание подключения к каналу
	 * @param string $nameChannel
	 * @return Rabbit
	 */
	function __construct($nameChannel)
	{
		$this->nameChannel = (string) $nameChannel;
		$this->vhost = RABBIT_V_HOST;
		$this->connection = new AMQPStreamConnection(RABBIT_HOST, RABBIT_PORT, RABBIT_LOGIN, RABBIT_PSWD, $this->vhost);
		$this->channel = $this->connection->channel();
		$this->channel->queue_declare($this->nameChannel, false, true, false, false);
	}

	/**
	 * Добавление сообщения в канал
	 *
	 * @param string $message
	 * @return void
	 */
	public function producer($message)
	{
		$msg = new AMQPMessage($message);
		$this->channel->basic_publish($msg, '', $this->nameChannel);
		$this->channel->close();
		$this->connection->close();
	}

	/**
	 * Получить сообщение
	 *
	 * @return object
	 */
	public function getMessage($ack = false)
	{
		$result = $this->channel->basic_get($this->nameChannel);
		if ($ack) $this->channel->basic_ack($result->getDeliveryTag());
		$this->channel->close();
		$this->connection->close();
		return $result;
	}

	/**
	 * создание подключения к каналу и выполнение callback функции на каждое сообщение в нем
	 *
	 * @param string $callback
	 * @param string $tag
	 * @return void
	 */
	public function consumer($callback = '', $tag = '', $autoAck = false)
	{
		echo "connect to " . RABBIT_LOGIN . "@" . RABBIT_HOST . "/$this->vhost:$this->nameChannel \n";
		if (!$callback) $callback = function ($msg) {
			echo " [x] Received ", $msg->body, "\n";
		};
		//Уходим слушать сообщения из очереди в бесконечный цикл
		$this->channel->basic_consume($this->nameChannel, $tag, false, $autoAck, false, false, $callback);
		while (count($this->channel->callbacks)) {
			$this->channel->wait();
		}

		$this->channel->close();
		$this->connection->close();
	}

	/**
	 * Запрос в апи
	 *
	 * @param string $path
	 * @param string $method
	 * @return object
	 */
	private static function getApiData(string $path, string $method = "")
	{
		$url = "https://" . RABBIT_HOST . $path;
		$params = [
			'http' => [
				'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL . sprintf(
					'Authorization: Basic %s',
					base64_encode(RABBIT_LOGIN . ":" . RABBIT_PSWD)
				)
			]
		];
		if (!empty($method)) $params['http']['method'] = $method;
		$ctx = stream_context_create($params);
		return json_decode(file_get_contents($url, false, $ctx));
	}

	/**
	 * Проверка существования подключения к очереди
	 *
	 * @param string $nameChannel
	 * @param string $tag
	 * @return object
	 */
	public static function checkConsumer($nameChannel, $tag = '')
	{
		$data = (array) self::getApiData("/api/consumers");
		foreach ($data as $item) {
			$queue = $item->queue;
			if ($queue->name == $nameChannel && $queue->vhost == RABBIT_V_HOST)
				if ($tag == $item->consumer_tag || !$tag)
					return $item;
		}
	}

	/**
	 * Прерывание подключения по имени канала
	 *
	 * @param string $nameChannel
	 * @return void
	 */
	public static function deleteConnection(string $nameChannel): void
	{
		if (empty($connection = self::checkConsumer($nameChannel))) return;
		$name = rawurlencode($connection->channel_details->connection_name);
		self::getApiData("/api/connections/$name", "DELETE");
	}

	/**
	 * API метод сброса подключения по имени очереди
	 *
	 * @param string $name
	 * @return void
	 */
	public static function connectionDelete(string $name): void
	{
		if (empty($name)) return;
		self::deleteConnection($name);
	}
}
