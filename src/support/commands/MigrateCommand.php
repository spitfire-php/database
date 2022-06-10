<?php namespace spitfire\storage\database\support\commands;

use spitfire\exceptions\ApplicationException;
use spitfire\storage\database\Connection;
use spitfire\storage\database\migration\schemaState\SchemaMigrationExecutor;
use spitfire\storage\database\MigrationOperationInterface;
use spitfire\storage\database\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
	
	protected static $defaultName = 'migration:migrate';
	protected static $defaultDescription = 'Performs migrations.';
	
	/**
	 *
	 * @var Connection
	 */
	private $connection;
	
	/**
	 *
	 * @var string
	 */
	private $migrationManifestFile;
	
	/**
	 *
	 * @var string
	 */
	private $schemaFile;
	
	/**
	 *
	 * @param Connection $connection
	 * @param string $migrationManifestFile
	 */
	public function __construct(Connection $connection, string $migrationManifestFile, string $schemaFile)
	{
		$this->connection = $connection;
		$this->migrationManifestFile = $migrationManifestFile;
		$this->schemaFile = $schemaFile;
		parent::__construct();
	}
	
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/**
		 * The migration command will allow the system to import migrations
		 * from the migrations manifest file which should contain an array
		 * of migrations to be performed to maintain the application at a
		 * modern state.
		 *
		 * Please note that the order in which the migrations appear in the
		 * manifest file is relevant to the order in which they are applied
		 * and rolled back.
		 */
		$file = $this->migrationManifestFile;
		
		/**
		 * If there is no manifest, there is no way to consistently apply
		 * the migrations.
		 */
		if (!file_exists($file)) {
			throw new ApplicationException(sprintf('No migration manifest file in %s', $file), 2204110944);
		}
		
		$connection = clone $this->connection;
		$connection->setSchema(
			file_exists($this->schemaFile)?
				include $this->schemaFile :
				new Schema($this->connection->getSchema()->getName())
		);
		
		/**
		 * List the migrations available to the application.
		 */
		$migrations = include($file);
		
		/**
		 * Fast forward the base schema to match the currently applied schema on the database.
		 * This should prevent the application from having inconsistent states.
		 */
		foreach ($migrations as $migration) {
			$output->writeln('Checking ' . $migration->identifier());
			if ($result = $connection->contains($migration)) {
				$migration->up(new SchemaMigrationExecutor($connection->getSchema()));
			}
			else {
				echo 'Not found', PHP_EOL;
			}
		}
		
		/**
		 * Fast forward the schema to match the status of the server
		 */
		
		foreach	($migrations as $migration) {
			if (!$connection->contains($migration)) {
				assert($migration instanceof MigrationOperationInterface);
				$output->writeln('Applying ' . $migration->identifier());
				$connection->apply($migration);
			}
			else {
				$output->writeln('Skipping...');
			}
		}
		
		return 0;
	}
}
