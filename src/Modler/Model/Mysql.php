<?php

namespace Modler\Model;

use Modler\Model;

class Mysql extends Model
{
    /**
    * Current database object
    *
    * @var object
    */
    private object $db;

    /**
    * Last database error
    *
    * @var string
    */
    private string $lastError = '';

    /**
    * Init the model and set up the database instance
    *     Optionally load data
    *
    * @param object $db   Database instance
    * @param array  $data Optional data to load
    */
    public function __construct(object $db, array $data = array())
    {
        $this->setDb($db);
        parent::__construct($data);
    }

    /**
    * Set the current DB object instance
    *
    * @param object $db Database object
    *
    * @return void
    */
    public function setDb(object $db): void
    {
        $this->db = $db;
    }

    /**
    * Get the current database object instance
    *
    * @return object Database instance
    */
    public function getDb(): object
    {
        return (get_class($this->db) == 'PDO') ? $this->db : $this->db['db'];
    }

    /**
    * Get the current model's table name
    *
    * @return string Table name
    */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
    * Get the last error from the database requests
    *
    * @return string Error message
    */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
    * Make a new model instance
    *
    * @param string $model Model namespace "path"
    *
    * @return object Model instance
    */
    public function makeModelInstance(string $model): object
    {
        $instance = new $model($this->getDb());
        return $instance;
    }

    /**
    * Save the current model - switches between create/update
    *     as needed
    *
    * @param array $data Optional data to save with (overwrites, not appends)
    *
    * @return boolean Success/fail of the operation
    */
    public function save(array $data = array()): bool
    {
        $data = (!empty($data)) ? $data : $this->toArray();

        // see if we have any pre-save
        foreach ($data as $name => $value) {
            $preMethod = 'pre' . ucwords($name);
            if (method_exists($this, $preMethod)) {
                $data[$name] = $this->$preMethod($value);
            }
        }

        if ($this->id === null) {
            return $this->create($data);
        } else {
            return $this->update($data);
        }
    }

    /**
    * "Set up" the needed values for the database requests
    *     (for binding to queries)
    *
    * @param array $data Data to "set up"
    *
    * @return array Set containing the columns and bind values
    */
    public function setup(array $data): array
    {
        $bind = array();
        foreach ($data as $column => $value) {
            $bind[$column] = ':' . $column;
        }

        return array(array_keys($data), $bind);
    }

    /**
    * Execute the request (not a fetch)
    *
    * @param string $sql  SQL statement to execute
    * @param array  $data Data to use in execution
    *
    * @return boolean Success/fail of the operation
    */
    public function execute(string $sql, array $data): bool
    {
        $sth = $this->getDb()->prepare($sql);
        $result = $sth->execute($data);

        if ($result === false) {
            $error = $sth->errorInfo();
            $this->lastError = 'DB ERROR: [' . $sth->errorCode() . '] ' . $error[2];
            error_log($this->lastError);
        }
        return $result;
    }

    /**
    * Fetch the data matching the results of the SQL operation
    *
    * @param string  $sql    SQL statement
    * @param array   $data   Data to use in fetch operation
    * @param boolean $single Only fetch a single record
    *
    * @return array Fetched data
    */
    public function fetch(string $sql, array $data, bool $single = false): array|bool
    {
        $sth = $this->getDb()->prepare($sql);
        $result = $sth->execute($data);

        if ($result === false) {
            $error = $sth->errorInfo();
            $this->lastError = 'DB ERROR: [' . $sth->errorCode() . '] ' . $error[2];
            error_log($this->lastError);
            return false;
        }

        $results = $sth->fetchAll(\PDO::FETCH_ASSOC);
        return ($single === true) ? array_shift($results) : $results;
    }

    /**
    * Create the record (new)
    *
    * @param array $data Data to use in create
    *
    * @return boolean Success/fail of the record creation
    */
    public function create(array $data): bool
    {
        $data['created'] = date('Y-m-d H:i:s');
        $data['updated'] = date('Y-m-d H:i:s');

        list($columns, $bind) = $this->setup($data);

        foreach ($columns as $index => $column) {
            $colName = $this->properties[$column]['column'];
            $columns[$index] = $colName;
        }

        $sql = 'insert into ' . $this->getTableName()
        . ' (' . implode(',', $columns) . ')'
        . ' values (' . implode(',', array_values($bind)) . ')';
        $result = $this->execute($sql, $data);
        if ($result !== false) {
            $this->id = $this->getDb()->lastInsertId();
        }

        return $result;
    }

    /**
    * Update a record
    *
    * @param array $data Data to use in update
    *
    * @return boolean Success/fail of operation
    */
    public function update(array $data = array()): bool
    {
        $data['created'] = date('Y-m-d H:i:s');
        $data['updated'] = date('Y-m-d H:i:s');

        list($columns, $bind) = $this->setup($data);
        $update = array();
        foreach ($bind as $column => $name) {
            $colName = $this->properties[$column]['column'];
            $update[] = $colName . ' = ' . $name;
        }

        $sql = 'update ' . $this->getTableName() . ' set ' . implode(',', $update)
        . ' where ID = ' . $this->id;
        return $this->execute($sql, $data);
    }

    /**
    * Find records matching the "where" data given
    *     All "where" options are appended via "and"
    *
    * @param array $where Data to use in "where" statement
    *
    * @return array Fetched data
    */
    public function find(array $where = array()): array
    {
        $properties = $this->getProperties();
        list($columns, $bind) = $this->setup($where);
        $update = array();
        foreach ($bind as $column => $name) {
            // See if we keep to transfer it over to a column name
            if (array_key_exists($column, $properties)) {
                $column = $properties[$column]['column'];
            }
            $update[] = $column . ' = ' . $name;
        }

        $sql = 'select * from ' . $this->getTableName();

        if (!empty($update)) {
            $sql .= ' where ' . implode(' and ', $update);
        }
        $result = $this->fetch($sql, $where);

        if ($result !== false && count($result) == 1) {
            $this->load($result[0], false);
        }
        return $result;
    }

    /**
    * Load the given data into the current model
    *
    * @param array $data         Property data
    * @param bool  $enforceGuard Ensures that guarded values are not overwritten
    *
    * @return boolean True when complete
    */
    public function load(array $data, bool $enforceGuard = true): bool
    {
        $loadData = array();
        $properties = $this->getProperties();
        foreach ($properties as $propertyName => $propertyDetail) {
            if (!isset($propertyDetail['column'])) {
                if (isset($data[$propertyName])) {
                    $loadData[$propertyName] = $data[$propertyName];
                }
                continue;
            }
            $column = $propertyDetail['column'];
            if (isset($data[$column])) {
                $loadData[$propertyName] = $data[$column];
            }
        }
        parent::load($loadData, $enforceGuard);
        return true;
    }

    /**
    * Delete the current model/record
    *
    * @return boolean Success/fail of delete
    */
    public function delete(): bool
    {
        $where = $this->toArray();
        $properties = $this->getProperties();
        list($columns, $bind) = $this->setup($where);
        $update = array();
        foreach ($bind as $column => $name) {
            // See if we keep to transfer it over to a column name
            if (array_key_exists($column, $properties)) {
                $column = $properties[$column]['column'];
            }
            $update[] = $column . ' = ' . $name;
        }

        $sql = 'delete from ' . $this->getTableName()
        . ' where ' . implode(' and ', $update);
        return $this->execute($sql, $this->toArray());
    }

    /**
    * Find a record by ID
    *
    * @param integer $id ID to locate
    *
    * @return array Matching data
    */
    public function findById(int $id): array
    {
        return $this->find(array('ID' => $id));
    }
}
