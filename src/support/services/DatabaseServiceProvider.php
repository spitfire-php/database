<?php namespace spitfire\storage\database\support\services;

use Psr\Container\ContainerInterface;
use spitfire\contracts\ConfigurationInterface;
use spitfire\contracts\services\ProviderInterface;
use spitfire\provider\Container;
use spitfire\storage\database\Connection;
use spitfire\storage\database\ConnectionManager;

class DatabaseServiceProvider implements ProviderInterface
{
	
	/**
	 *
	 * @return void
	 */
	public function register(ContainerInterface $container)
	{
		$config  = $container->get(ConfigurationInterface::class);
		$default = $config->get('database.default');
		$manager = new ConnectionManager($config->get('database.connections'));
		
		/**
		 *
		 * @var Container
		 */
		$container = $container->get(Container::class);
		
		$container->set(ConnectionManager::class, $manager);
		$container->set(Connection::class, $manager->get($default));
	}
	
	/**
	 *
	 * @return void
	 */
	public function init(ContainerInterface $container)
	{
	}
}
