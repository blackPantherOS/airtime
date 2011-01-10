<?php


/**
 * Base class that represents a query for the 'cc_show' table.
 *
 * 
 *
 * @method     CcShowQuery orderByDbId($order = Criteria::ASC) Order by the id column
 * @method     CcShowQuery orderByDbName($order = Criteria::ASC) Order by the name column
 * @method     CcShowQuery orderByDbRepeats($order = Criteria::ASC) Order by the repeats column
 * @method     CcShowQuery orderByDbDescription($order = Criteria::ASC) Order by the description column
 *
 * @method     CcShowQuery groupByDbId() Group by the id column
 * @method     CcShowQuery groupByDbName() Group by the name column
 * @method     CcShowQuery groupByDbRepeats() Group by the repeats column
 * @method     CcShowQuery groupByDbDescription() Group by the description column
 *
 * @method     CcShowQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     CcShowQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     CcShowQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     CcShowQuery leftJoinCcShowDays($relationAlias = '') Adds a LEFT JOIN clause to the query using the CcShowDays relation
 * @method     CcShowQuery rightJoinCcShowDays($relationAlias = '') Adds a RIGHT JOIN clause to the query using the CcShowDays relation
 * @method     CcShowQuery innerJoinCcShowDays($relationAlias = '') Adds a INNER JOIN clause to the query using the CcShowDays relation
 *
 * @method     CcShowQuery leftJoinCcShowHosts($relationAlias = '') Adds a LEFT JOIN clause to the query using the CcShowHosts relation
 * @method     CcShowQuery rightJoinCcShowHosts($relationAlias = '') Adds a RIGHT JOIN clause to the query using the CcShowHosts relation
 * @method     CcShowQuery innerJoinCcShowHosts($relationAlias = '') Adds a INNER JOIN clause to the query using the CcShowHosts relation
 *
 * @method     CcShowQuery leftJoinCcShowSchedule($relationAlias = '') Adds a LEFT JOIN clause to the query using the CcShowSchedule relation
 * @method     CcShowQuery rightJoinCcShowSchedule($relationAlias = '') Adds a RIGHT JOIN clause to the query using the CcShowSchedule relation
 * @method     CcShowQuery innerJoinCcShowSchedule($relationAlias = '') Adds a INNER JOIN clause to the query using the CcShowSchedule relation
 *
 * @method     CcShow findOne(PropelPDO $con = null) Return the first CcShow matching the query
 * @method     CcShow findOneOrCreate(PropelPDO $con = null) Return the first CcShow matching the query, or a new CcShow object populated from the query conditions when no match is found
 *
 * @method     CcShow findOneByDbId(int $id) Return the first CcShow filtered by the id column
 * @method     CcShow findOneByDbName(string $name) Return the first CcShow filtered by the name column
 * @method     CcShow findOneByDbRepeats(int $repeats) Return the first CcShow filtered by the repeats column
 * @method     CcShow findOneByDbDescription(string $description) Return the first CcShow filtered by the description column
 *
 * @method     array findByDbId(int $id) Return CcShow objects filtered by the id column
 * @method     array findByDbName(string $name) Return CcShow objects filtered by the name column
 * @method     array findByDbRepeats(int $repeats) Return CcShow objects filtered by the repeats column
 * @method     array findByDbDescription(string $description) Return CcShow objects filtered by the description column
 *
 * @package    propel.generator.airtime.om
 */
abstract class BaseCcShowQuery extends ModelCriteria
{

	/**
	 * Initializes internal state of BaseCcShowQuery object.
	 *
	 * @param     string $dbName The dabase name
	 * @param     string $modelName The phpName of a model, e.g. 'Book'
	 * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
	 */
	public function __construct($dbName = 'airtime', $modelName = 'CcShow', $modelAlias = null)
	{
		parent::__construct($dbName, $modelName, $modelAlias);
	}

	/**
	 * Returns a new CcShowQuery object.
	 *
	 * @param     string $modelAlias The alias of a model in the query
	 * @param     Criteria $criteria Optional Criteria to build the query from
	 *
	 * @return    CcShowQuery
	 */
	public static function create($modelAlias = null, $criteria = null)
	{
		if ($criteria instanceof CcShowQuery) {
			return $criteria;
		}
		$query = new CcShowQuery();
		if (null !== $modelAlias) {
			$query->setModelAlias($modelAlias);
		}
		if ($criteria instanceof Criteria) {
			$query->mergeWith($criteria);
		}
		return $query;
	}

	/**
	 * Find object by primary key
	 * Use instance pooling to avoid a database query if the object exists
	 * <code>
	 * $obj  = $c->findPk(12, $con);
	 * </code>
	 * @param     mixed $key Primary key to use for the query
	 * @param     PropelPDO $con an optional connection object
	 *
	 * @return    CcShow|array|mixed the result, formatted by the current formatter
	 */
	public function findPk($key, $con = null)
	{
		if ((null !== ($obj = CcShowPeer::getInstanceFromPool((string) $key))) && $this->getFormatter()->isObjectFormatter()) {
			// the object is alredy in the instance pool
			return $obj;
		} else {
			// the object has not been requested yet, or the formatter is not an object formatter
			$criteria = $this->isKeepQuery() ? clone $this : $this;
			$stmt = $criteria
				->filterByPrimaryKey($key)
				->getSelectStatement($con);
			return $criteria->getFormatter()->init($criteria)->formatOne($stmt);
		}
	}

	/**
	 * Find objects by primary key
	 * <code>
	 * $objs = $c->findPks(array(12, 56, 832), $con);
	 * </code>
	 * @param     array $keys Primary keys to use for the query
	 * @param     PropelPDO $con an optional connection object
	 *
	 * @return    PropelObjectCollection|array|mixed the list of results, formatted by the current formatter
	 */
	public function findPks($keys, $con = null)
	{	
		$criteria = $this->isKeepQuery() ? clone $this : $this;
		return $this
			->filterByPrimaryKeys($keys)
			->find($con);
	}

	/**
	 * Filter the query by primary key
	 *
	 * @param     mixed $key Primary key to use for the query
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByPrimaryKey($key)
	{
		return $this->addUsingAlias(CcShowPeer::ID, $key, Criteria::EQUAL);
	}

	/**
	 * Filter the query by a list of primary keys
	 *
	 * @param     array $keys The list of primary key to use for the query
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByPrimaryKeys($keys)
	{
		return $this->addUsingAlias(CcShowPeer::ID, $keys, Criteria::IN);
	}

	/**
	 * Filter the query on the id column
	 * 
	 * @param     int|array $dbId The value to use as filter.
	 *            Accepts an associative array('min' => $minValue, 'max' => $maxValue)
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByDbId($dbId = null, $comparison = null)
	{
		if (is_array($dbId) && null === $comparison) {
			$comparison = Criteria::IN;
		}
		return $this->addUsingAlias(CcShowPeer::ID, $dbId, $comparison);
	}

	/**
	 * Filter the query on the name column
	 * 
	 * @param     string $dbName The value to use as filter.
	 *            Accepts wildcards (* and % trigger a LIKE)
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByDbName($dbName = null, $comparison = null)
	{
		if (null === $comparison) {
			if (is_array($dbName)) {
				$comparison = Criteria::IN;
			} elseif (preg_match('/[\%\*]/', $dbName)) {
				$dbName = str_replace('*', '%', $dbName);
				$comparison = Criteria::LIKE;
			}
		}
		return $this->addUsingAlias(CcShowPeer::NAME, $dbName, $comparison);
	}

	/**
	 * Filter the query on the repeats column
	 * 
	 * @param     int|array $dbRepeats The value to use as filter.
	 *            Accepts an associative array('min' => $minValue, 'max' => $maxValue)
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByDbRepeats($dbRepeats = null, $comparison = null)
	{
		if (is_array($dbRepeats)) {
			$useMinMax = false;
			if (isset($dbRepeats['min'])) {
				$this->addUsingAlias(CcShowPeer::REPEATS, $dbRepeats['min'], Criteria::GREATER_EQUAL);
				$useMinMax = true;
			}
			if (isset($dbRepeats['max'])) {
				$this->addUsingAlias(CcShowPeer::REPEATS, $dbRepeats['max'], Criteria::LESS_EQUAL);
				$useMinMax = true;
			}
			if ($useMinMax) {
				return $this;
			}
			if (null === $comparison) {
				$comparison = Criteria::IN;
			}
		}
		return $this->addUsingAlias(CcShowPeer::REPEATS, $dbRepeats, $comparison);
	}

	/**
	 * Filter the query on the description column
	 * 
	 * @param     string $dbDescription The value to use as filter.
	 *            Accepts wildcards (* and % trigger a LIKE)
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByDbDescription($dbDescription = null, $comparison = null)
	{
		if (null === $comparison) {
			if (is_array($dbDescription)) {
				$comparison = Criteria::IN;
			} elseif (preg_match('/[\%\*]/', $dbDescription)) {
				$dbDescription = str_replace('*', '%', $dbDescription);
				$comparison = Criteria::LIKE;
			}
		}
		return $this->addUsingAlias(CcShowPeer::DESCRIPTION, $dbDescription, $comparison);
	}

	/**
	 * Filter the query by a related CcShowDays object
	 *
	 * @param     CcShowDays $ccShowDays  the related object to use as filter
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByCcShowDays($ccShowDays, $comparison = null)
	{
		return $this
			->addUsingAlias(CcShowPeer::ID, $ccShowDays->getDbShowId(), $comparison);
	}

	/**
	 * Adds a JOIN clause to the query using the CcShowDays relation
	 * 
	 * @param     string $relationAlias optional alias for the relation
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function joinCcShowDays($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		$tableMap = $this->getTableMap();
		$relationMap = $tableMap->getRelation('CcShowDays');
		
		// create a ModelJoin object for this join
		$join = new ModelJoin();
		$join->setJoinType($joinType);
		$join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
		if ($previousJoin = $this->getPreviousJoin()) {
			$join->setPreviousJoin($previousJoin);
		}
		
		// add the ModelJoin to the current object
		if($relationAlias) {
			$this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
			$this->addJoinObject($join, $relationAlias);
		} else {
			$this->addJoinObject($join, 'CcShowDays');
		}
		
		return $this;
	}

	/**
	 * Use the CcShowDays relation CcShowDays object
	 *
	 * @see       useQuery()
	 * 
	 * @param     string $relationAlias optional alias for the relation,
	 *                                   to be used as main alias in the secondary query
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowDaysQuery A secondary query class using the current class as primary query
	 */
	public function useCcShowDaysQuery($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		return $this
			->joinCcShowDays($relationAlias, $joinType)
			->useQuery($relationAlias ? $relationAlias : 'CcShowDays', 'CcShowDaysQuery');
	}

	/**
	 * Filter the query by a related CcShowHosts object
	 *
	 * @param     CcShowHosts $ccShowHosts  the related object to use as filter
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByCcShowHosts($ccShowHosts, $comparison = null)
	{
		return $this
			->addUsingAlias(CcShowPeer::ID, $ccShowHosts->getDbShow(), $comparison);
	}

	/**
	 * Adds a JOIN clause to the query using the CcShowHosts relation
	 * 
	 * @param     string $relationAlias optional alias for the relation
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function joinCcShowHosts($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		$tableMap = $this->getTableMap();
		$relationMap = $tableMap->getRelation('CcShowHosts');
		
		// create a ModelJoin object for this join
		$join = new ModelJoin();
		$join->setJoinType($joinType);
		$join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
		if ($previousJoin = $this->getPreviousJoin()) {
			$join->setPreviousJoin($previousJoin);
		}
		
		// add the ModelJoin to the current object
		if($relationAlias) {
			$this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
			$this->addJoinObject($join, $relationAlias);
		} else {
			$this->addJoinObject($join, 'CcShowHosts');
		}
		
		return $this;
	}

	/**
	 * Use the CcShowHosts relation CcShowHosts object
	 *
	 * @see       useQuery()
	 * 
	 * @param     string $relationAlias optional alias for the relation,
	 *                                   to be used as main alias in the secondary query
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowHostsQuery A secondary query class using the current class as primary query
	 */
	public function useCcShowHostsQuery($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		return $this
			->joinCcShowHosts($relationAlias, $joinType)
			->useQuery($relationAlias ? $relationAlias : 'CcShowHosts', 'CcShowHostsQuery');
	}

	/**
	 * Filter the query by a related CcShowSchedule object
	 *
	 * @param     CcShowSchedule $ccShowSchedule  the related object to use as filter
	 * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function filterByCcShowSchedule($ccShowSchedule, $comparison = null)
	{
		return $this
			->addUsingAlias(CcShowPeer::ID, $ccShowSchedule->getDbShowId(), $comparison);
	}

	/**
	 * Adds a JOIN clause to the query using the CcShowSchedule relation
	 * 
	 * @param     string $relationAlias optional alias for the relation
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function joinCcShowSchedule($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		$tableMap = $this->getTableMap();
		$relationMap = $tableMap->getRelation('CcShowSchedule');
		
		// create a ModelJoin object for this join
		$join = new ModelJoin();
		$join->setJoinType($joinType);
		$join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
		if ($previousJoin = $this->getPreviousJoin()) {
			$join->setPreviousJoin($previousJoin);
		}
		
		// add the ModelJoin to the current object
		if($relationAlias) {
			$this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
			$this->addJoinObject($join, $relationAlias);
		} else {
			$this->addJoinObject($join, 'CcShowSchedule');
		}
		
		return $this;
	}

	/**
	 * Use the CcShowSchedule relation CcShowSchedule object
	 *
	 * @see       useQuery()
	 * 
	 * @param     string $relationAlias optional alias for the relation,
	 *                                   to be used as main alias in the secondary query
	 * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
	 *
	 * @return    CcShowScheduleQuery A secondary query class using the current class as primary query
	 */
	public function useCcShowScheduleQuery($relationAlias = '', $joinType = Criteria::INNER_JOIN)
	{
		return $this
			->joinCcShowSchedule($relationAlias, $joinType)
			->useQuery($relationAlias ? $relationAlias : 'CcShowSchedule', 'CcShowScheduleQuery');
	}

	/**
	 * Exclude object from result
	 *
	 * @param     CcShow $ccShow Object to remove from the list of results
	 *
	 * @return    CcShowQuery The current query, for fluid interface
	 */
	public function prune($ccShow = null)
	{
		if ($ccShow) {
			$this->addUsingAlias(CcShowPeer::ID, $ccShow->getDbId(), Criteria::NOT_EQUAL);
	  }
	  
		return $this;
	}

} // BaseCcShowQuery
