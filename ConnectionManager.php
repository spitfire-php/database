<?php namespace spitfire\storage\database;

use Psr\Container\ContainerInterface;
use spitfire\collection\Collection;
use spitfire\provider\Container;
use spitfire\storage\database\drivers\Adapter;
use spitfire\storage\database\drivers\mysqlpdo\Driver;

class ConnectionManager
{
	
	/**
	 *
	 * @var Collection<Connection>
	 */
	private $connections;
	
	/**
	 *
	 * @var ContainerInterface
	 */
	private $container;
	
	/**
	 * 
	 * @var string
	 */
	private $schemaFile;
	
	/**
	 *
	 * @var mixed[]
	 */
	private $definitions;
	
	/**
	 *
	 * @param mixed[] $definitions
	 */
	public function __construct(ContainerInterface $container, array $definitions, string $schemaFile)
	{
		$this->container = $container;
		$this->definitions = $definitions;
		$this->schemaFile = $schemaFile;
		$this->connections = new Collection();
	}
	
	public function get(string $name) : Connection
	{
		
		/**
		 * If the key is available, we return it from our cache
		 */
		if ($this->connections->has($name)) {
			return $this->connections->$name;
		}
		
		$connection = $this->make($name);
		$this->connections->$name = $connection;
		return $connection;
	}
	
	
	public function make(string $name) : Connection
	{
		/**
		 * Load the definition from the configuration excerpt we received from
		 * the framework.
		 */
		$definition = $this->definitions[$name];
		
		/**
		 * We assemble a settings object tat can be used to initialize the driver. The
		 * data will depend on the database driver.
		 */
		$settings = is_string($definition['settings'])? Settings::fromURL($definition['settings']) :Settings::fromArray($definition['settings']);
		
		/**
		 * Find the schema cache file. This contains information about the state of the schema,
		 * allowing the application to verify that no data is being accessed that should not be
		 * read.
		 */
		$schemaFile = $definition['schema']?? $this->schemaFile;
		$schema = file_exists($schemaFile)? include($schemaFile) : new Schema($settings->getSchema());
		
		/**
		 * Initialize the driver, find the appropriate driver class, and instance it with the settings
		 * we found earlier.
		 *
		 * To ensure proper state, we verify the object we received is actually an instance of a Driver.
		 */
		$type   = $definition['driver'];
		
		$driver = $this->container->get(Container::class)->assemble($type, [
			'settings' => $settings
		]);
		
		assert($driver instanceof Driver);
		
		$driver->connect();
		
		/**
		 * Create the connection, cache it, and return it. Please note that this does not yet guarantee
		 * that the driver is working properly, drivers may be lazy with their connection to prevent
		 * starting connections for applications that may not need them.
		 */
		$connection = new Connection($schema, new Adapter($driver));
		
		return $connection;
	}
}
