<?php namespace spitfire\storage\database\migration\schemaState;

/*
 *
 * Copyright (C) 2021-2023 César de la Cal Bretschneider <cesar@magic3w.com>.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-13 01  USA
 *
 */

use spitfire\collection\Collection;
use spitfire\exceptions\NotFoundException;
use spitfire\storage\database\drivers\TableMigrationExecutorInterface;
use spitfire\storage\database\events\QueryBeforeCreateEvent;
use spitfire\storage\database\events\QueryBeforeEvent;
use spitfire\storage\database\events\RecordBeforeDeleteEvent;
use spitfire\storage\database\events\RecordBeforeInsertEvent;
use spitfire\storage\database\events\RecordBeforeUpdateEvent;
use spitfire\storage\database\events\SoftDeleteListener;
use spitfire\storage\database\events\SoftDeleteQueryListener;
use spitfire\storage\database\events\UpdateTimestampListener;
use spitfire\storage\database\Field;
use spitfire\storage\database\ForeignKey;
use spitfire\storage\database\Index;
use spitfire\storage\database\Layout;
use spitfire\storage\database\LayoutInterface;

/**
 * The table migration executor allows the application to write migrations
 * that apply to the schema.
 */
class TableMigrationExecutor implements TableMigrationExecutorInterface
{
	
	/**
	 *
	 * @var LayoutInterface
	 */
	private $table;
	
	/**
	 * Creates a new table migrator.
	 *
	 * @param LayoutInterface $layout
	 */
	public function __construct(LayoutInterface $layout)
	{
		$this->table = $layout;
	}
	
	/**
	 * Adds an autoincrementing field to the table.
	 *
	 * @throws NotFoundException
	 * @param string $name
	 * @return TableMigrationExecutorInterface
	 */
	public function increments(string $name): TableMigrationExecutorInterface
	{
		$this->table->putField($name, 'long:unsigned', false, true);
		$this->table->primary($this->table->getField($name));
		
		return $this;
	}
	
	/**
	 * Adds an integer field to the schema.
	 *
	 * @param string $name
	 * @param bool $unsigned
	 * @param bool $nullable
	 * @return TableMigrationExecutorInterface
	 */
	public function int(string $name, bool $unsigned, bool $nullable = true): TableMigrationExecutorInterface
	{
		$this->table->putField($name, $unsigned? 'int:unsigned' : 'int', $nullable, false);
		return $this;
	}
	
	/**
	 * Adds an integer field to the schema.
	 *
	 * @param string $name
	 * @param bool $unsigned
	 * @param bool $nullable
	 * @return TableMigrationExecutorInterface
	 */
	public function long(string $name, bool $unsigned, bool $nullable = true): TableMigrationExecutorInterface
	{
		$this->table->putField($name, $unsigned? 'long:unsigned' : 'long', $nullable, false);
		return $this;
	}
	
	/**
	 * Adds a string field to the schema.
	 *
	 * @param string $name
	 * @param int $length
	 * @param bool $nullable
	 * @return TableMigrationExecutorInterface
	 */
	public function string(string $name, int $length, bool $nullable = true) : TableMigrationExecutorInterface
	{
		$this->table->putField($name, 'string:' . $length, $nullable, false);
		return $this;
	}
	
	/**
	 * Adds a text field to the schema. Please ensure to validate input to these fields
	 * since large text items can destabilize a server.
	 *
	 * @param string $name
	 * @param bool $nullable
	 * @return TableMigrationExecutorInterface
	 */
	public function text(string $name, bool $nullable = true): TableMigrationExecutorInterface
	{
		$this->table->putField($name, 'text', $nullable, false);
		return $this;
	}
	
	/**
	 * Adds an enum field to the layout. The options may not contain any comma values.
	 *
	 * @param string $name
	 * @param string[] $options
	 * @param bool $nullable
	 * @return TableMigrationExecutorInterface
	 */
	public function enum(string $name, array $options, bool $nullable = true): TableMigrationExecutorInterface
	{
		/**
		 * Verify that none of the options contains a comma. This ensures that the developer
		 * is not causing any inconsistent behavior. This code is only executed during testing
		 * and is generally not expected to run in production.
		 */
		assert((Collection::fromArray($options))->filter(function (string $e) : bool {
			return strstr($e, ',') !== false;
		})->isEmpty());
		
		$this->table->putField($name, 'enum:' . implode(',', $options), $nullable, false);
		return $this;
	}
	
	/**
	 * Adds an index to the table.
	 *
	 * @param string $name
	 * @param string[] $fields
	 * @return TableMigrationExecutorInterface
	 */
	public function index(string $name, array $fields): TableMigrationExecutorInterface
	{
		$_fields = (Collection::fromArray($fields))->each(function (string $name) : Field {
			return $this->table->getField($name);
		});
		
		$this->table->index($name, ...$_fields);
		return $this;
	}
	
	/**
	 * Adds a foreign key to the layout. Using the remote TableMigrationExecutor makes it really
	 * convenient for an application to use the migrations.
	 *
	 * @param string $name
	 * @param TableMigrationExecutorInterface $table
	 * @return TableMigrationExecutorInterface
	 */
	public function foreign(string $name, TableMigrationExecutorInterface $table): TableMigrationExecutorInterface
	{
		/**
		 * If the referenced layout does not have a primary key the code cannot
		 * continue.
		 */
		$layout = $table->layout();
		assert($layout->getPrimaryKey() !== null);
		
		/**
		 * Create a field to host the data for the referenced field. Rename the field
		 * to prefix it with the name we want to assign to this field.
		 *
		 * We swap the field to being nullable, because the remote will always be the primary
		 * key, which is not nullable, but this makes no sense in the reference.
		 */
		$reference = $layout->getPrimaryKey()->getFields()[0];
		$field = $this->table->putField($name . $reference->getName(), $reference->getType(), true, false);
		
		$index = new ForeignKey(
			sprintf('fk_%s_%s', $this->table->getTableName(), $name),
			$field,
			($layout)->getTableReference()->getOutput($reference->getName())
		);
		
		$this->table->putIndex($index);
		
		return $this;
	}
	
	/**
	 * Adds a unique key to the table. This will be enforced by the DBMS, requiring the values
	 * in this index to be unique.
	 *
	 * @param string $name
	 * @param string[] $fields
	 * @return TableMigrationExecutorInterface
	 */
	public function unique(string $name, array $fields): TableMigrationExecutorInterface
	{
		$_fields = (Collection::fromArray($fields))->each(function (string $name) {
			return $this->table->getField($name);
		});
		
		$this->table->putIndex(new Index($name, $_fields, true, false));
		return $this;
	}
	
	/**
	 * Adds a primary key to the database.
	 *
	 * Please note that some DBMS will ignore the name of the primary key and just require it
	 * being either unnamed or having some naming convention.  In these cases spitfire will ignore
	 * the name and use the convention.
	 *
	 * @throws NotFoundException
	 * @param string $field
	 * @return TableMigrationExecutorInterface
	 */
	public function primary(string $field): TableMigrationExecutorInterface
	{
		$_fields = Collection::fromArray([$this->table->getField($field)]);
		
		/**
		 * If the table already has a primary key. This should fail.
		 */
		assert($this->table->getPrimaryKey() === null);
		
		$this->table->putIndex(new Index(Layout::PRIMARY_KEY, $_fields, true, true));
		return $this;
	}
	
	/**
	 * Add an autoincrementing ID field so the database has a primary key.
	 *
	 * @throws NotFoundException
	 * @return TableMigrationExecutorInterface
	 */
	public function id(): TableMigrationExecutorInterface
	{
		return $this->increments('_id');
	}
	
	/**
	 * Add a timestamp to the record so we know when it was created and whether/when
	 * it was updated.
	 *
	 * @return TableMigrationExecutorInterface
	 */
	public function timestamps(): TableMigrationExecutorInterface
	{
		$this->table->putField('created', 'int:unsigned', false, false);
		$this->table->putField('updated', 'int:unsigned', true, false);
		
		$this->table->events()->hook(RecordBeforeInsertEvent::class, new UpdateTimestampListener('created'));
		$this->table->events()->hook(RecordBeforeUpdateEvent::class, new UpdateTimestampListener('updated'));
		
		return $this;
	}
	
	/**
	 * Add a timestamp that allows the database to record that certain data was deleted and
	 * should no longer be maintained.
	 *
	 * @return TableMigrationExecutorInterface
	 */
	public function softDelete(): TableMigrationExecutorInterface
	{
		$this->table->putField('removed', 'int:unsigned', true, false);
		$this->table->events()->hook(RecordBeforeDeleteEvent::class, new SoftDeleteListener('removed'));
		$this->table->events()->hook(QueryBeforeCreateEvent::class, new SoftDeleteQueryListener('removed'));
		
		/**
		 * @todo Add the query hook so we can ignore soft deleted records by default.
		 */
		
		return $this;
	}
	
	/**
	 * Removes the field from the table.
	 *
	 * @param string $name
	 * @return TableMigrationExecutorInterface
	 */
	public function drop(string $name): TableMigrationExecutorInterface
	{
		$this->table->unsetField($name);
		return $this;
	}
	
	public function dropIndex(string $name): TableMigrationExecutorInterface
	{
		$this->table->unsetIndex($name);
		return $this;
	}
	
	/**
	 *
	 * @return LayoutInterface
	 */
	public function layout(): LayoutInterface
	{
		return $this->table;
	}
}
