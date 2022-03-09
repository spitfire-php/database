<?php namespace spitfire\storage\database\events;

use spitfire\event\Event;
use spitfire\storage\database\DriverInterface;
use spitfire\storage\database\LayoutInterface;
use spitfire\storage\database\Record;

abstract class RecordEvent extends Event
{
	
	/**
	 *
	 * @var DriverInterface
	 */
	private $driver;
	
	/**
	 *
	 * @var LayoutInterface
	 */
	private $layout;
	
	/**
	 *
	 * @var Record
	 */
	private $record;
	
	/**
	 *
	 * @var mixed[]
	 */
	private $options;
	
	/**
	 *
	 * @param DriverInterface $driver
	 * @param Record $record
	 * @param string[] $options
	 */
	public function __construct(DriverInterface $driver, LayoutInterface $layout, Record $record, array $options = [])
	{
		$this->record = $record;
		$this->layout = $layout;
		$this->options = $options;
		$this->driver = $driver;
	}
	
	public function getRecord() : Record
	{
		return $this->record;
	}
	
	public function getLayout() : LayoutInterface
	{
		return $this->layout;
	}
	
	/**
	 *
	 * @return string[]
	 */
	public function getOptions() : array
	{
		return $this->options;
	}
	
	public function getDriver() : DriverInterface
	{
		return $this->driver;
	}
}
