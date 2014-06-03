<?php
/**
 * @link https://github.com/FinalLevel/nomos-php/
 * @copyright Copyright (c) 2014 Vitalii Khranivskyi
 * @author Vitalii Khranivskyi <misaret@gmail.com>
 * @license LICENSE file
 */

namespace fl\nomos;

/**
 * PHP library for work with Nomos Storage servers
 */
class Storage
{
	/**
	 * Protocol version
	 */
	const VERSION = 'V01';

	/**
	 * Array of server info
	 * ~~~
	 * [
	 *	['host' => '127.0.0.1', 'port' => 14301],
	 *	['host' => '127.0.0.1', 'port' => 14302],
	 * ]
	 * ~~~
	 * @var array
	 */
	public $servers;
	/**
	 * Timeout socket operations in seconds
	 * @var integer
	 */
	public $timeout = 2;
	/**
	 * function (string $eventName, object $thisObject) { return true; }
	 * @var callable
	 */
	public $eventsFunc;
	/**
	 * Serialize/unserialize data before/after operations
	 * @var boolean
	 */
	public $useSerialize = true;
	/**
	 * [
	 *	'key' => $key,
	 *	'cmd' => $cmd,
	 * ]
	 * @var array
	 */
	public $lastCommand;
	/**
	 * Socket descriptors
	 * @var array of resource
	 */
	private $_sockets = [];
	private $_currentSocketIndex;

	public function __construct(array $servers = [])
	{
		$this->servers = $servers;
	}

	/**
	 * Convert $key to hexadecimal string, max length 16
	 * @param string $key
	 * @return string
	 */
	public static function buildKey($key)
	{
		if (!is_scalar($key)) {
			$key = substr(sha1(serialize($key)), -16);
		} elseif (!ctype_xdigit($key) || strlen($key) > 16) {
			$key = substr(sha1($key), -16);
		}

		return $key;
	}

	/**
	 * Get data
	 * @param integer $level
	 * @param integer $subLevel
	 * @param string $key hexadecimal string, max length 16
	 * @param integer $time new life time, 0 - leave life time unchanged
	 * @return mixed
	 */
	public function get($level, $subLevel, $key, $time = 0)
	{
		$key = static::buildKey($key);
		$result = $this->_cmd($key, "G,$level,$subLevel,$key,$time");
		if (!$result) {
			return false;
		}

		if (!$this->useSerialize) {
			return $result;
		}

		$data = unserialize($result);
		if ($data === false) {
			trigger_error("unserialize error: \"" . $result . '"', E_USER_WARNING);
			$this->delete($level, $subLevel, $key);
		}

		return $data;
	}

	/**
	 * Put data
	 *
	 * @param integer $level
	 * @param integer $subLevel
	 * @param string $key hexadecimal string, max length 16
	 * @param integer $time
	 * @param mixed $data
	 * @return boolean
	 */
	public function put($level, $subLevel, $key, $time, $data)
	{
		$key = static::buildKey($key);
		if ($this->useSerialize) {
			$data = serialize($data);
		}
		$dataLen = strlen($data);
		return $this->_cmd($key, "P,$level,$subLevel,$key,$time,$dataLen", $data);
	}

	/**
	 * Update data.
	 * If value of data the same then update only lifetime
	 *
	 * @param integer $level
	 * @param integer $subLevel
	 * @param string $key hexadecimal string, max length 16
	 * @param integer $time
	 * @param mixed $data
	 * @return boolean
	 */
	public function update($level, $subLevel, $key, $time, $data)
	{
		$key = static::buildKey($key);
		if ($this->useSerialize) {
			$data = serialize($data);
		}
		$dataLen = strlen($data);
		return $this->_cmd($key, "U,$level,$subLevel,$key,$time,$dataLen", $data);
	}

	/**
	 * Create empty data record
	 *
	 * @param integer $level
	 * @param integer $subLevel
	 * @param string $key hexadecimal string, max length 16
	 * @param integer $time
	 * @return boolean
	 */
	public function touch($level, $subLevel, $key, $time)
	{
		$key = static::buildKey($key);
		return $this->_cmd($key, "T,$level,$subLevel,$key,$time");
	}

	/**
	 * Delete data record
	 *
	 * @param integer $level
	 * @param integer $subLevel
	 * @param string $key hexadecimal string, max length 16
	 * @return boolean
	 */
	public function delete($level, $subLevel, $key)
	{
		$key = static::buildKey($key);
		return $this->_cmd($key, "R,$level,$subLevel,$key");
	}

	/**
	 * Creates a new top level of the index
	 * ~~~
	 * createTopLevel('level1', 'INT32', 'STRING')
	 * ~~~
	 * @param string $level
	 * @param string $subLevelType
	 * @param string $itemType
	 * @return boolean
	 */
	public function createTopLevel($level, $subLevelType, $itemType)
	{
		return $this->_cmd(0, "C,$level,$subLevelType,$itemType");
	}

	/**
	 * Open connection to Nomos Storage servers
	 * @param boolean $reopen Reopen socket
	 * @return resource
	 */
	private function _open($reopen = false)
	{
		$countServers = count($this->servers);
		for (
			$i = 0, $currIndex = $this->_currentSocketIndex;
			$i < $countServers;
			++$i, $currIndex = ($this->_currentSocketIndex + $i) % $countServers
		) {
			$socket = @$this->_sockets[$currIndex];
			if ($socket) {
				if ($reopen) {
					fclose($socket);
				} else {
					$this->_sockets[$this->_currentSocketIndex] = $socket;
					return $socket;
				}
			}

			if ($this->_fireEvent('beforeOpen') === false) {
				return false;
			}

			$serverInfo = $this->servers[$currIndex];
			$errno = $errstr = false;
			$socket = @fsockopen($serverInfo['host'], $serverInfo['port'], $errno, $errstr, $this->timeout);
			if ($socket) {
				stream_set_timeout($socket, $this->timeout);
				$this->_sockets[$this->_currentSocketIndex] = $socket;
				$this->_sockets[$currIndex] = $socket;
				trigger_error('Connected to ' . $serverInfo['host'] . ':' . $serverInfo['port']);
				$this->_fireEvent('afterOpen');
				return $socket;
			} else {
				trigger_error('Cannot connect to ' . $serverInfo['host'] . ':' . $serverInfo['port']
					. ' ' . $errstr, E_USER_WARNING);
				$this->_sockets[$currIndex] = null;
				$this->_fireEvent('afterOpenFails');
			}
		}

		trigger_error('Cannot connect to any servers', E_USER_ERROR);
		$this->servers = [];
		$this->_sockets = [];
		return false;
	}

	private function _socketRead($len)
	{
		$result = '';
		$socket = $this->_sockets[$this->_currentSocketIndex];
		$br = true;
		while ($len > 0 && !feof($socket)) {
			$tmp = fread($socket, $len);
			if ($tmp === false || strlen($tmp) < 1 && ($br = !$br)) {
				trigger_error('Error when read ' . $len . ' bytes; result = '
					. ($tmp === false ? 'false' : "''"), E_USER_WARNING);
				break;
			}
			$len -= strlen($tmp);
			$result .= $tmp;
		}

		return $len > 0 ? false : $result;
	}

	private function _send($cmd, $data = null)
	{
		$buff = "$cmd\n$data";
		$len = strlen($buff);
		$sent = fwrite($this->_sockets[$this->_currentSocketIndex], $buff, $len);
		if ($len != $sent && $this->_open(true)) {
			$sent = fwrite($this->_sockets[$this->_currentSocketIndex], $buff, $len);
			if ($len != $sent) {
				trigger_error('Resend failed: ' . $cmd, E_USER_WARNING);
			} else {
				trigger_error('Resend OK: ' . $cmd);
			}
		}
		if ($len != $sent) {
			return false;
		}

		fflush($this->_sockets[$this->_currentSocketIndex]);

		return true;
	}

	private function _readAnswer()
	{
		$result = $this->_socketRead(11);
		if (!$result) {
			return null;
		}

		if (!strncmp($result, 'ERR', 3)) {
			if (!strncmp($result, 'ERR_CR', 6)) {
				fclose($this->_sockets[$this->_currentSocketIndex]);
				$this->_sockets[$this->_currentSocketIndex] = null;
			}
			return false;
		}

		$size = hexdec(substr($result, 0, -1));
		if (!$size) {
			return true;
		}

		$result = $this->_socketRead($size);

		return $result === false ? null : $result;
	}

	private function _cmd($key, $cmd, $data = null)
	{
		$this->lastCommand = compact('key', 'cmd');
		if ($this->_fireEvent('beforeCommand') === false) {
			return false;
		}

		$this->_currentSocketIndex = $this->_keyToIndex($key);

		$retry = 0;
		while (++$retry <= 2) {
			if (!$this->_open($retry > 1)) {
				$this->_fireEvent('afterCommandFails');
				return false;
			}

			if ($this->_fireEvent('beforeSendCommand') === false) {
				return false;
			}

			$result = $this->_send(static::VERSION . ',' . $cmd, $data);
			if ($result) {
				$result = $this->_readAnswer();
				if ($result) {
					$this->_fireEvent('afterCommand');
					return $result;
				} elseif ($result === false) {
					$this->_fireEvent('afterCommandFails');
					return false;
				}
			}
		}

		trigger_error('Command failed: ' . $cmd, E_USER_ERROR);
		$this->_fireEvent('afterCommandFails');
		return false;
	}

	/**
	 * key to storage index assigment
	 * @param string $key hexadecimal string, max length 16
	 * @return integer
	 */
	protected function _keyToIndex($key)
	{
		return hexdec(substr($key, -4)) % count($this->servers);
	}

	protected function _fireEvent($eventName)
	{
		if ($this->eventsFunc) {
			return call_user_func($this->eventsFunc, $eventName, $this);
		}

		return true;
	}
}
