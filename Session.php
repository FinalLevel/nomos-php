<?php
/**
 * @link https://github.com/FinalLevel/nomos-php/
 * @copyright Copyright (c) 2014 Vitalii Khranivskyi
 * @author Vitalii Khranivskyi <misaret@gmail.com>
 * @license LICENSE file
 */

namespace fl\nomos;

/**
 * Nomos Storage session handler
 * ~~~
 * $storage = new \fl\nomos\Storage([
 *	['host' => '127.0.0.1', 'port' => '8986'],
 * ]);
 * $storage->useSerialize = false;
 * $handler = new \fl\nomos\Session($storage, 1, 2);
 * session_set_save_handler($handler);
 * ~~~
 */
class Session implements \SessionHandlerInterface
{
	/**
	 * @var integer
	 */
	public $lifetime;
	public $writeOnlyChanges = true;

	/**
	 * @var \fl\nomos\Storage
	 */
	private $_storage;
	private $_level;
	private $_subLevel = 0;

	private $_lastSessionId;
	private $_lastSessionData;

	/**
	 * @param \fl\nomos\Storage $storage
	 * @param string $level
	 * @param string $subLevel
	 * @param integer $lifetime
	 */
	public function __construct(Storage $storage, $level, $subLevel = null, $lifetime = null)
	{
		$this->_storage = $storage;
		$this->_level = $level;
		if ($subLevel) {
			$this->_subLevel = $subLevel;
		}
		$this->lifetime = ($lifetime ? $lifetime : (int) ini_get('session.gc_maxlifetime'));
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return boolean
	 */
	public function close()
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $sessionId
	 * @return boolean
	 */
	public function destroy($sessionId)
	{
		$this->_lastSessionId = null;
		$this->_lastSessionData = null;

		return $this->_storage->delete($this->_level, $this->_subLevel, $sessionId);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $maxlifetime
	 * @return boolean
	 */
	public function gc($maxlifetime)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $savePath
	 * @param string $name
	 * @return boolean
	 */
	public function open($savePath, $name)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 * 
	 * @param string $sessionId
	 * @return string
	 */
	public function read($sessionId)
	{
		$this->_lastSessionId = $sessionId;
		$this->_lastSessionData = $this->_storage->get($this->_level, $this->_subLevel, $sessionId, $this->lifetime);

		return $this->_lastSessionData;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $sessionId
	 * @param string $sessionData
	 * @return boolean
	 */
	public function write($sessionId, $sessionData)
	{
		if (
			$this->writeOnlyChanges
			&& $sessionId === $this->_lastSessionId
			&& $sessionData === $this->_lastSessionData
		) {
			return true;
		}

		$this->_lastSessionId = $sessionId;
		$this->_lastSessionData = $sessionData;

		return $this->_storage->update($this->_level, $this->_subLevel, $sessionId, $this->lifetime, $sessionData);
	}
}
