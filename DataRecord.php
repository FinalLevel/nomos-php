<?php
/**
 * @link https://github.com/misaret/nomos-php/
 * @copyright Copyright (c) 2014 Vitalii Khranivskyi
 * @author Vitalii Khranivskyi <misaret@gmail.com>
 * @license LICENSE file
 */

namespace misaret\nomos;

/**
 * PHP library for manage data record in Nomos Storage
 */
class DataRecord
{
	/**
	 * @var string hexadecimal string, max length 16
	 */
	public $key;
	/**
	 * Reference to variable containing data
	 * @var array reference
	 */
	public $dataReference;
	/**
	 * Default data life time
	 * @var integer
	 */
	public $lifetime = 7200; // 2 hours

	private $_storage;
	private $_level;
	private $_subLevel = 0;
	private $_oldData;

	/**
	 * Constructor
	 * @param string $key hexadecimal string, max length 16
	 * @param array $data variable to load/save data
	 * @param \misaret\nomos\Storage $storage
	 * @param integer $level
	 * @param integer $subLevel
	 * @param integer $lifetime
	 */
	public function __construct($key, &$data, Storage $storage, $level, $subLevel = null, $lifetime = null)
	{
		$this->key = $key;
		$this->dataReference = &$data;
		$this->_storage = $storage;
		$this->_level = $level;
		if ($subLevel)
			$this->_subLevel = $subLevel;
		if ($lifetime)
			$this->lifetime = $lifetime;
	}

	/**
	 * Load data from storage
	 * @return array
	 */
	public function load()
	{
		$this->dataReference = $this->_storage->get($this->_level, $this->_subLevel, $this->key, $this->lifetime);
		$this->_oldData = $this->dataReference;

		return $this->dataReference;
	}

	/**
	 * Save data to storage if changed
	 * @return boolean
	 */
	public function save()
	{
		if (!$this->_oldData && !$this->dataReference)
			return false;
		if ($this->_oldData === $this->dataReference)
			return true;

		if ($this->_storage->put($this->_level, $this->_subLevel, $this->key, $this->lifetime, $this->dataReference)) {
			$this->_oldData = $this->dataReference;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Delete data from storage
	 * @return boolean
	 */
	public function delete()
	{
		$this->_oldData = null;
		
		return $this->_storage->delete($this->_level, $this->_subLevel, $this->key);
	}
}
