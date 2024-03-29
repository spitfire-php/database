<?php namespace spitfire\storage\database;

use spitfire\exceptions\ApplicationException;
use spitfire\provider\NotFoundException;
use spitfire\storage\database\drivers\Adapter;
use spitfire\storage\database\drivers\SchemaMigrationExecutorInterface;
use spitfire\storage\database\query\ResultInterface;

/**
 * A connection combines a database driver and a schema, allowing the application to
 * maintain a context for the schema it's working on.
 * 
 * The point of a global connection is to "terminate" the dumps. When using the framework
 * outside of a testing environment, dumping the record will only print the name of the 
 * connection, and resort to retrieving the connection from a global object.
 */
class ConnectionGlobal implements ConnectionInterface
{
	private ?string $connection;
	
	public function __construct(string $connection = null)
	{
		$this->connection = $connection;
	}
	
	
	protected function getUnderlyingConnection() : ConnectionInterface
	{
		try {
			return $this->connection !== null?
			spitfire()->provider()->get(ConnectionManager::class)->get($this->connection) :
			spitfire()->provider()->get(Connection::class);
		}
		catch (NotFoundException $ex) {
			trigger_error('Connection is not available', E_USER_ERROR);
		}
	}
	
	/**
	 * Returns the schema used to model this connection. This provides information about the state
	 * of the database. Models use this to determine which data can be written to the db.
	 *
	 * @return Schema
	 */
	public function getSchema() : Schema
	{
		return $this->getUnderlyingConnection()->getSchema();
	}
	
	public function setSchema(Schema $schema): void
	{
		$this->getUnderlyingConnection()->setSchema($schema);
	}
	
	public function getAdapter() : Adapter
	{
		return $this->getUnderlyingConnection()->getAdapter();
	}
	
	public function getMigrationExecutor() : SchemaMigrationExecutorInterface
	{
		return $this->getUnderlyingConnection()->getMigrationExecutor();
	}
	
	/**
	 * Executes a migration operation on the database. This allows you to create,
	 * upgrade or downgrade database schemas.
	 *
	 * @param MigrationOperationInterface $migration
	 * @return bool True, if the migration has been applied
	 */
	public function contains(MigrationOperationInterface $migration): bool
	{
		return $this->getUnderlyingConnection()->contains($migration);
	}
	
	/**
	 * Executes a migration operation on the database. This allows you to create,
	 * upgrade or downgrade database schemas.
	 *
	 * @param MigrationOperationInterface $migration
	 * @throws ApplicationException If the migration could not be applied
	 */
	public function apply(MigrationOperationInterface $migration): void
	{
		$this->getUnderlyingConnection()->apply($migration);
	}
	
	/**
	 * Rolls a migration back. Undoing it's changes to the schema.
	 *
	 * @param MigrationOperationInterface $migration
	 * @throws ApplicationException If the migration could not be applied
	 */
	public function rollback(MigrationOperationInterface $migration): void
	{
		$this->getUnderlyingConnection()->rollback($migration);
	}
	
	/**
	 * Query the database for data. The query needs to encapsulate all the data
	 * that is needed for our DBMS to execute the query.
	 *
	 * @param Query $query
	 * @return ResultInterface
	 */
	public function query(Query $query): ResultInterface
	{
		return $this->getUnderlyingConnection()->query($query);
	}
	
	public function update(LayoutInterface $layout, Record $record): bool
	{
		return $this->getUnderlyingConnection()->update($layout, $record);
	}
	
	
	public function insert(LayoutInterface $layout, Record $record): bool
	{
		return $this->getUnderlyingConnection()->insert($layout, $record);
	}
	
	
	public function delete(LayoutInterface $layout, Record $record): bool
	{
		return $this->getUnderlyingConnection()->delete($layout, $record);
	}
	
	public function has(string $name): bool
	{
		return $this->getUnderlyingConnection()->has($name);
	}
}
