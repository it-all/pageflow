<?php
declare(strict_types=1);

namespace Pageflow\Infrastructure\PostgreSQL\Query;

class UpdateBuilder extends InsertUpdateBuilder
{
    public $updateOnColumnName;
    public $updateOnColumnValue;
    public $setColumnsValues;

    public function __construct(string $dbTable, string $updateOnColumnName, $updateOnColumnValue)
    {
        $this->updateOnColumnName = $updateOnColumnName;
        $this->updateOnColumnValue = $updateOnColumnValue;
        parent::__construct($dbTable);
    }

    /**
     * adds column to update query
     * @param string $name
     * @param $value
     */
    public function addColumn(string $name, $value)
    {
        $this->params[] = $value;
        if (count($this->params) > 1) {
            $this->setColumnsValues .= ", ";
        }
        $argNum = count($this->params);
        $this->setColumnsValues .= "$name = \$".$argNum;
    }

    /**
     * @param array $updateColumns
     */
    public function addColumnsArray(array $updateColumns)
    {
        foreach ($updateColumns as $name => $value) {
            $this->addColumn($name, $value);
        }
    }

    /**
     * sets update query
     */
    public function setSql()
    {
        $this->params[] = $this->updateOnColumnValue;
        $lastArgNum = count($this->params);
        $this->sql = "UPDATE $this->dbTable SET $this->setColumnsValues WHERE $this->updateOnColumnName = $".$lastArgNum;
    }
}
