<?php namespace spitfire\storage\database;

use spitfire\collection\Collection;


/* 
 * Copyright (C) 2021 César de la Cal Bretschneider <cesar@magic3w.com>.
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
 * MA 02110-1301  USA
 */

/**
 * The layout is basically a list of columns and indexes that makes up the schema
 * of a relation in a relational database. Spitfire constructs these objects automatically 
 * from models so it can interact with the database consistently.
 * 
 * Please note that, these are similar to model fields, but not 100% compatible, they
 * are not interchangeable.
 */
class Layout
{
	
	/**
	 * The name of the table this layout represents in the DBMS. This table name
	 * must be valid in your DBMS, if this is not the case, the application will
	 * fail without proper notice.
	 * 
	 * Therefore it's recommended to generate table names without slashes, or any 
	 * non ASCII characters.
	 * 
	 * @var string
	 */
	private $tablename;
	
	/**
	 * The fields or columns this table contains. Each column can hold
	 * a certain data type, we ensure that the data we write / read from
	 * the DBMS has the appropriate type.
	 * 
	 * @var Collection<Field>
	 */
	private $fields;
	
	/**
	 * The indexes in this relation. This information is used when constructing 
	 * tables only, and bears no relevance when querying the database.
	 * 
	 * @var Collection<Index>
	 */
	private $indexes;
	
	/**
	 * 
	 * @param string $tablename
	 */
	public function __construct(string $tablename)
	{
		$this->tablename = $tablename;
	}
	
	/**
	 * Returns the name the DBMS should use to name this table. The implementing
	 * class should respect user configuration including db_table_prefix
	 * 
	 * @return string
	 */
	function getTableName() : string
	{
		return $this->tablename;
	}
	
	/**
	 * Gets a certain field from the layout. 
	 * 
	 * @param string $name
	 * @return Field
	 */
	function getField($name) : Field
	{
		return $this->fields[$name];
	}
	
	/**
	 * Sets a certain field to a certain value. 
	 * 
	 * @param string $name
	 * @param Field $field
	 * @return Layout
	 */
	function setField(string $name, Field $field) : Layout
	{
		$this->fields[$name] = $field;
		return $this;
	}
	
	/**
	 * Removes the field from the layout.
	 * 
	 * @param string $name
	 * @return Layout
	 */
	function unsetField(string $name) : Layout
	{
		unset($this->fields[$name]);
		return $this;
	}
	
	/**
	 * Adds the fields we received from a collection, this is specially useful since 
	 * logical fields will return collections when generating physical fields.
	 * 
	 * @param Collection $fields
	 * @return Layout
	 */
	function addFields(Collection $fields) : Layout
	{
		assert($fields->containsOnly(Field::class));
		$this->fields->add($fields);
		return $this;
	}
	
	/**
	 * 
	 * @return Collection<Field> The columns in this database table
	 */
	function getFields() : Collection
	{
		return $this->fields;
	}
	
	/**
	 * This method needs to get the lost of indexes from the logical Schema and 
	 * convert them to physical indexes for the DBMS to manage.
	 * 
	 * @return Collection<IndexInterface> The indexes in this layout
	 */
	function getIndexes() : Collection
	{
		return $this->indexes;
	}
	
	/**
	 * Get's the table's primary key. This will always return an array
	 * containing the fields the Primary Key contains.
	 * 
	 * @return IndexInterface|null
	 */
	public function getPrimaryKey() :? IndexInterface
	{
		$indexes = $this->indexes;
		return $indexes->filter(function (IndexInterface $i) { return $i->isPrimary(); })->rewind();
	}
	
	/**
	 * Get the auto increment field, or null if there is none for this table.
	 * 
	 * @return Field|null
	 */
	public function getAutoIncrement() :? Field
	{
		$fields = $this->fields;
		return $fields->filter(function (Field $i) { return $i->isAutoIncrement(); })->rewind();
	}
	
	
}
