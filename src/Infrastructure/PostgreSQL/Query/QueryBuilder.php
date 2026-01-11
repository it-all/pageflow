<?php

declare(strict_types=1);

namespace Pageflow\Infrastructure\PostgreSQL\Query;

use Exception;
use Pageflow\Infrastructure\PostgreSQL\PostgresService;
use Pageflow\Infrastructure\Exceptions\QueryFailureException;
use Pageflow\Infrastructure\Exceptions\QueryResultsNotFoundException;

class QueryBuilder
{
    protected $pgConn;
    protected $sql;
    protected $params = array();
    const OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'IS', 'IS NOT', 'LIKE', 'ILIKE'];

    /**
     * QueryBuilder constructor. like add, for convenience
     */
    public function __construct($pgConn)
    {
        $this->pgConn = $pgConn;
        $args = func_get_args();

        if (count($args) > 1) {
            array_shift($args); // remove pgConn
            call_user_func_array(array($this, 'add'), $args);
        }
    }

    /**
     * appends sql and args to query
     * @param string $sql
     * @param array|null $params
     * @return $this
     */
    public function add(string $sql)
    {
        $this->sql .= $sql;
        $args = func_get_args();
        array_shift($args); // remove sql
        if ($args != null) {
            $this->params = array_merge($this->params, $args);
        }
        return $this;
    }

    /**
     * handle null argument for correct sql
     * @param string $name
     * @param $arg
     * @return $this
     */
    public function nullExpression(string $name, $param)
    {
        if ($param === null) {
            $this->sql .= "$name IS null";
        } else {
            $this->params[] = $param;
            $paramNum = count($this->params);
            $this->sql .= "$name = \$$paramNum";
        }
        return $this;
    }

    /**
     * sets sql and args
     * @param string sql
     * @param $args
     */
    public function set(string $sql, array $params)
    {
        $this->sql = $sql;
        $this->params = $params;
    }

    private function alterBooleanArgs()
    {
        foreach ($this->params as $argIndex => $arg) {
            if (is_bool($arg)) {
                $this->params[$argIndex] = self::convertBoolToPostgresBool($arg);
            }
        }
    }

    public function execute(bool $alterBooleanArgs = false)
    {
        if ($alterBooleanArgs) {
            $this->alterBooleanArgs();
        }

        /** query failures within transactions without suppressing errors for pg_query_params caused two errors, only 1 of which was inserted to the database log */
        if (!$result = pg_query_params($this->pgConn, $this->sql, $this->params)) {
            /** note pg_last_error seems to often not return anything, but pg_query_params call will result in php warning */
            $msg = pg_last_error($this->pgConn) . " " . $this->sql;
            if (count($this->params) > 0) {
                $msg .= PHP_EOL . " Args: " . var_export($this->params, true);
            }

            throw new QueryFailureException($msg, E_ERROR);
        }

        $this->resetQuery();
        /** prevent accidental multiple execution */
        return $result;
    }

    public function executeGetArray(bool $alterBooleanArgs = false): ?array
    {
        $pgResult = $this->execute($alterBooleanArgs);
        if (!$results = pg_fetch_all($pgResult)) {
            $results = null;
        }
        pg_free_result($pgResult);
        return $results;
    }

    public function executeGetArrayOneField(string $fieldName, bool $alterBooleanArgs = false): ?array
    {
        if (null === $resultsArray = $this->executeGetArray($alterBooleanArgs)) {
            return null;
        }
        $results = [];
        foreach ($resultsArray as $resultRow) {
            $results[] = $resultRow[$fieldName];
        }
        return $results;
    }

    public function executeGetArrayOneRow(bool $alterBooleanArgs = false): ?array
    {
        if (null === $resultsArray = $this->executeGetArray($alterBooleanArgs)) {
            return null;
        }
        return $resultsArray[0];
    }


    /**
     * In order to receive a column value back for INSERT, UPDATE, and DELETE queries
     * Note that RETURNING can include multiple fields or expressions in SQL, but this method only accepts one field. To receive multiple, simply call execute() instead and process the returned result similar to below
     * Note also that if an invalid $returnField is received, the query still executes prior to throwing the InvalidArgumentException.
     */
    public function executeWithReturnField(string $returnField, bool $alterBooleanArgs = false)
    {
        $this->add(" RETURNING $returnField");

        /** note, if query fails exception thrown in execute */
        $result = $this->execute($alterBooleanArgs);

        if (pg_num_rows($result) > 0) {
            $returned = pg_fetch_all($result);
            if (!isset($returned[0][$returnField])) {
                throw new \InvalidArgumentException("Query executed, but $returnField column does not exist");
            }
            return $returned[0][$returnField];
        } else {
            /** nothing was found - ie an update or delete that found no matches to the WHERE clause */
            throw new QueryResultsNotFoundException();
        }
    }

    /**
     * 3/20/22: deprecated as there is no good way to handle no results.
     * ie if no results returns null or false, that's not good because null or false values also exist.
     * use getRow() / getRow()[0] instead
     * 
     * returns the value of the one column in one record or an array of the entire row if arg flag set true
     * returns null if 0 records result
     * throws exception if query results in recordset
     */
    private function getOne(bool $entireRow = false)
    {
        $result = $this->execute();
        if (pg_num_rows($result) == 1) {
            if ($entireRow) {
                return pg_fetch_array($result);
            } else {
                // make sure only 1 field in query
                if (pg_num_fields($result) == 1) {
                    return pg_fetch_array($result)[0];
                } else {
                    throw new \Exception("Too many result fields");
                }
            }
        } else {
            // either 0 or multiple records in result
            // if 0
            if (pg_num_rows($result) == 0) {
                // no error here. client can error if appropriate
                return null;
            } else {
                throw new \Exception("Multiple results");
            }
        }
    }

    /**
     * returns an array of the record
     * returns null if 0 records result
     * throws exception if query results in recordset
     */
    public function getRow(): ?array
    {
        $result = $this->execute();
        $numRecords = pg_num_rows($result);
        switch ($numRecords) {
            case -1:
                throw new \Exception("Error");
            case 0:
                return null;
            case 1:
                return pg_fetch_array($result);
            default:
                throw new \Exception("Multiple results");
        }
    }

    public function recordExists(): bool
    {
        return null !== $this->getRow();
    }

    public static function validateWhereOperator(string $op): bool
    {
        return in_array(strtoupper($op), self::OPERATORS);
    }

    public static function getWhereOperatorsText(): string
    {
        $ops = "";
        $opCount = 1;
        foreach (self::OPERATORS as $op) {
            $ops .= "$op";
            if ($opCount < count(self::OPERATORS)) {
                $ops .= ", ";
            }
            $opCount++;
        }
        return $ops;
    }

    public function getSql(): ?string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        return null;
    }

    public function getArgs(): array
    {
        return $this->getParams();
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function resetQuery()
    {
        $this->sql = '';
        $this->params = [];
    }

    public static function convertPostgresBoolToBool(string $pgBool): bool
    {
        if ($pgBool !== PostgresService::BOOLEAN_TRUE && $pgBool !== PostgresService::BOOLEAN_FALSE) {
            throw new \InvalidArgumentException("pgBool must be valid postgres boolean");
        }

        return $pgBool === PostgresService::BOOLEAN_TRUE;
    }

    public static function convertBoolToPostgresBool(bool $bool): string
    {
        return ($bool) ? PostgresService::BOOLEAN_TRUE : PostgresService::BOOLEAN_FALSE;
    }

    /** helpful to put null in nullable fields instead of blank string */
    public static function convertEmptyToNull(?string $incoming): ?string
    {
        if (is_string($incoming) && mb_strlen(trim($incoming)) == 0) {
            return null;
        }

        return $incoming;
    }
}
