<?php
declare(strict_types=1);

namespace Pageflow\Infrastructure\PostgreSQL\Query;

abstract class InsertUpdateBuilder extends QueryBuilder
{
    protected $dbTable;

    protected $primaryKeyName;

    function __construct($pgConn, string $dbTable)
    {
        $this->dbTable = $dbTable;
        parent::__construct($pgConn);
    }

    abstract public function addColumn(string $name, $value);

    abstract public function setSql();

    /**
     * calls appropriate parent method to execute query
     * @return recordset
     */
    public function runExecute(bool $alterBooleanArgs = false)
    {
        if (!isset($this->sql)) {
            $this->setSql();
        }
        if (isset($this->primaryKeyName)) {
            return parent::executeWithReturnField($this->primaryKeyName, $alterBooleanArgs);
        } else {
            return parent::execute($alterBooleanArgs);
        }
    }

    public function setPrimaryKeyName(string $name)
    {
        $this->primaryKeyName = $name;
    }
}
