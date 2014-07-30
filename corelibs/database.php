<?php
class Chain
{
    private $db;
    private $command;
    private $data;
    private $allowed;
    private $queries;

    public function __construct(PDO $db, $query, array $allowed, array $data = array())
    {
        $this->db = $db;
        $this->command = $query;
        $this->data = $data;
        $this->allowed = $allowed;
        $this->queries = array();
        foreach ($allowed as $fn) {
            $this->queries[$fn] = array("query" => "", "data" => array());
        }
    }

    private function _where($column, $op, $value, $type = 'AND')
    {
        MySQL::validIdentifier($column);
        if (!preg_match('/^([<>=&~|^%]|<=>|>=|<>|IS(( NOT)?( NULL)?)?|LIKE|!=)$/i', $op))
            throw new Exception("Unknown or bad operator '$op'");
        $type = strtoupper($type);
        if ($type != 'AND' && $type != 'OR')
            $type = 'AND';
        $type = ' ' . $type;
        if (!$this->queries['where']['query']) {
            // The first time this is called, use WHERE instead of AND or OR (starting value)
            $type = 'WHERE';
        }
        return array("query" => "{$type} $column $op ?",
                     "mode" => "a",
                     "data" => array($value));
    }

    private function _orWhere($key, $op, $value)
    {
        return $this->_where($key, $op, $value, 'OR');
    }

    private function _limit($from, $to = null)
    {
        $query = 'LIMIT ?';
        $data = array($from);
        if ($to !== null) {
            $query .= ", ?";
            $data[] = $to;
        }
        return array("query" => $query, "data" => $data);
    }

    // TODO: Order By multiple columns
    private function _orderBy($column, $order)
    {
        MySQL::validIdentifier($column);
        if (!preg_match('/^\s*(ASC|DESC)+\s*$/i', $order))
            throw new Exception("Unknown order by sort: $order");
        return array("query" => "ORDER BY $column $order");
    }

    public function __call($name, array $arguments)
    {
        $afunc = $func = preg_replace('/(\w)_(\w)/e', '"$1" . strtoupper("$2")', $name);
        if ($afunc == 'orWhere') {
            // _orWhere extends the base _where
            $afunc = 'where';
        }
        if (!in_array($afunc, $this->allowed))
            throw new Exception($afunc . " not allowed for query type.");
        if (($res = call_user_func_array(array($this, "_$func"), $arguments)) === false) {
            throw new BadMethodCallException("Unknown function or wrong parameters ($name)");
        }
        if (isset($res["query"])) {
            $res["mode"] = isset($res["mode"]) ? $res["mode"] : "w";
            if (!isset($res["data"]))
                $res["data"] = array();
            if ($res["mode"] == "w") {
                $this->queries[$afunc]["query"] = $res["query"];
                $this->queries[$afunc]["data"] = $res["data"];
            } elseif ($res["mode"] == "a") {
                $this->queries[$afunc]["query"] .= $res["query"];
                $this->queries[$afunc]["data"] = array_merge($this->queries[$afunc]["data"], $res["data"]);
            }
        }
        return $this;
    }

    private function buildQuery()
    {
        $query = $this->command;
        $data = $this->data;
        foreach ($this->allowed as $fn) {
            if ($this->queries[$fn]['query'] != "") {
                $query .= ' ' . $this->queries[$fn]['query'];
                $data = array_merge($data, $this->queries[$fn]['data']);
            }
        }
        return array($query, $data);
    }

    public function _($row_count = false)
    {
        list($query, $data) = $this->buildQuery();
        $statement = $this->db->prepare($query);
        foreach ($data as $i => $value) {
            switch(gettype($value)) {
                case 'integer':
                case 'double':
                    $type = PDO::PARAM_INT;
                    break;
                case 'boolean':
                    $type = PDO::PARAM_BOOL;
                    break;
                case 'NULL':
                    $type = PDO::PARAM_NULL;
                case 'string':
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }
            $statement->bindValue($i + 1, $value, $type);
        }
        if ($statement->execute()) {
            if ($row_count) {
                return $statement->rowCount();
            }
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $info = $statement->errorInfo();
        throw new Exception((string) $info[2], (int) $statement->errorCode());
        return array();
    }
}
class MySQL
{
    private $db;

    public static function getInstance()
    {
        static $instance;
        if ($instance === null) {
            $instance = new self(DB_HOST, DB_NAME, DB_USER, DB_PASS);
        }
        return $instance;
    }

    private function __construct($host, $database, $username, $password)
    {
        $this->db = new PDO("mysql:dbname={$database};host={$host};charset=utf8", $username, $password);
    }

    public static function validIdentifier($identifier)
    {
        if (!is_string($identifier))
            throw new Exception("Identifier must be a string. Got " . gettype($identifier) . "($identifier)");
        if (!preg_match('/^\s*[\w$]+\s*$/', $identifier))
            throw new Exception("Invalid identifier format '$identifier'");
        return $identifier;
    }

    public function select($rows, $table)
    {
        if (!preg_match('/^\s*([\w$]+(?:,\s*[\w$]+)*|\*)\s*$/', $rows))
            throw new Exception("Invalid row format '$rows'");
        self::validIdentifier($table);
        return new Chain($this->db, "SELECT $rows FROM `$table`", array('where', 'orderBy', 'limit'));
    }

    public function update($table, $data)
    {
        self::validIdentifier($table);
        if (count($data) == 0) {
            throw new Exception('Must provide data to update');
        }
        $set = implode(' = ?, ', array_map('MySQL::validIdentifier', array_keys($data))) . ' = ?';
        $values = array_values($data);
        return new Chain($this->db, "UPDATE `$table` SET $set", array('where', 'orderBy', 'limit'), $values);
    }

    public function insert($table, $data)
    {
        return $this->insertBatch($table, array($data));
    }

    public function insert_batch($table, $data)
    {
        return $this->insertBatch($table, $data);
    }

    public function insertBatch($table, $data)
    {
        self::validIdentifier($table);
        $row_count = count($data);
        if ($row_count == 0) {
            throw new Exception('Must insert at least 1 row');
        }
        $columns = array();
        $i = 0;
        foreach ($data as $row) {
            foreach($row as $column => $value) {
                self::validIdentifier($column);
                if (!isset($columns[$column])) {
                    if ($i > 0) {
                        $columns[$column] = array_fill(0, $i, null);
                    } else {
                        $columns[$column] = array();
                    }
                }
                $columns[$column][] = $value;
            }
            $i++;
        }
        $akeys = array_keys($columns);
        $keys = '(' . implode(', ', $akeys) . ')';
        $values = implode(', ', array_fill(0, count($data), '(' . implode(', ', array_fill(0, count($akeys), '?')) . ')'));
        $d_values = array();
        for ($i = 0; $i < count($data); $i++) {
            foreach ($columns as $cdata) {
                $d_values[] = isset($cdata[$i]) ? $cdata[$i] : null;
            }
        }
        return new Chain($this->db, "INSERT INTO `$table` $keys VALUES $values", array(), $d_values);
    }

    public function delete($table)
    {
        self::validIdentifier($table);
        return new Chain($this->db, "DELETE FROM `$table`", array('where', 'orderBy', 'limit'));
    }

    public function exec($sql)
    {
        trigger_error("Calling MySQL->exec is bad! It could open you database to SQL injections. Please use a safe function.", E_USER_WARNING);
        // It's also unable to detect invalid SQL before sent to the database.
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        return $this->db->exec($sql);
    }

    public function createTable($table, array $fields, array $indexes = array(), array $options = array())
    {
        function validFieldType($type)
        {
            if (!preg_match('/^[A-Za-z]{3,}(\(\d+\))?$/', $type))
                throw new Exception("Invalid field type '$type'");
        }

        self::validIdentifier($table);
        $fieldNames = array();
        $fmtFields = array();
        foreach ($fields as $field) {
            if (!is_array($field) || count($field) < 2) {
                throw new Exception("Invalid field '" . print_r($field, true) . "'");
            }
            $fieldNames[] = $field[0];
            $field[0] = '`' . self::validIdentifier($field[0]) . '`';
            validFieldType($field[1]);
            $fmtFields[] = implode(' ', $field);
        }
        $keys = array();
        foreach ($indexes as $key => $columns) {
            $arr = array();
            $key = strtoupper($key);
            if (!in_array($key, array('PRIMARY', 'INDEX', 'UNIQUE', 'FULLTEXT')))
                throw new Exeption('Unknown index type ' . $key);
            if ($key === 'INDEX')
                $key = '';
            foreach ($columns as $column) {
                $size = false;
                if (preg_match('/(.*)\((\d+)\)/', $column, $m)) {
                    $column = $m[1];
                    $size = (int) $m[2];
                }
                self::validIdentifier($column);
                if (!in_array($column, $fieldNames)) {
                    throw new Exception("Cannot create index for '$column' as it does not exist.");
                }
                $str = "`$column`";
                if ($size !== false)
                    $str .= "($size)";
                if (!in_array($str, $arr)) {
                    $arr[] = $str;
                }
            }
            $keys[$key] = $arr;
        }
        foreach ($keys as $type => $values) {
            if (count($values) === 0)
                continue;
            $fmtFields[] = $type . ' KEY (' . implode(', ', $values) . ')';
        }
        $opts = "";
        foreach ($options as $key => $value) {
            $opts .= " $key=$value";
        }
        return $this->db->exec("CREATE TABLE `$table` (\n" . implode(", \n", $fmtFields) . "\n)$opts") !== false;
    }
}
