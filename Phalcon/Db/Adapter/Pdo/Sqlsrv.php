<?php

namespace Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\Column;
use Phalcon\Db\Result\PdoSqlsrv as ResultPdo;

/**
 * Phalcon\Db\Adapter\Pdo\Sqlsrv
 * Specific functions for the MsSQL database system
 * <code>
 * $config = array(
 * "host" => "192.168.0.11",
 * "dbname" => "blog",
 * "port" => 3306,
 * "username" => "sigma",
 * "password" => "secret"
 * );
 * $connection = new \Phalcon\Db\Adapter\Pdo\Sqlsrv($config);
 * </code>.
 *
 * @property \Phalcon\Db\Dialect\Sqlsrv $_dialect
 */
class Sqlsrv extends \Phalcon\Db\Adapter\Pdo implements \Phalcon\Db\AdapterInterface {

    protected $_type = 'sqlsrv';
    protected $_dialectType = 'sqlsrv';
    protected $_lastID = false;

    /**
     * This method is automatically called in Phalcon\Db\Adapter\Pdo constructor.
     * Call it when you need to restore a database connection.
     *
     * @param array $descriptor
     *
     * @return bool
     */
    public function connect(array $descriptor = null) {
        if (is_null($descriptor) === true) {
            $descriptor = $this->_descriptor;
        }

        /*
         * Check if the developer has defined custom options or create one from scratch
         */
        if (isset($descriptor['options']) === true) {
            $options = $descriptor['options'];
            unset($descriptor['options']);
        } else {
            $options = array();
        }

        $dsn = "sqlsrv:server=" . $descriptor['host'] . ";database=" . $descriptor['dbname'] . ";";
        $dbusername = $descriptor['username'];
        $dbpassword = $descriptor['password'];

        $this->_pdo = new \PDO($dsn, $dbusername, $dbpassword);
        $this->_pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        /*
         * Set dialect class
         */
        if (isset($descriptor['dialectClass']) === false) {
            $dialectClass = 'Phalcon\\Db\\Dialect\\' . ucfirst($this->_dialectType);
        } else {
            $dialectClass = $descriptor['dialectClass'];
        }

        /*
         * Create the instance only if the dialect is a string
         */
        if (is_string($dialectClass) === true) {
            $dialectObject = new $dialectClass();
            $this->_dialect = $dialectObject;
        }
    }

    /**
     * Returns an array of Phalcon\Db\Column objects describing a table
     * <code>
     * print_r($connection->describeColumns("posts"));
     * </code>.
     *
     * @param string $table
     * @param string $schema
     *
     * @return \Phalcon\Db\Column
     */
    public function describeColumns($table, $schema = null) {
        $oldColumn = null;

        /*
         * Get primary keys
         */
        $primaryKeys = array();
        foreach ($this->fetchAll($this->_dialect->getPrimaryKey($table, $schema)) as $field) {
            $primaryKeys[$field['COLUMN_NAME']] = true;
        }

        /*
         * Get the SQL to describe a table
         * We're using FETCH_NUM to fetch the columns
         * Get the describe
         * Field Indexes: 0:name, 1:type, 2:not null, 3:key, 4:default, 5:extra
         */
        foreach ($this->fetchAll($this->_dialect->describeColumns($table, $schema)) as $field) {
            /*
             * By default the bind types is two
             */
            $definition = array('bindType' => Column::BIND_PARAM_STR);

            /*
             * By checking every column type we convert it to a Phalcon\Db\Column
             */
            $autoIncrement = false;
            $columnType = $field['TYPE_NAME'];
            switch ($columnType) {
                /*
                 * Smallint/Bigint/Integers/Int are int
                 */
                case 'int identity':
                case 'tinyint identity':
                case 'smallint identity':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    $autoIncrement = true;
                    break;
                case 'bigint':
                    $definition['type'] = Column::TYPE_BIGINTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'decimal':
                case 'money':
                case 'smallmoney':
                    $definition['type'] = Column::TYPE_DECIMAL;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'int':
                case 'tinyint':
                case 'smallint':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'numeric':
                    $definition['type'] = Column::TYPE_DOUBLE;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'float':
                    $definition['type'] = Column::TYPE_FLOAT;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;

                /*
                 * Boolean
                 */
                case 'bit':
                    $definition['type'] = Column::TYPE_BOOLEAN;
                    $definition['bindType'] = Column::BIND_PARAM_BOOL;
                    break;

                /*
                 * Date are dates
                 */
                case 'date':
                    $definition['type'] = Column::TYPE_DATE;
                    break;

                /*
                 * Special type for datetime
                 */
                case 'datetime':
                case 'datetime2':
                case 'smalldatetime':
                    $definition['type'] = Column::TYPE_DATETIME;
                    break;

                /*
                 * Timestamp are dates
                 */
                case 'timestamp':
                    $definition['type'] = Column::TYPE_TIMESTAMP;
                    break;

                /*
                 * Chars are chars
                 */
                case 'char':
                case 'nchar':
                    $definition['type'] = Column::TYPE_CHAR;
                    break;

                case 'varchar':
                case 'nvarchar':
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;

                /*
                 * Text are varchars
                 */
                case 'text':
                case 'ntext':
                    $definition['type'] = Column::TYPE_TEXT;
                    break;

                /*
                 * blob type
                 */
                case 'varbinary':
                    $definition['type'] = Column::TYPE_BLOB;
                    break;

                /*
                 * By default is string
                 */
                default:
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;
            }

            /*
             * If the column type has a parentheses we try to get the column size from it
             */
            $definition['size'] = (int) $field['LENGTH'];
            $definition['precision'] = (int) $field['PRECISION'];

            if ($field['SCALE'] || $field['SCALE'] == '0') {
                //                $definition["scale"] = (int) $field['SCALE'];
                $definition['size'] = $definition['precision'];
            }

            /*
             * Positions
             */
            if (!$oldColumn) {
                $definition['first'] = true;
            } else {
                $definition['after'] = $oldColumn;
            }

            /*
             * Check if the field is primary key
             */
            if (isset($primaryKeys[$field['COLUMN_NAME']])) {
                $definition['primary'] = true;
            }

            /*
             * Check if the column allows null values
             */
            if ($field['NULLABLE'] == 0) {
                $definition['notNull'] = true;
            }

            /*
             * Check if the column is auto increment
             */
            if ($autoIncrement) {
                $definition['autoIncrement'] = true;
            }

            /*
             * Check if the column is default values
             */
            if ($field['COLUMN_DEF'] != null) {
                $definition['default'] = $field['COLUMN_DEF'];
            }

            $columnName = $field['COLUMN_NAME'];
            $columns[] = new Column($columnName, $definition);
            $oldColumn = $columnName;
        }

        return $columns;
    }

    /**
     * Escapes a column/table/schema name
     *
     * <code>
     *    $escapedTable = $connection->escapeIdentifier('robots');
     *    $escapedTable = $connection->escapeIdentifier(array('store', 'robots'));
     * </code>
     *
     * @param string identifier
     * @return string
     */
    public function escapeIdentifier($identifier) {
        if (is_array($identifier)) {
            return "[" . $identifier[0] . "].[" . $identifier[1] . "]";
        }
        return "[" . $identifier . "]";
    }

    /**
     * 
     * @param type $sql
     * @param type $bindParams
     * @param type $bindTypes
     * @return type
     */
    public function query2($sql, $bindParams = null, $bindTypes = null) {
        // echo '---- ---- ---- ---- ----<br><br>';
        if (is_string($sql)) {
            //check sql server keyword
            if (!strpos($sql, '[rowcount]')) {
                $sql = str_replace('rowcount', '[rowcount]', $sql);    //sql server keywords
            }
            //case 1. select count(query builder)
            $countString = 'SELECT COUNT(*)';
            if (strpos($sql, $countString)) {
                $sql = str_replace('"', '', $sql);
                return parent::query($sql, $bindParams, $bindTypes);
            }
            //case 2. subquery need alais name (model find)
            $countString = 'SELECT COUNT(*) "numrows" ';
            if (strpos($sql, $countString) !== false) {
                $sql .= ' dt ';
                // $sql = preg_replace('/ORDER\sBY.*\)\ dt/i',') dt',$sql);
                //subquery need TOP
                if (strpos($sql, 'TOP') === false) {
                    if (strpos($sql, 'ORDER') !== false) {
                        $offset = count($countString);
                        $pos = strpos($sql, 'SELECT', $offset) + 7; //'SELECT ';
                        if (stripos($sql, 'SELECT DISTINCT') === false) {
                            $sql = substr($sql, 0, $pos) . 'TOP 100 PERCENT ' . substr($sql, $pos);
                        }
                    }
                }
            }
            // echo $sql."<br><br>";
            //sql server(dblib) does not accept " as escaper
            $sql = str_replace('"', '', $sql);
        }
        // echo $sql.'<br><br>------ --------- ----------';
        return parent::query($sql, $bindParams, $bindTypes);
    }

    /**
     * Sends SQL statements to the database server returning the success state.
     * Use this method only when the SQL statement sent to the server is returning rows
     * <code>
     * //Querying data
     * $resultset = $connection->query("SELECTFROM robots WHERE type='mechanical'");
     * $resultset = $connection->query("SELECTFROM robots WHERE type=?", array("mechanical"));
     * </code>.
     *
     * @param string $sqlStatement
     * @param mixed  $bindParams
     * @param mixed  $bindTypes
     *
     * @return bool|\Phalcon\Db\ResultInterface
     */
    public function query($sqlStatement, $bindParams = null, $bindTypes = null) {
        $eventsManager = $this->_eventsManager;

        /*
         * Execute the beforeQuery event if a EventsManager is available
         */
        if (is_object($eventsManager)) {
            $this->_sqlStatement = $sqlStatement;
            $this->_sqlVariables = $bindParams;
            $this->_sqlBindTypes = $bindTypes;

            if ($eventsManager->fire('db:beforeQuery', $this, $bindParams) === false) {
                return false;
            }
        }

        $pdo = $this->_pdo;

        $cursor = \PDO::CURSOR_SCROLL;
        if (strpos($sqlStatement, 'exec') !== false) {
            $cursor = \PDO::CURSOR_FWDONLY;
        }

        if (is_array($bindParams)) {
            $statement = $pdo->prepare($sqlStatement, array(\PDO::ATTR_CURSOR => $cursor));
            if (is_object($statement)) {
                $statement = $this->executePrepared($statement, $bindParams, $bindTypes);
            }
        } else {
            $statement = $pdo->prepare($sqlStatement, array(\PDO::ATTR_CURSOR => $cursor));
            $statement->execute();
        }

        /*
         * Execute the afterQuery event if a EventsManager is available
         */
        if (is_object($statement)) {
            if (is_object($eventsManager)) {
                $eventsManager->fire('db:afterQuery', $this, $bindParams);
            }

            return new ResultPdo($this, $statement, $sqlStatement, $bindParams, $bindTypes);
        }

        return $statement;
    }

    /**
     * INSERT
     * @param type $table
     * @param array $values
     * @param type $fields
     * @param type $dataTypes
     * @return boolean
     * @throws \Phalcon\Db\Exception
     */
    public function insert($table, array $values, $fields = NULL, $dataTypes = NULL) { // 2.x
        $placeholders;
        $insertValues;
        $bindDataTypes;
        $bindType;
        $position;
        $value;
        $escapedTable;
        $joinedValues;
        $escapedFields;
        $field;
        $insertSql;
        if (!is_array($values)) {
            throw new \Phalcon\Db\Exception("The second parameter for insert isn't an Array");
        }
        /**
         * A valid array with more than one element is required
         */
        if (!count($values)) {
            throw new \Phalcon\Db\Exception("Unable to insert into " . $table . " without data");
        }
        $placeholders = array();
        $insertValues = array();
        if (!is_array($dataTypes)) {
            $bindDataTypes = array();
        } else {
            $bindDataTypes = $dataTypes;
        }
        /**
         * Objects are casted using __toString, null values are converted to string "null", everything else is passed as "?"
         */
        //echo PHP_EOL;	var_dump($dataTypes);
        foreach ($values as $position => $value) {
            if (is_object($value)) {
                $placeholders[] = '?'; // (string) $value;
                $insertValues[] = (string) $value;
            } else {
                if ($value === null) { // (0 ==) null is true
                    $placeholders[] = '?';  // "default";
                    $insertValues[] = null; // "default";
                } else {
                    $placeholders[] = "?";
                    $insertValues[] = $value;
                    if (is_array($dataTypes)) {
                        if (!isset($dataTypes[$position])) {
                            throw new \Phalcon\Db\Exception("Incomplete number of bind types");
                        }
                        $bindType = $dataTypes[$position];
                        $bindDataTypes[] = $bindType;
                    }
                }
            }
        }
        // if (defined('DEBUG')) { var_dump($placeholders); die; }
        if (false) { //globals_get("db.escape_identifiers") {
            $escapedTable = $this->escapeIdentifier($table);
        } else {
            $escapedTable = $table;
        }
        /**
         * Build the final SQL INSERT statement
         */
        $joinedValues = join(", ", $placeholders);
        if (is_array($fields)) {
            if (false) {//globals_get("db.escape_identifiers") {
                $escapedFields = array();
                foreach ($fields as $field) {
                    $escapedFields[] = $this->escapeIdentifier($field);
                }
            } else {
                $escapedFields = $fields;
            }
            $insertSql = "INSERT INTO " . $escapedTable . " (" . join(", ", $escapedFields) . ") VALUES (" . $joinedValues . ")";
        } else {
            $insertSql = "INSERT INTO " . $escapedTable . " VALUES (" . $joinedValues . ")";
        }
        $insertSql = 'SET NOCOUNT ON; ' . $insertSql . '; SELECT CAST(SCOPE_IDENTITY() as int) as newid';
        /**
         * Perform the execution via PDO::execute
         */
        $obj = $this->query($insertSql, $insertValues, $bindDataTypes);
        $ret = $obj->fetchAll();
        if ($ret && isset($ret[0]) && isset($ret[0]['newid'])) {
            $this->_lastID = $ret[0]['newid'];
            if ($this->_lastID > 0) {
                return true;
            } else {
                $this->_lastID = null;
                return false;
            }
        } else {
            $this->_lastID = null;
            return false;
        }
    }

    /**
     * UPDATE
     * @param type $table
     * @param type $fields
     * @param type $values
     * @param type $whereCondition
     * @param type $dataTypes
     * @return type
     * @throws \Phalcon\Db\Exception
     */
    public function update($table, $fields, $values, $whereCondition = null, $dataTypes = null) {
        $placeholders = array();
        $updateValues = array();
        if (is_array($dataTypes)) {
            $bindDataTypes = array();
        } else {
            $bindDataTypes = $dataTypes;
        }
        /**
         * Objects are casted using __toString, null values are converted to string 'null', everything else is passed as '?'
         */
        foreach ($values as $position => $value) {
            if (!isset($fields[$position])) {
                throw new \Phalcon\Db\Exception("The number of values in the update is not the same as fields");
            }
            $field = $fields[$position];
            if (false) {//globals_get("db.escape_identifiers") {
                $escapedField = $this->escapeIdentifier($field);
            } else {
                $escapedField = $field;
            }
            if (is_object($value)) {
                // $placeholders[] = $escapedField . " = " . $value;
                $placeholders[] = $escapedField . ' = ? ';
                $updateValues[] = (string) $value;
            } else {
                if ($value === null) { // (0 ==) null is true
                    $placeholders[] = $escapedField . " = null";
                    // $placeholders[] = $escapedField . ' = ? ';
                    // $updateValues[] = null;
                } else {
                    $updateValues[] = $value;
                    if (is_array($dataTypes)) {
                        if (!isset($dataTypes[$position])) {
                            throw new \Phalcon\Db\Exception("Incomplete number of bind types");
                        }
                        $bindType = $dataTypes[$position];
                        $bindDataTypes[] = $bindType;
                    }
                    $placeholders[] = $escapedField . " = ?";
                }
            }
        }
        if (false) {//globals_get("db.escape_identifiers") {
            $escapedTable = $this->escapeIdentifier($table);
        } else {
            $escapedTable = $table;
        }
        $setClause = join(", ", $placeholders);
        if ($whereCondition !== null) {
            $updateSql = "UPDATE " . $escapedTable . " SET " . $setClause . " WHERE ";
            /**
             * String conditions are simply appended to the SQL
             */
            if (!is_array($whereCondition)) {
                $updateSql .= $whereCondition;
            } else {
                /**
                 * Array conditions may have bound params and bound types
                 */
                if (!is_array($whereCondition)) {
                    throw new \Phalcon\Db\Exception("Invalid WHERE clause conditions");
                }
                /**
                 * If an index 'conditions' is present it contains string where conditions that are appended to the UPDATE sql
                 */
                if (isset($whereCondition["conditions"])) {
                    $conditions = $whereCondition['conditions'];
                    $updateSql .= $conditions;
                }
                /**
                 * Bound parameters are arbitrary values that are passed by separate
                 */
                if (isset($whereCondition["bind"])) {
                    $whereBind = $whereCondition["bind"];
                    $updateValues = array_merge($updateValues, $whereBind);
                }
                /**
                 * Bind types is how the bound parameters must be casted before be sent to the database system
                 */
                if (isset($whereCondition["bindTypes"])) {
                    $whereTypes = $whereCondition['bindTypes'];
                    $bindDataTypes = array_merge($bindDataTypes, $whereTypes);
                }
            }
        } else {
            $updateSql = "UPDATE " . $escapedTable . " SET " . $setClause;
        }
        /**
         * Perform the update via PDO::execute
         */
        //					echo PHP_EOL . $updateSql;
        //					var_dump($updateValues);
        return $this->execute($updateSql, $updateValues, $bindDataTypes);
    }

    /**
     * Last Insert Id
     * @param type $tableName
     * @param type $primaryKey
     * @return type
     */
    public function lastInsertId($tableName = null, $primaryKey = null) {
        // $sql = 'SET NOCOUNT ON; SELECT CAST(SCOPE_IDENTITY() as int) as id';
        // echo __FUNCTION__.': '.$this->instance.'<br>'; die;
        return $this->_lastID;
        // return (int)$this->fetchOne($sql);
    }

    /**
     * 
     * @param type $table
     * @param type $whereCondition
     * @param type $placeholders
     * @param type $dataTypes
     * @return type
     */
    public function delete($table, $whereCondition = null, $placeholders = null, $dataTypes = null) {
        $sql;
        $escapedTable;
        if (false) { // globals_get("db.escape_identifiers") {
            $escapedTable = $this->escapeIdentifier($table);
        } else {
            $escapedTable = $table;
        }
        if (!empty($whereCondition)) {
            $sql = "DELETE FROM " . $escapedTable . " WHERE " . $whereCondition;
        } else {
            $sql = "DELETE FROM " . $escapedTable;
        }
        /**
         * Perform the update via PDO::execute
         */
        return $this->execute($sql, $placeholders, $dataTypes);
    }

    /**
     * Lists table indexes
     *
     * <code>
     *    print_r($connection->describeIndexes('robots_parts'));
     * </code>
     *
     * @param    string table
     * @param    string schema
     * @return    Phalcon\Db\Index[]
     */
    public function describeIndexes($table, $schema = null) {
        $dialect = $this->_dialect;
        $indexes = array();
        $temps = $this->fetchAll($dialect->describeIndexes($table, $schema), \Phalcon\Db::FETCH_ASSOC);
        foreach ($temps as $index) {
            $keyName = $index['index_id'];
            if (!isset($indexes[$keyName])) {
                $indexes[$keyName] = array();
            }
            //let indexes[keyName][] = index[4];
        }
        $indexObjects = array();
        foreach ($indexes as $name => $indexColumns) {
            /**
             * Every index is abstracted using a Phalcon\Db\Index instance
             */
            $indexObjects[$name] = new \Phalcon\Db\Index($name, $indexColumns);
        }
        return $indexObjects;
    }

    /**
     * Lists table references
     *
     * <code>
     * print_r($connection->describeReferences('robots_parts'));
     * </code>
     *
     * @param    string table
     * @param    string schema
     * @return    Phalcon\Db\Reference[]
     */
    public function describeReferences($table, $schema = null) {
        $dialect = $this->_dialect;
        $emptyArr = array();
        $references = array();
        $temps = $this->fetchAll($dialect->describeReferences($table, $schema), \Phalcon\Db::FETCH_NUM);
        foreach ($temps as $reference) {
            $constraintName = $reference[2];
            if (!isset($references[$constraintName])) {
                $references[$constraintName] = array(
                    "referencedSchema" => $reference[3],
                    "referencedTable" => $reference[4],
                    "columns" => $emptyArr,
                    "referencedColumns" => $emptyArr
                );
            }
            //let references[constraintName]["columns"][] = reference[1],
            //	references[constraintName]["referencedColumns"][] = reference[5];
        }
        $referenceObjects = array();
        foreach ($references as $name => $arrayReference) {
            $referenceObjects[$name] = new \Phalcon\Db\Reference($name, array(
                "referencedSchema" => $arrayReference["referencedSchema"],
                "referencedTable" => $arrayReference["referencedTable"],
                "columns" => $arrayReference["columns"],
                "referencedColumns" => $arrayReference["referencedColumns"]
            ));
        }
        return $referenceObjects;
    }

    /**
     * Gets creation options from a table
     *
     * <code>
     * print_r($connection->tableOptions('robots'));
     * </code>
     *
     * @param    string tableName
     * @param    string schemaName
     * @return    array
     */
    public function tableOptions($tableName, $schemaName = null) {
        $dialect = $this->_dialect;
        $sql = $dialect->tableOptions($tableName, $schemaName);
        if ($sql) {
            $describe = $this->fetchAll($sql, \Phalcon\DB::FETCH_NUM);
            return $describe[0];
        }
        return array();
    }

    /**
     * Begin a transaction.
     *
     * It is necessary to override the abstract PDO transaction functions here, as
     * the PDO driver for MSSQL does not support transactions.
     */
    public function begin($nesting = false) {
        //						$this->execute('SET QUOTED_IDENTIFIER OFF');
        //						$this->execute('SET NOCOUNT OFF');
        $this->execute('BEGIN TRANSACTION;');
        return true;
    }

    /**
     * Commit a transaction.
     *
     * It is necessary to override the abstract PDO transaction functions here, as
     * the PDO driver for MSSQL does not support transactions.
     */
    public function commit($nesting = false) {
        $this->execute('COMMIT TRANSACTION');
        return true;
    }

    /**
     * Roll-back a transaction.
     *
     * It is necessary to override the abstract PDO transaction functions here, as
     * the PDO driver for MSSQL does not support transactions.
     */
    public function rollBack($nesting = false) {
        $this->execute('ROLLBACK TRANSACTION');
        return true;
    }

    public function getTransactionLevel() {
        return (int) $this->fetchOne('SELECT @@TRANCOUNT as level');
    }

    /**
     * Creates a PDO DSN for the adapter from $this->_config settings.
     *
     * @return string
     */
    protected function _dsn() {
        // baseline of DSN parts
        $dsn = $this->_config;
        // don't pass the username and password in the DSN
        unset($dsn['username']);
        unset($dsn['password']);
        unset($dsn['options']);
        unset($dsn['persistent']);
        unset($dsn['driver_options']);
        if (isset($dsn['port'])) {
            $seperator = ':';
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $seperator = ',';
            }
            $dsn['host'] .= $seperator . $dsn['port'];
            unset($dsn['port']);
        }
        // this driver supports multiple DSN prefixes
        // @see http://www.php.net/manual/en/ref.pdo-dblib.connection.php
        if (isset($dsn['pdoType'])) {
            switch (strtolower($dsn['pdoType'])) {
                case 'freetds':
                case 'sybase':
                    $this->_pdoType = 'sybase';
                    break;
                case 'mssql':
                    $this->_pdoType = 'mssql';
                    break;
                case 'dblib':
                default:
                    $this->_pdoType = 'dblib';
                    break;
            }
            unset($dsn['pdoType']);
        }
        // use all remaining parts in the DSN
        foreach ($dsn as $key => $val) {
            $dsn[$key] = "$key=$val";
        }
        $dsn = $this->_pdoType . ':' . implode(';', $dsn);
        return $dsn;
    }

    /**
     * Sends SQL statements to the database server returning the success state.
     * Use this method only when the SQL statement sent to the server doesn't return any rows
     * <code>
     * //Inserting data
     * $success = $connection->execute("INSERT INTO robots VALUES (1, 'Astro Boy')");
     * $success = $connection->execute("INSERT INTO robots VALUES (?, ?)", array(1, 'Astro Boy'));
     * </code>.
     *
     * @param string $sqlStatement
     * @param mixed  $bindParams
     * @param mixed  $bindTypes
     *
     * @return bool
     */
//    public function execute($sqlStatement, $bindParams = null, $bindTypes = null)
    //    {
    //        $eventsManager = $this->_eventsManager;
    //
    //        /*
    //         * Execute the beforeQuery event if a EventsManager is available
    //         */
    //        if (is_object($eventsManager)) {
    //            $this->_sqlStatement = $sqlStatement;
    //            $this->_sqlVariables = $bindParams;
    //            $this->_sqlBindTypes = $bindTypes;
    //
    //            if ($eventsManager->fire('db:beforeQuery', $this, $bindParams) === false) {
    //                return false;
    //            }
    //        }
    //
    //        /*
    //         * Initialize affectedRows to 0
    //         */
    //        $affectedRows = 0;
    //
    //        $pdo = $this->_pdo;
    //
    //        $cursor = \PDO::CURSOR_SCROLL;
    //        if (strpos($sqlStatement, 'exec') !== false) {
    //            $cursor = \PDO::CURSOR_FWDONLY;
    //        }
    //
    //        if (is_array($bindParams)) {
    //            $statement = $pdo->prepare($sqlStatement, array(\PDO::ATTR_CURSOR => $cursor));
    //            if (is_object($statement)) {
    //                $newStatement = $this->executePrepared($statement, $bindParams, $bindTypes);
    //                $affectedRows = $newStatement->rowCount();
    //            }
    //        } else {
    ////            $statement = $pdo->prepare($sqlStatement, array(\PDO::ATTR_CURSOR => $cursor));
    ////            $statement->execute();
    //            $affectedRows = $pdo->exec($sqlStatement);
    //        }
    //
    //        /*
    //         * Execute the afterQuery event if an EventsManager is available
    //         */
    //        if (is_int($affectedRows)) {
    //            $this->_affectedRows = affectedRows;
    //            if (is_object($eventsManager)) {
    //                $eventsManager->fire('db:afterQuery', $this, $bindParams);
    //            }
    //        }
    //
    //        return true;
    //    }
}
