<?php


/**
 * Base class that represents a row from the 'cc_stamp' table.
 *
 * 
 *
 * @package    propel.generator.airtime.om
 */
abstract class BaseCcStamp extends BaseObject  implements Persistent
{

	/**
	 * Peer class name
	 */
  const PEER = 'CcStampPeer';

	/**
	 * The Peer class.
	 * Instance provides a convenient way of calling static methods on a class
	 * that calling code may not be able to identify.
	 * @var        CcStampPeer
	 */
	protected static $peer;

	/**
	 * The value for the id field.
	 * @var        int
	 */
	protected $id;

	/**
	 * The value for the show_id field.
	 * @var        int
	 */
	protected $show_id;

	/**
	 * The value for the instance_id field.
	 * @var        int
	 */
	protected $instance_id;

	/**
	 * @var        CcShow
	 */
	protected $aCcShow;

	/**
	 * @var        CcShowInstances
	 */
	protected $aCcShowInstances;

	/**
	 * @var        array CcStampContents[] Collection to store aggregation of CcStampContents objects.
	 */
	protected $collCcStampContentss;

	/**
	 * Flag to prevent endless save loop, if this object is referenced
	 * by another object which falls in this transaction.
	 * @var        boolean
	 */
	protected $alreadyInSave = false;

	/**
	 * Flag to prevent endless validation loop, if this object is referenced
	 * by another object which falls in this transaction.
	 * @var        boolean
	 */
	protected $alreadyInValidation = false;

	/**
	 * Get the [id] column value.
	 * 
	 * @return     int
	 */
	public function getDbId()
	{
		return $this->id;
	}

	/**
	 * Get the [show_id] column value.
	 * 
	 * @return     int
	 */
	public function getDbShowId()
	{
		return $this->show_id;
	}

	/**
	 * Get the [instance_id] column value.
	 * 
	 * @return     int
	 */
	public function getDbInstanceId()
	{
		return $this->instance_id;
	}

	/**
	 * Set the value of [id] column.
	 * 
	 * @param      int $v new value
	 * @return     CcStamp The current object (for fluent API support)
	 */
	public function setDbId($v)
	{
		if ($v !== null) {
			$v = (int) $v;
		}

		if ($this->id !== $v) {
			$this->id = $v;
			$this->modifiedColumns[] = CcStampPeer::ID;
		}

		return $this;
	} // setDbId()

	/**
	 * Set the value of [show_id] column.
	 * 
	 * @param      int $v new value
	 * @return     CcStamp The current object (for fluent API support)
	 */
	public function setDbShowId($v)
	{
		if ($v !== null) {
			$v = (int) $v;
		}

		if ($this->show_id !== $v) {
			$this->show_id = $v;
			$this->modifiedColumns[] = CcStampPeer::SHOW_ID;
		}

		if ($this->aCcShow !== null && $this->aCcShow->getDbId() !== $v) {
			$this->aCcShow = null;
		}

		return $this;
	} // setDbShowId()

	/**
	 * Set the value of [instance_id] column.
	 * 
	 * @param      int $v new value
	 * @return     CcStamp The current object (for fluent API support)
	 */
	public function setDbInstanceId($v)
	{
		if ($v !== null) {
			$v = (int) $v;
		}

		if ($this->instance_id !== $v) {
			$this->instance_id = $v;
			$this->modifiedColumns[] = CcStampPeer::INSTANCE_ID;
		}

		if ($this->aCcShowInstances !== null && $this->aCcShowInstances->getDbId() !== $v) {
			$this->aCcShowInstances = null;
		}

		return $this;
	} // setDbInstanceId()

	/**
	 * Indicates whether the columns in this object are only set to default values.
	 *
	 * This method can be used in conjunction with isModified() to indicate whether an object is both
	 * modified _and_ has some values set which are non-default.
	 *
	 * @return     boolean Whether the columns in this object are only been set with default values.
	 */
	public function hasOnlyDefaultValues()
	{
		// otherwise, everything was equal, so return TRUE
		return true;
	} // hasOnlyDefaultValues()

	/**
	 * Hydrates (populates) the object variables with values from the database resultset.
	 *
	 * An offset (0-based "start column") is specified so that objects can be hydrated
	 * with a subset of the columns in the resultset rows.  This is needed, for example,
	 * for results of JOIN queries where the resultset row includes columns from two or
	 * more tables.
	 *
	 * @param      array $row The row returned by PDOStatement->fetch(PDO::FETCH_NUM)
	 * @param      int $startcol 0-based offset column which indicates which restultset column to start with.
	 * @param      boolean $rehydrate Whether this object is being re-hydrated from the database.
	 * @return     int next starting column
	 * @throws     PropelException  - Any caught Exception will be rewrapped as a PropelException.
	 */
	public function hydrate($row, $startcol = 0, $rehydrate = false)
	{
		try {

			$this->id = ($row[$startcol + 0] !== null) ? (int) $row[$startcol + 0] : null;
			$this->show_id = ($row[$startcol + 1] !== null) ? (int) $row[$startcol + 1] : null;
			$this->instance_id = ($row[$startcol + 2] !== null) ? (int) $row[$startcol + 2] : null;
			$this->resetModified();

			$this->setNew(false);

			if ($rehydrate) {
				$this->ensureConsistency();
			}

			return $startcol + 3; // 3 = CcStampPeer::NUM_COLUMNS - CcStampPeer::NUM_LAZY_LOAD_COLUMNS).

		} catch (Exception $e) {
			throw new PropelException("Error populating CcStamp object", $e);
		}
	}

	/**
	 * Checks and repairs the internal consistency of the object.
	 *
	 * This method is executed after an already-instantiated object is re-hydrated
	 * from the database.  It exists to check any foreign keys to make sure that
	 * the objects related to the current object are correct based on foreign key.
	 *
	 * You can override this method in the stub class, but you should always invoke
	 * the base method from the overridden method (i.e. parent::ensureConsistency()),
	 * in case your model changes.
	 *
	 * @throws     PropelException
	 */
	public function ensureConsistency()
	{

		if ($this->aCcShow !== null && $this->show_id !== $this->aCcShow->getDbId()) {
			$this->aCcShow = null;
		}
		if ($this->aCcShowInstances !== null && $this->instance_id !== $this->aCcShowInstances->getDbId()) {
			$this->aCcShowInstances = null;
		}
	} // ensureConsistency

	/**
	 * Reloads this object from datastore based on primary key and (optionally) resets all associated objects.
	 *
	 * This will only work if the object has been saved and has a valid primary key set.
	 *
	 * @param      boolean $deep (optional) Whether to also de-associated any related objects.
	 * @param      PropelPDO $con (optional) The PropelPDO connection to use.
	 * @return     void
	 * @throws     PropelException - if this object is deleted, unsaved or doesn't have pk match in db
	 */
	public function reload($deep = false, PropelPDO $con = null)
	{
		if ($this->isDeleted()) {
			throw new PropelException("Cannot reload a deleted object.");
		}

		if ($this->isNew()) {
			throw new PropelException("Cannot reload an unsaved object.");
		}

		if ($con === null) {
			$con = Propel::getConnection(CcStampPeer::DATABASE_NAME, Propel::CONNECTION_READ);
		}

		// We don't need to alter the object instance pool; we're just modifying this instance
		// already in the pool.

		$stmt = CcStampPeer::doSelectStmt($this->buildPkeyCriteria(), $con);
		$row = $stmt->fetch(PDO::FETCH_NUM);
		$stmt->closeCursor();
		if (!$row) {
			throw new PropelException('Cannot find matching row in the database to reload object values.');
		}
		$this->hydrate($row, 0, true); // rehydrate

		if ($deep) {  // also de-associate any related objects?

			$this->aCcShow = null;
			$this->aCcShowInstances = null;
			$this->collCcStampContentss = null;

		} // if (deep)
	}

	/**
	 * Removes this object from datastore and sets delete attribute.
	 *
	 * @param      PropelPDO $con
	 * @return     void
	 * @throws     PropelException
	 * @see        BaseObject::setDeleted()
	 * @see        BaseObject::isDeleted()
	 */
	public function delete(PropelPDO $con = null)
	{
		if ($this->isDeleted()) {
			throw new PropelException("This object has already been deleted.");
		}

		if ($con === null) {
			$con = Propel::getConnection(CcStampPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
		}
		
		$con->beginTransaction();
		try {
			$ret = $this->preDelete($con);
			if ($ret) {
				CcStampQuery::create()
					->filterByPrimaryKey($this->getPrimaryKey())
					->delete($con);
				$this->postDelete($con);
				$con->commit();
				$this->setDeleted(true);
			} else {
				$con->commit();
			}
		} catch (PropelException $e) {
			$con->rollBack();
			throw $e;
		}
	}

	/**
	 * Persists this object to the database.
	 *
	 * If the object is new, it inserts it; otherwise an update is performed.
	 * All modified related objects will also be persisted in the doSave()
	 * method.  This method wraps all precipitate database operations in a
	 * single transaction.
	 *
	 * @param      PropelPDO $con
	 * @return     int The number of rows affected by this insert/update and any referring fk objects' save() operations.
	 * @throws     PropelException
	 * @see        doSave()
	 */
	public function save(PropelPDO $con = null)
	{
		if ($this->isDeleted()) {
			throw new PropelException("You cannot save an object that has been deleted.");
		}

		if ($con === null) {
			$con = Propel::getConnection(CcStampPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
		}
		
		$con->beginTransaction();
		$isInsert = $this->isNew();
		try {
			$ret = $this->preSave($con);
			if ($isInsert) {
				$ret = $ret && $this->preInsert($con);
			} else {
				$ret = $ret && $this->preUpdate($con);
			}
			if ($ret) {
				$affectedRows = $this->doSave($con);
				if ($isInsert) {
					$this->postInsert($con);
				} else {
					$this->postUpdate($con);
				}
				$this->postSave($con);
				CcStampPeer::addInstanceToPool($this);
			} else {
				$affectedRows = 0;
			}
			$con->commit();
			return $affectedRows;
		} catch (PropelException $e) {
			$con->rollBack();
			throw $e;
		}
	}

	/**
	 * Performs the work of inserting or updating the row in the database.
	 *
	 * If the object is new, it inserts it; otherwise an update is performed.
	 * All related objects are also updated in this method.
	 *
	 * @param      PropelPDO $con
	 * @return     int The number of rows affected by this insert/update and any referring fk objects' save() operations.
	 * @throws     PropelException
	 * @see        save()
	 */
	protected function doSave(PropelPDO $con)
	{
		$affectedRows = 0; // initialize var to track total num of affected rows
		if (!$this->alreadyInSave) {
			$this->alreadyInSave = true;

			// We call the save method on the following object(s) if they
			// were passed to this object by their coresponding set
			// method.  This object relates to these object(s) by a
			// foreign key reference.

			if ($this->aCcShow !== null) {
				if ($this->aCcShow->isModified() || $this->aCcShow->isNew()) {
					$affectedRows += $this->aCcShow->save($con);
				}
				$this->setCcShow($this->aCcShow);
			}

			if ($this->aCcShowInstances !== null) {
				if ($this->aCcShowInstances->isModified() || $this->aCcShowInstances->isNew()) {
					$affectedRows += $this->aCcShowInstances->save($con);
				}
				$this->setCcShowInstances($this->aCcShowInstances);
			}

			if ($this->isNew() ) {
				$this->modifiedColumns[] = CcStampPeer::ID;
			}

			// If this object has been modified, then save it to the database.
			if ($this->isModified()) {
				if ($this->isNew()) {
					$criteria = $this->buildCriteria();
					if ($criteria->keyContainsValue(CcStampPeer::ID) ) {
						throw new PropelException('Cannot insert a value for auto-increment primary key ('.CcStampPeer::ID.')');
					}

					$pk = BasePeer::doInsert($criteria, $con);
					$affectedRows += 1;
					$this->setDbId($pk);  //[IMV] update autoincrement primary key
					$this->setNew(false);
				} else {
					$affectedRows += CcStampPeer::doUpdate($this, $con);
				}

				$this->resetModified(); // [HL] After being saved an object is no longer 'modified'
			}

			if ($this->collCcStampContentss !== null) {
				foreach ($this->collCcStampContentss as $referrerFK) {
					if (!$referrerFK->isDeleted()) {
						$affectedRows += $referrerFK->save($con);
					}
				}
			}

			$this->alreadyInSave = false;

		}
		return $affectedRows;
	} // doSave()

	/**
	 * Array of ValidationFailed objects.
	 * @var        array ValidationFailed[]
	 */
	protected $validationFailures = array();

	/**
	 * Gets any ValidationFailed objects that resulted from last call to validate().
	 *
	 *
	 * @return     array ValidationFailed[]
	 * @see        validate()
	 */
	public function getValidationFailures()
	{
		return $this->validationFailures;
	}

	/**
	 * Validates the objects modified field values and all objects related to this table.
	 *
	 * If $columns is either a column name or an array of column names
	 * only those columns are validated.
	 *
	 * @param      mixed $columns Column name or an array of column names.
	 * @return     boolean Whether all columns pass validation.
	 * @see        doValidate()
	 * @see        getValidationFailures()
	 */
	public function validate($columns = null)
	{
		$res = $this->doValidate($columns);
		if ($res === true) {
			$this->validationFailures = array();
			return true;
		} else {
			$this->validationFailures = $res;
			return false;
		}
	}

	/**
	 * This function performs the validation work for complex object models.
	 *
	 * In addition to checking the current object, all related objects will
	 * also be validated.  If all pass then <code>true</code> is returned; otherwise
	 * an aggreagated array of ValidationFailed objects will be returned.
	 *
	 * @param      array $columns Array of column names to validate.
	 * @return     mixed <code>true</code> if all validations pass; array of <code>ValidationFailed</code> objets otherwise.
	 */
	protected function doValidate($columns = null)
	{
		if (!$this->alreadyInValidation) {
			$this->alreadyInValidation = true;
			$retval = null;

			$failureMap = array();


			// We call the validate method on the following object(s) if they
			// were passed to this object by their coresponding set
			// method.  This object relates to these object(s) by a
			// foreign key reference.

			if ($this->aCcShow !== null) {
				if (!$this->aCcShow->validate($columns)) {
					$failureMap = array_merge($failureMap, $this->aCcShow->getValidationFailures());
				}
			}

			if ($this->aCcShowInstances !== null) {
				if (!$this->aCcShowInstances->validate($columns)) {
					$failureMap = array_merge($failureMap, $this->aCcShowInstances->getValidationFailures());
				}
			}


			if (($retval = CcStampPeer::doValidate($this, $columns)) !== true) {
				$failureMap = array_merge($failureMap, $retval);
			}


				if ($this->collCcStampContentss !== null) {
					foreach ($this->collCcStampContentss as $referrerFK) {
						if (!$referrerFK->validate($columns)) {
							$failureMap = array_merge($failureMap, $referrerFK->getValidationFailures());
						}
					}
				}


			$this->alreadyInValidation = false;
		}

		return (!empty($failureMap) ? $failureMap : true);
	}

	/**
	 * Retrieves a field from the object by name passed in as a string.
	 *
	 * @param      string $name name
	 * @param      string $type The type of fieldname the $name is of:
	 *                     one of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
	 *                     BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
	 * @return     mixed Value of field.
	 */
	public function getByName($name, $type = BasePeer::TYPE_PHPNAME)
	{
		$pos = CcStampPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
		$field = $this->getByPosition($pos);
		return $field;
	}

	/**
	 * Retrieves a field from the object by Position as specified in the xml schema.
	 * Zero-based.
	 *
	 * @param      int $pos position in xml schema
	 * @return     mixed Value of field at $pos
	 */
	public function getByPosition($pos)
	{
		switch($pos) {
			case 0:
				return $this->getDbId();
				break;
			case 1:
				return $this->getDbShowId();
				break;
			case 2:
				return $this->getDbInstanceId();
				break;
			default:
				return null;
				break;
		} // switch()
	}

	/**
	 * Exports the object as an array.
	 *
	 * You can specify the key type of the array by passing one of the class
	 * type constants.
	 *
	 * @param     string  $keyType (optional) One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME,
	 *                    BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM. 
	 *                    Defaults to BasePeer::TYPE_PHPNAME.
	 * @param     boolean $includeLazyLoadColumns (optional) Whether to include lazy loaded columns. Defaults to TRUE.
	 * @param     boolean $includeForeignObjects (optional) Whether to include hydrated related objects. Default to FALSE.
	 *
	 * @return    array an associative array containing the field names (as keys) and field values
	 */
	public function toArray($keyType = BasePeer::TYPE_PHPNAME, $includeLazyLoadColumns = true, $includeForeignObjects = false)
	{
		$keys = CcStampPeer::getFieldNames($keyType);
		$result = array(
			$keys[0] => $this->getDbId(),
			$keys[1] => $this->getDbShowId(),
			$keys[2] => $this->getDbInstanceId(),
		);
		if ($includeForeignObjects) {
			if (null !== $this->aCcShow) {
				$result['CcShow'] = $this->aCcShow->toArray($keyType, $includeLazyLoadColumns, true);
			}
			if (null !== $this->aCcShowInstances) {
				$result['CcShowInstances'] = $this->aCcShowInstances->toArray($keyType, $includeLazyLoadColumns, true);
			}
		}
		return $result;
	}

	/**
	 * Sets a field from the object by name passed in as a string.
	 *
	 * @param      string $name peer name
	 * @param      mixed $value field value
	 * @param      string $type The type of fieldname the $name is of:
	 *                     one of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
	 *                     BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
	 * @return     void
	 */
	public function setByName($name, $value, $type = BasePeer::TYPE_PHPNAME)
	{
		$pos = CcStampPeer::translateFieldName($name, $type, BasePeer::TYPE_NUM);
		return $this->setByPosition($pos, $value);
	}

	/**
	 * Sets a field from the object by Position as specified in the xml schema.
	 * Zero-based.
	 *
	 * @param      int $pos position in xml schema
	 * @param      mixed $value field value
	 * @return     void
	 */
	public function setByPosition($pos, $value)
	{
		switch($pos) {
			case 0:
				$this->setDbId($value);
				break;
			case 1:
				$this->setDbShowId($value);
				break;
			case 2:
				$this->setDbInstanceId($value);
				break;
		} // switch()
	}

	/**
	 * Populates the object using an array.
	 *
	 * This is particularly useful when populating an object from one of the
	 * request arrays (e.g. $_POST).  This method goes through the column
	 * names, checking to see whether a matching key exists in populated
	 * array. If so the setByName() method is called for that column.
	 *
	 * You can specify the key type of the array by additionally passing one
	 * of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME,
	 * BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM.
	 * The default key type is the column's phpname (e.g. 'AuthorId')
	 *
	 * @param      array  $arr     An array to populate the object from.
	 * @param      string $keyType The type of keys the array uses.
	 * @return     void
	 */
	public function fromArray($arr, $keyType = BasePeer::TYPE_PHPNAME)
	{
		$keys = CcStampPeer::getFieldNames($keyType);

		if (array_key_exists($keys[0], $arr)) $this->setDbId($arr[$keys[0]]);
		if (array_key_exists($keys[1], $arr)) $this->setDbShowId($arr[$keys[1]]);
		if (array_key_exists($keys[2], $arr)) $this->setDbInstanceId($arr[$keys[2]]);
	}

	/**
	 * Build a Criteria object containing the values of all modified columns in this object.
	 *
	 * @return     Criteria The Criteria object containing all modified values.
	 */
	public function buildCriteria()
	{
		$criteria = new Criteria(CcStampPeer::DATABASE_NAME);

		if ($this->isColumnModified(CcStampPeer::ID)) $criteria->add(CcStampPeer::ID, $this->id);
		if ($this->isColumnModified(CcStampPeer::SHOW_ID)) $criteria->add(CcStampPeer::SHOW_ID, $this->show_id);
		if ($this->isColumnModified(CcStampPeer::INSTANCE_ID)) $criteria->add(CcStampPeer::INSTANCE_ID, $this->instance_id);

		return $criteria;
	}

	/**
	 * Builds a Criteria object containing the primary key for this object.
	 *
	 * Unlike buildCriteria() this method includes the primary key values regardless
	 * of whether or not they have been modified.
	 *
	 * @return     Criteria The Criteria object containing value(s) for primary key(s).
	 */
	public function buildPkeyCriteria()
	{
		$criteria = new Criteria(CcStampPeer::DATABASE_NAME);
		$criteria->add(CcStampPeer::ID, $this->id);

		return $criteria;
	}

	/**
	 * Returns the primary key for this object (row).
	 * @return     int
	 */
	public function getPrimaryKey()
	{
		return $this->getDbId();
	}

	/**
	 * Generic method to set the primary key (id column).
	 *
	 * @param      int $key Primary key.
	 * @return     void
	 */
	public function setPrimaryKey($key)
	{
		$this->setDbId($key);
	}

	/**
	 * Returns true if the primary key for this object is null.
	 * @return     boolean
	 */
	public function isPrimaryKeyNull()
	{
		return null === $this->getDbId();
	}

	/**
	 * Sets contents of passed object to values from current object.
	 *
	 * If desired, this method can also make copies of all associated (fkey referrers)
	 * objects.
	 *
	 * @param      object $copyObj An object of CcStamp (or compatible) type.
	 * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
	 * @throws     PropelException
	 */
	public function copyInto($copyObj, $deepCopy = false)
	{
		$copyObj->setDbShowId($this->show_id);
		$copyObj->setDbInstanceId($this->instance_id);

		if ($deepCopy) {
			// important: temporarily setNew(false) because this affects the behavior of
			// the getter/setter methods for fkey referrer objects.
			$copyObj->setNew(false);

			foreach ($this->getCcStampContentss() as $relObj) {
				if ($relObj !== $this) {  // ensure that we don't try to copy a reference to ourselves
					$copyObj->addCcStampContents($relObj->copy($deepCopy));
				}
			}

		} // if ($deepCopy)


		$copyObj->setNew(true);
		$copyObj->setDbId(NULL); // this is a auto-increment column, so set to default value
	}

	/**
	 * Makes a copy of this object that will be inserted as a new row in table when saved.
	 * It creates a new object filling in the simple attributes, but skipping any primary
	 * keys that are defined for the table.
	 *
	 * If desired, this method can also make copies of all associated (fkey referrers)
	 * objects.
	 *
	 * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
	 * @return     CcStamp Clone of current object.
	 * @throws     PropelException
	 */
	public function copy($deepCopy = false)
	{
		// we use get_class(), because this might be a subclass
		$clazz = get_class($this);
		$copyObj = new $clazz();
		$this->copyInto($copyObj, $deepCopy);
		return $copyObj;
	}

	/**
	 * Returns a peer instance associated with this om.
	 *
	 * Since Peer classes are not to have any instance attributes, this method returns the
	 * same instance for all member of this class. The method could therefore
	 * be static, but this would prevent one from overriding the behavior.
	 *
	 * @return     CcStampPeer
	 */
	public function getPeer()
	{
		if (self::$peer === null) {
			self::$peer = new CcStampPeer();
		}
		return self::$peer;
	}

	/**
	 * Declares an association between this object and a CcShow object.
	 *
	 * @param      CcShow $v
	 * @return     CcStamp The current object (for fluent API support)
	 * @throws     PropelException
	 */
	public function setCcShow(CcShow $v = null)
	{
		if ($v === null) {
			$this->setDbShowId(NULL);
		} else {
			$this->setDbShowId($v->getDbId());
		}

		$this->aCcShow = $v;

		// Add binding for other direction of this n:n relationship.
		// If this object has already been added to the CcShow object, it will not be re-added.
		if ($v !== null) {
			$v->addCcStamp($this);
		}

		return $this;
	}


	/**
	 * Get the associated CcShow object
	 *
	 * @param      PropelPDO Optional Connection object.
	 * @return     CcShow The associated CcShow object.
	 * @throws     PropelException
	 */
	public function getCcShow(PropelPDO $con = null)
	{
		if ($this->aCcShow === null && ($this->show_id !== null)) {
			$this->aCcShow = CcShowQuery::create()->findPk($this->show_id, $con);
			/* The following can be used additionally to
			   guarantee the related object contains a reference
			   to this object.  This level of coupling may, however, be
			   undesirable since it could result in an only partially populated collection
			   in the referenced object.
			   $this->aCcShow->addCcStamps($this);
			 */
		}
		return $this->aCcShow;
	}

	/**
	 * Declares an association between this object and a CcShowInstances object.
	 *
	 * @param      CcShowInstances $v
	 * @return     CcStamp The current object (for fluent API support)
	 * @throws     PropelException
	 */
	public function setCcShowInstances(CcShowInstances $v = null)
	{
		if ($v === null) {
			$this->setDbInstanceId(NULL);
		} else {
			$this->setDbInstanceId($v->getDbId());
		}

		$this->aCcShowInstances = $v;

		// Add binding for other direction of this n:n relationship.
		// If this object has already been added to the CcShowInstances object, it will not be re-added.
		if ($v !== null) {
			$v->addCcStamp($this);
		}

		return $this;
	}


	/**
	 * Get the associated CcShowInstances object
	 *
	 * @param      PropelPDO Optional Connection object.
	 * @return     CcShowInstances The associated CcShowInstances object.
	 * @throws     PropelException
	 */
	public function getCcShowInstances(PropelPDO $con = null)
	{
		if ($this->aCcShowInstances === null && ($this->instance_id !== null)) {
			$this->aCcShowInstances = CcShowInstancesQuery::create()->findPk($this->instance_id, $con);
			/* The following can be used additionally to
			   guarantee the related object contains a reference
			   to this object.  This level of coupling may, however, be
			   undesirable since it could result in an only partially populated collection
			   in the referenced object.
			   $this->aCcShowInstances->addCcStamps($this);
			 */
		}
		return $this->aCcShowInstances;
	}

	/**
	 * Clears out the collCcStampContentss collection
	 *
	 * This does not modify the database; however, it will remove any associated objects, causing
	 * them to be refetched by subsequent calls to accessor method.
	 *
	 * @return     void
	 * @see        addCcStampContentss()
	 */
	public function clearCcStampContentss()
	{
		$this->collCcStampContentss = null; // important to set this to NULL since that means it is uninitialized
	}

	/**
	 * Initializes the collCcStampContentss collection.
	 *
	 * By default this just sets the collCcStampContentss collection to an empty array (like clearcollCcStampContentss());
	 * however, you may wish to override this method in your stub class to provide setting appropriate
	 * to your application -- for example, setting the initial array to the values stored in database.
	 *
	 * @return     void
	 */
	public function initCcStampContentss()
	{
		$this->collCcStampContentss = new PropelObjectCollection();
		$this->collCcStampContentss->setModel('CcStampContents');
	}

	/**
	 * Gets an array of CcStampContents objects which contain a foreign key that references this object.
	 *
	 * If the $criteria is not null, it is used to always fetch the results from the database.
	 * Otherwise the results are fetched from the database the first time, then cached.
	 * Next time the same method is called without $criteria, the cached collection is returned.
	 * If this CcStamp is new, it will return
	 * an empty collection or the current collection; the criteria is ignored on a new object.
	 *
	 * @param      Criteria $criteria optional Criteria object to narrow the query
	 * @param      PropelPDO $con optional connection object
	 * @return     PropelCollection|array CcStampContents[] List of CcStampContents objects
	 * @throws     PropelException
	 */
	public function getCcStampContentss($criteria = null, PropelPDO $con = null)
	{
		if(null === $this->collCcStampContentss || null !== $criteria) {
			if ($this->isNew() && null === $this->collCcStampContentss) {
				// return empty collection
				$this->initCcStampContentss();
			} else {
				$collCcStampContentss = CcStampContentsQuery::create(null, $criteria)
					->filterByCcStamp($this)
					->find($con);
				if (null !== $criteria) {
					return $collCcStampContentss;
				}
				$this->collCcStampContentss = $collCcStampContentss;
			}
		}
		return $this->collCcStampContentss;
	}

	/**
	 * Returns the number of related CcStampContents objects.
	 *
	 * @param      Criteria $criteria
	 * @param      boolean $distinct
	 * @param      PropelPDO $con
	 * @return     int Count of related CcStampContents objects.
	 * @throws     PropelException
	 */
	public function countCcStampContentss(Criteria $criteria = null, $distinct = false, PropelPDO $con = null)
	{
		if(null === $this->collCcStampContentss || null !== $criteria) {
			if ($this->isNew() && null === $this->collCcStampContentss) {
				return 0;
			} else {
				$query = CcStampContentsQuery::create(null, $criteria);
				if($distinct) {
					$query->distinct();
				}
				return $query
					->filterByCcStamp($this)
					->count($con);
			}
		} else {
			return count($this->collCcStampContentss);
		}
	}

	/**
	 * Method called to associate a CcStampContents object to this object
	 * through the CcStampContents foreign key attribute.
	 *
	 * @param      CcStampContents $l CcStampContents
	 * @return     void
	 * @throws     PropelException
	 */
	public function addCcStampContents(CcStampContents $l)
	{
		if ($this->collCcStampContentss === null) {
			$this->initCcStampContentss();
		}
		if (!$this->collCcStampContentss->contains($l)) { // only add it if the **same** object is not already associated
			$this->collCcStampContentss[]= $l;
			$l->setCcStamp($this);
		}
	}


	/**
	 * If this collection has already been initialized with
	 * an identical criteria, it returns the collection.
	 * Otherwise if this CcStamp is new, it will return
	 * an empty collection; or if this CcStamp has previously
	 * been saved, it will retrieve related CcStampContentss from storage.
	 *
	 * This method is protected by default in order to keep the public
	 * api reasonable.  You can provide public methods for those you
	 * actually need in CcStamp.
	 *
	 * @param      Criteria $criteria optional Criteria object to narrow the query
	 * @param      PropelPDO $con optional connection object
	 * @param      string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
	 * @return     PropelCollection|array CcStampContents[] List of CcStampContents objects
	 */
	public function getCcStampContentssJoinCcFiles($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
	{
		$query = CcStampContentsQuery::create(null, $criteria);
		$query->joinWith('CcFiles', $join_behavior);

		return $this->getCcStampContentss($query, $con);
	}


	/**
	 * If this collection has already been initialized with
	 * an identical criteria, it returns the collection.
	 * Otherwise if this CcStamp is new, it will return
	 * an empty collection; or if this CcStamp has previously
	 * been saved, it will retrieve related CcStampContentss from storage.
	 *
	 * This method is protected by default in order to keep the public
	 * api reasonable.  You can provide public methods for those you
	 * actually need in CcStamp.
	 *
	 * @param      Criteria $criteria optional Criteria object to narrow the query
	 * @param      PropelPDO $con optional connection object
	 * @param      string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
	 * @return     PropelCollection|array CcStampContents[] List of CcStampContents objects
	 */
	public function getCcStampContentssJoinCcWebstream($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
	{
		$query = CcStampContentsQuery::create(null, $criteria);
		$query->joinWith('CcWebstream', $join_behavior);

		return $this->getCcStampContentss($query, $con);
	}


	/**
	 * If this collection has already been initialized with
	 * an identical criteria, it returns the collection.
	 * Otherwise if this CcStamp is new, it will return
	 * an empty collection; or if this CcStamp has previously
	 * been saved, it will retrieve related CcStampContentss from storage.
	 *
	 * This method is protected by default in order to keep the public
	 * api reasonable.  You can provide public methods for those you
	 * actually need in CcStamp.
	 *
	 * @param      Criteria $criteria optional Criteria object to narrow the query
	 * @param      PropelPDO $con optional connection object
	 * @param      string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
	 * @return     PropelCollection|array CcStampContents[] List of CcStampContents objects
	 */
	public function getCcStampContentssJoinCcBlock($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
	{
		$query = CcStampContentsQuery::create(null, $criteria);
		$query->joinWith('CcBlock', $join_behavior);

		return $this->getCcStampContentss($query, $con);
	}


	/**
	 * If this collection has already been initialized with
	 * an identical criteria, it returns the collection.
	 * Otherwise if this CcStamp is new, it will return
	 * an empty collection; or if this CcStamp has previously
	 * been saved, it will retrieve related CcStampContentss from storage.
	 *
	 * This method is protected by default in order to keep the public
	 * api reasonable.  You can provide public methods for those you
	 * actually need in CcStamp.
	 *
	 * @param      Criteria $criteria optional Criteria object to narrow the query
	 * @param      PropelPDO $con optional connection object
	 * @param      string $join_behavior optional join type to use (defaults to Criteria::LEFT_JOIN)
	 * @return     PropelCollection|array CcStampContents[] List of CcStampContents objects
	 */
	public function getCcStampContentssJoinCcPlaylist($criteria = null, $con = null, $join_behavior = Criteria::LEFT_JOIN)
	{
		$query = CcStampContentsQuery::create(null, $criteria);
		$query->joinWith('CcPlaylist', $join_behavior);

		return $this->getCcStampContentss($query, $con);
	}

	/**
	 * Clears the current object and sets all attributes to their default values
	 */
	public function clear()
	{
		$this->id = null;
		$this->show_id = null;
		$this->instance_id = null;
		$this->alreadyInSave = false;
		$this->alreadyInValidation = false;
		$this->clearAllReferences();
		$this->resetModified();
		$this->setNew(true);
		$this->setDeleted(false);
	}

	/**
	 * Resets all collections of referencing foreign keys.
	 *
	 * This method is a user-space workaround for PHP's inability to garbage collect objects
	 * with circular references.  This is currently necessary when using Propel in certain
	 * daemon or large-volumne/high-memory operations.
	 *
	 * @param      boolean $deep Whether to also clear the references on all associated objects.
	 */
	public function clearAllReferences($deep = false)
	{
		if ($deep) {
			if ($this->collCcStampContentss) {
				foreach ((array) $this->collCcStampContentss as $o) {
					$o->clearAllReferences($deep);
				}
			}
		} // if ($deep)

		$this->collCcStampContentss = null;
		$this->aCcShow = null;
		$this->aCcShowInstances = null;
	}

	/**
	 * Catches calls to virtual methods
	 */
	public function __call($name, $params)
	{
		if (preg_match('/get(\w+)/', $name, $matches)) {
			$virtualColumn = $matches[1];
			if ($this->hasVirtualColumn($virtualColumn)) {
				return $this->getVirtualColumn($virtualColumn);
			}
			// no lcfirst in php<5.3...
			$virtualColumn[0] = strtolower($virtualColumn[0]);
			if ($this->hasVirtualColumn($virtualColumn)) {
				return $this->getVirtualColumn($virtualColumn);
			}
		}
		throw new PropelException('Call to undefined method: ' . $name);
	}

} // BaseCcStamp
