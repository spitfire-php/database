<?php namespace spitfire\storage\database;

use BadMethodCallException;
use Closure;
use spitfire\collection\Collection;
use spitfire\collection\TypedCollection;
use spitfire\storage\database\identifiers\FieldIdentifier;
use spitfire\storage\database\identifiers\FieldIdentifierInterface;
use spitfire\storage\database\identifiers\IdentifierInterface;
use spitfire\storage\database\identifiers\TableIdentifierInterface;
use spitfire\storage\database\query\Alias;
use spitfire\storage\database\query\Join;
use spitfire\storage\database\query\JoinTable;
use spitfire\storage\database\query\QueryOrTableIdentifier;
use spitfire\storage\database\query\Restriction;
use spitfire\storage\database\query\RestrictionGroup;
use spitfire\storage\database\query\SelectExpression;

/**
 * The query provides a mechanism for assembling restrictions that Spitfire and
 * the DBMS driver can then convert into a SQL query (or similar, for NoSQL).
 *
 * @mixin RestrictionGroup
 */
class Query
{
	
	/**
	 * The table this query is retrieving data from. This table is wrapped inside
	 * a QueryTable object to ensure that the table can refer back to the query
	 * when needed.
	 *
	 * @var Alias
	 */
	private $from;
	
	/**
	 * Allows the query to include complex resultsets that accommodate more elaborate
	 * searches. A join is composed of a type (left, right, inner, outer) and a query
	 * that restricts it's return.
	 *
	 * Please note, that this lowlevel library does not perform the logic for linking
	 * the query with the subquery (this needs to be performed by the developer or the
	 * model system).
	 *
	 * @var Collection<Join>
	 */
	private $joins;
	
	/**
	 *
	 * @var RestrictionGroup
	 */
	private $where;
	
	/**
	 * This contains an array of aggregation functions that are executed with the
	 * query to provide metadata on the query.
	 *
	 * @var Collection<SelectExpression>
	 */
	private $select;
	
	/**
	 * Determines by which fields the result should be aggregated. This affects aggregation
	 * functions like count() or max(). Please note that, if the groupBy is populated, your
	 * application should only attempt to retrieve:
	 *
	 * * Fields that are part of the groupby
	 * * Computed fields that reduce the result to a single record (like max or count)
	 *
	 * @var Collection<IdentifierInterface>
	 */
	private $groupBy;
	
	/**
	 *
	 * @var Collection<OrderBy>
	 */
	private $order;
	
	/**
	 * The number of records that should be skipped when working with the query's resultset.
	 *
	 * @var int|null
	 */
	private $offset = null;
	
	/**
	 * The size of the largest resultset that this query should return. In record count.
	 *
	 * @var int|null
	 */
	private $limit  = null;
	
	/**
	 *
	 * @param QueryOrTableIdentifier $table
	 */
	public function __construct(QueryOrTableIdentifier $table)
	{
		$this->from = new Alias($table, $table->withAlias());
		$this->joins = new TypedCollection(Join::class);
		$this->where = new RestrictionGroup($this->from->output());
		$this->select = new TypedCollection(SelectExpression::class);
		$this->groupBy = new TypedCollection(IdentifierInterface::class);
		$this->order = new TypedCollection(OrderBy::class);
	}
	
	/**
	 * Joins a table to the current query.
	 *
	 * You can pass a second, optional argument to customize the join, this is a closure that
	 * receives the following parameters:
	 *
	 * * Join : The new connection. You can use it to retrieve fields and push connections
	 * * Query: The query being joined into. You can use this to retrieve the fields from the uplinks.
	 *
	 * @param TableIdentifierInterface $table
	 * @param Closure $fn
	 */
	public function joinTable(TableIdentifierInterface $table, Closure $fn = null) : JoinTable
	{
		$join = new JoinTable(new Alias(new QueryOrTableIdentifier($table), $table->withAlias()));
		$this->joins->push($join);
		
		$fn !== null && $fn($join, $this);
		
		return $join;
	}
	
	/**
	 *
	 * @return Collection<Join>
	 */
	public function getJoined() : Collection
	{
		return $this->joins;
	}
	
	public function getRestrictions() : RestrictionGroup
	{
		return $this->where;
	}
	
	/**
	 * Adds an order clause to the result set, this will be appended and therefore
	 * subordinated to the previously added order clauses.
	 *
	 * @param OrderBy $order
	 * @return Query
	 */
	public function putOrder(OrderBy $order)
	{
		$this->order->push($order);
		return $this;
	}
	
	
	/**
	 * This method returns a finite amount of items matching the parameters from
	 * the database. This method always returns a collection, even if the result
	 * is empty (no records matched the query)
	 *
	 * @todo This now feels like it needs to be moved to the model. Where it makes sense to retrieve
	 * the data from the query directly.
	 *
	 * @param int|null $skip
	 * @param int|null $amt
	 * @return Query
	 */
	public function range(int $skip = null, int $amt = null) : Query
	{
		$this->offset = $skip;
		$this->limit  = $amt;
		return $this;
	}
	
	/**
	 * Returns the number of results that the DBMS should skip before starting to return data.
	 * This is specially useful when creating paginated queries.
	 *
	 * @return int|null
	 */
	public function getOffset() :? int
	{
		return $this->offset;
	}
	
	/**
	 * Return the maximum number of records the DBMS should be returning when fetching the
	 * result for this query.
	 *
	 * @return int|null
	 */
	public function getLimit() :? int
	{
		return $this->limit;
	}
	
	/**
	 * Defines a column or array of columns the system will be using to group
	 * data when generating aggregates.
	 *
	 * @param IdentifierInterface[] $columns
	 * @return Query Description
	 */
	public function groupBy(array $columns = [])
	{
		$this->groupBy = Collection::fromArray($columns);
		return $this;
	}
	
	/**
	 * Returns the fields this query is grouped by.
	 *
	 * @return Collection<IdentifierInterface>
	 */
	public function getGroupBy() : Collection
	{
		return $this->groupBy;
	}
	
	/**
	 * Add all the fields of a table (implied to be the current query table if null is
	 * passed).
	 *
	 * @param TableIdentifierInterface $table
	 * @return Collection<SelectExpression>
	 */
	public function selectAll(TableIdentifierInterface $table = null) : Collection
	{
		$_t = $table !== null? $table : $this->from->output();
		
		$add = $_t->getOutputs()->each(function (IdentifierInterface $f) : SelectExpression {
			return new SelectExpression($f);
		});
		
		$this->select->add($add);
		
		assert($this->select->containsOnly(SelectExpression::class));
		return $add;
	}
	
	public function select(string $name, string $alias = null) : SelectExpression
	{
		$field = $this->from->output()->getOutput($name);
		$expression = new SelectExpression($field, $alias);
		$this->select->push($expression);
		assert($this->select->containsOnly(SelectExpression::class));
		return $expression;
	}
	
	public function selectField(FieldIdentifier $field, string $alias = null) : SelectExpression
	{
		$expression = new SelectExpression($field, $alias);
		$this->select->push($expression);
		assert($this->select->containsOnly(SelectExpression::class));
		return $expression;
	}
	
	/**
	 * Creates a copy of the query that does not select anything. This is specially
	 * useful, when dealing with metadata queries, like count.
	 *
	 * Since order usually depends on the outputs of the query, the order is also removed.
	 *
	 * @return Query
	 */
	public function withoutSelect() : Query
	{
		$copy = clone $this;
		$copy->select = new TypedCollection(SelectExpression::class);
		$copy->order = new TypedCollection(OrderBy::class);
		return $copy;
	}
	
	public function aggregate(FieldIdentifierInterface $field, Aggregate $fn, string $alias) : Query
	{
		$this->select->push(new SelectExpression($field, $alias, $fn));
		return $this;
	}
	
	/**
	 *
	 * @param string $name
	 * @return SelectExpression
	 */
	public function getOutput(string $name) : SelectExpression
	{
		$output = $this->select->filter(function (SelectExpression $e) use ($name) {
			return $e->getName() === $name;
		})->first();
		
		assert($output !== null);
		return $output;
	}
	
	/**
	 *
	 * @return Collection<SelectExpression>
	 */
	public function getOutputs() : Collection
	{
		return $this->select;
	}
	
	/**
	 *
	 * @return Collection<OrderBy>
	 */
	public function getOrder() : Collection
	{
		return $this->order;
	}
	
	/**
	 *
	 * @return Alias
	 */
	public function getFrom() : Alias
	{
		return $this->from;
	}
	
	/**
	 * Returns the actual table this query is searching on.
	 *
	 * @return TableIdentifierInterface
	 */
	public function getTable()
	{
		return $this->from->output();
	}
	
	/**
	 *
	 * @param string $name
	 * @param mixed $arguments
	 * @return mixed
	 */
	public function __call($name, $arguments)
	{
		if (method_exists($this->where, $name)) {
			return $this->where->$name(...$arguments);
		}
		
		throw new BadMethodCallException(sprintf('Undefined method Query::%s', $name));
	}
	
	public function __toString()
	{
		return sprintf(
			'%s(%s) {%s}',
			$this->from->input()->isQuery()? 'Query' : 'Table',
			implode('.', $this->from->output()->raw()),
			$this->where->restrictions()->count()
		);
	}
}
