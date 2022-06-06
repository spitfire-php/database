<?php namespace spitfire\storage\database\drivers\mysqlpdo;

use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use spitfire\exceptions\ApplicationException;
use spitfire\storage\database\DriverInterface;
use spitfire\storage\database\drivers\SchemaMigrationExecutorInterface;
use spitfire\storage\database\Field;
use spitfire\storage\database\grammar\mysql\MySQLQueryGrammar;
use spitfire\storage\database\grammar\mysql\MySQLQuoter;
use spitfire\storage\database\grammar\mysql\MySQLRecordGrammar;
use spitfire\storage\database\grammar\mysql\MySQLSchemaGrammar;
use spitfire\storage\database\io\CharsetEncoder;
use spitfire\storage\database\LayoutInterface;
use spitfire\storage\database\Query;
use spitfire\storage\database\Record;
use spitfire\storage\database\ResultSetInterface;
use spitfire\storage\database\Schema;
use spitfire\storage\database\Settings;

/**
 * MySQL driver via PDO. This driver does <b>not</b> make use of prepared
 * statements, prepared statements become too difficult to handle for the driver
 * when using several JOINs or INs. For this reason the driver has moved from
 * them back to standard querying.
 */
class Driver implements DriverInterface
{
	
	/**
	 *
	 * @var Settings
	 */
	private $settings;
	
	/**
	 *
	 * @var CharsetEncoder
	 */
	private $encoder;
	
	/**
	 *
	 * @var PDO
	 */
	private $connection;
	
	/**
	 *
	 * @var LoggerInterface
	 */
	private $logger;
	
	/**
	 *
	 * @var int
	 */
	private $mode = DriverInterface::MODE_EXC;
	
	
	public function __construct(Settings $settings, LoggerInterface $logger)
	{
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->encoder  = new CharsetEncoder(mb_internal_encoding(), $settings->getEncoding());
	}
	
	public function getDefaultQueryGrammar() : QueryGrammarInterface
	{
		return new MySQLQueryGrammar(new MySQLQuoter($this->connection));
	}
	
	public function getDefaultRecordGrammar() : RecordGrammarInterface
	{
		return new MySQLRecordGrammar(new MySQLQuoter($this->connection));
	}
	
	public function init() : void
	{
		/**
		 * If the driver isn't hot, we assume the connection is not expected.
		 */
		if (!($this->mode & DriverInterface::MODE_EXC)) {
			return;
		}
		
		$encoding = ['utf8' => 'utf8mb4'][$this->encoder->getInnerEncoding()];
		
		/**
		 * Generate the DSN for the mysql PDO connection.
		 */
		$dsn  = 'mysql:' . http_build_query(array_filter([
			'dbname' => $this->settings->getSchema(),
			'host' => $this->settings->getServer(),
			'charset' => $encoding
		]), '', ';');
		
		$user = $this->settings->getUser();
		$pass = $this->settings->getPassword();
		
		/**
		 * Connect to the database to prepare for incoming queries. That way we can
		 * start receiving queries immediately.
		 */
		try {
			$this->connection = new PDO($dsn, $user, $pass);
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->connection->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
		}
		catch (PDOException $e) {
			$this->logger->error($e->getMessage());
			throw new ApplicationException('DB Error. Connection refused by the server: ' . $e->getMessage());
		}
	}
	
	
	public function write(string $sql) : int|false
	{
		
		return $this->connection->exec($sql);
	}
	
	public function read(string $sql) : PDOStatement|false
	{
		
		return $this->connection->query($sql);
	}
}
