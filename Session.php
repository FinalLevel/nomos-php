<?php
/**
 * @link https://github.com/misaret/nomos-php/
 * @copyright Copyright (c) 2014 Vitalii Khranivskyi
 * @author Vitalii Khranivskyi <misaret@gmail.com>
 * @license LICENSE file
 */

namespace misaret\nomos;

/**
 * Nomos Storage session handler
 * ~~~
 * $storage = new \misaret\nomos\Storage([
 *	['host' => '127.0.0.1', 'port' => '8986'],
 * ]);
 * $storage->useSerialize = false;
 * $handler = new \misaret\nomos\Session($storage, 1, 2);
 * session_set_save_handler($handler);
 * ~~~
 */
class Session implements \SessionHandlerInterface
{
	/**
	 * @var integer
	 */
	public $lifetime;

	/**
	 * @var \misaret\nomos\Storage
	 */
	private $_storage;
	private $_level;
	private $_subLevel = 0;

	/**
	 * @param \misaret\nomos\Storage $storage
	 * @param integer $level
	 * @param integer $subLevel
	 * @param integer $lifetime
	 */
	public function __construct(Storage $storage, $level, $subLevel = null, $lifetime = null)
	{
		$this->_storage = $storage;
		$this->_level = $level;
		if ($subLevel)
			$this->_subLevel = $subLevel;
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
	 * @param string $session_id
	 * @return boolean
	 */
	public function destroy($session_id)
	{
		return $this->_storage->delete($this->_level, $this->_subLevel, $session_id);
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
	 * @param string $save_path
	 * @param string $name
	 * @return boolean
	 */
	public function open($save_path, $name)
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 * 
	 * @param string $session_id
	 * @return string
	 */
	public function read($session_id)
	{
		return $this->_storage->get($this->_level, $this->_subLevel, $session_id, $this->lifetime);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string $session_id
	 * @param string $session_data
	 * @return boolean
	 */
	public function write($session_id, $session_data)
	{
		return $this->_storage->put($this->_level, $this->_subLevel, $session_id, $this->lifetime, $session_data);
	}
}
