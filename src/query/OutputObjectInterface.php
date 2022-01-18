<?php namespace spitfire\storage\database\query;

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
 * This interface represents objects in the database that can be used within
 * the context of queries
 */
interface OutputObjectInterface
{
	
	/**
	 * Returns the table this is a part of. This can be null if the item is anonymous (
	 * see Aggregation)
	 * 
	 * @return TableObjectInterface
	 */
	function getTable() :? TableObjectInterface;
	
	/**
	 * Returns the name of the object within the table context, this can be null in the
	 * event that the item is anonymous (like aggregation functions)
	 * 
	 * @return string|null
	 */
	function getName() :? string;
	
	/**
	 * Returns the name of the item when using it in context. This can be null if the
	 * item is not being aliased
	 * 
	 * @return string|null
	 */
	function getAlias() :? string;
}