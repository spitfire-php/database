<?php namespace spitfire\storage\database;

use PDOStatement;
use spitfire\exceptions\ApplicationException;
use spitfire\storage\database\drivers\internal\SchemaMigrationExecutor;
use spitfire\storage\database\query\ResultInterface;

/**
 * A connection combines a database driver and a schema, allowing the application to
 * maintain a context for the schema it's working on.
 */
class Connection
{
	
	/**
	 *
	 * @var DriverInterface
	 */
	private $driver;
	
	/**
	 *
	 * @todo Introduce the SchemaState class
	 * @var SchemaState
	 */
	private $schema;
	
	/**
	 * 
	 * @todo Introduce the interface
	 * @var QueryGrammarInterface
	 */
	private $queryGrammar;
	
	/**
	 * 
	 * @todo Introduce the interface
	 * @var RecordGrammarInterface
	 */
	private $recordGrammar;
	
	/**
	 * 
	 * @todo Introduce the interface
	 * @var SchemaGrammarInterface
	 */
	private $schemaGrammar;
	
	/**
	 *
	 */
	public function __construct(Schema $schema, DriverInterface $driver)
	{
		$this->driver = $driver;
		$this->schema = $schema;
		
		/**
		 * Default to the default grammars for the driver we loaded. These are generally
		 * not overridden except for very specific situations and testing fixtures.
		 */
		$this->queryGrammar  = $driver->getDefaultQueryGrammar();
		$this->recordGrammar = $driver->getDefaultRecordGrammar();
		$this->schemaGrammar = $driver->getDefaultSchemaGrammar();
	}
	
	/**
	 * Returns the schema used to model this connection. This provides information about the state
	 * of the database. Models use this to determine which data can be written to the db.
	 *
	 * @return Schema
	 */
	public function getSchema() : Schema
	{
		return $this->schema;
	}
	
	/**
	 * Returns the driver used to manage this connection.
	 *
	 * @return DriverInterface
	 */
	public function getDriver() : DriverInterface
	{
		return $this->driver;
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
		$manager = $this->driver->getMigrationExecutor($this->schema)->tags();
		
		if ($manager === null) {
			return false;
		}
		
		$tags = $manager->listTags();
		
		return !!array_search(
			'migration:' . $migration->identifier(),
			$tags,
			true
		);
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
		$migrators = [
			$this->driver->getMigrationExecutor($this->schema),
			new SchemaMigrationExecutor($this->schema)
		];
		
		foreach ($migrators as $migrator) {
			$migration->up($migrator);
			$migrator->tags() !== null? $migrator->tags()->tag('migration:' . $migration->identifier()) : null;
		}
	}
	
	/**
	 * Rolls a migration back. Undoing it's changes to the schema.
	 *
	 * @param MigrationOperationInterface $migration
	 * @throws ApplicationException If the migration could not be applied
	 */
	public function rollback(MigrationOperationInterface $migration): void
	{
		$migrators = [
			$this->driver->getMigrationExecutor($this->schema),
			new SchemaMigrationExecutor($this->schema)
		];
		
		foreach ($migrators as $migrator) {
			$migration->down($migrator);
			$migrator->tags() !== null? $migrator->tags()->untag('migration:' . $migration->identifier()) : null;
		}
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
		$sql = $this->queryGrammar->query($query);
		return $this->driver->read($sql);
	}
	
	public function update(LayoutInterface $layout, Record $record): bool
	{
		$stmt   = $this->recordGrammar->updateRecord($layout, $record);
		$result = $this->driver->write($stmt);
		
		/**
		 * Commit that the record has been written to the database. The record will be in sync
		 * with the database.
		 */
		$record->commit();
		
		return $result !== false;
	}
	
	
	public function insert(LayoutInterface $layout, Record $record): bool
	{
		$stmt = $this->recordGrammar->insertRecord($layout, $record);		
		$result = $this->driver->write($stmt);
		
		/**
		 * In the event that the field is automatically incremented, the dbms
		 * will provide us with the value it inserted. This value needs to be
		 * stored to the record.
		 */
		$increment = $layout->getFields()->filter(function (Field $field) {
			return $field->isAutoIncrement();
		})->first();
		
		if ($increment !== null) {
			$id = $this->connection->lastInsertId();
			$record->set($increment->getName(), $id);
		}
		
		/**
		 * Since the database data is now in sync with the contents of the
		 * record, we can commit the record as containing the same data that
		 * the DBMS does.
		 */
		$record->commit();
		
		return $result !== false;
	}
	
	
	public function delete(LayoutInterface $layout, Record $record): bool
	{
		$stmt = $this->recordGrammar->deleteRecord($layout, $record);
		$result = $this->driver->write($stmt);
		
		return $result !== false;
	}
	
	public function has(string $name): bool
	{
		$stmt = $this->driver->read($this->schemaGrammar->hasTable($this->settings->getSchema(), $name));
		
		assert($stmt instanceof PDOStatement);
		return ($stmt->fetch()[0]) > 0;
	}
}
