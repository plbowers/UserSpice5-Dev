<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
class US_DB {
	private static $_instance = null;
	private $_pdo, $_sql, $_bindvals, $_query, $_error = false, $_errorInfo = [], $_results, $_resultsArray, $_count = 0, $_lastId, $_queryCount=0;

	private function __construct($host=null, $dbName=null, $user=null, $passwd=null, $opts=[]) {
        $host   = ($host   ? : configGet('mysql/host'));
        $dbName = ($dbName ? : configGet('mysql/db'));
        $user   = ($user   ? : configGet('mysql/username'));
        $passwd = ($passwd ? : configGet('mysql/password'));
        $opts   = ($opts   ? : configGet('mysql/options'));
        $this->_pdo = $this->getConnection($host, $dbName, $user, $passwd, $opts);
	}

    public static function getConnection($host, $dbName, $user, $passwd, $opts=[]) {
		if (!$opts) {
			$opts = array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = ''");
        }
		try {
			return new PDO('mysql:host='.$host.';dbname='.$dbName, $user, $passwd, $opts);
		} catch(PDOException $e) {
			die($e->getMessage());
		}
    }

	public static function getInstance() {
		if (!isset(self::$_instance)) {
			self::$_instance = new DB();
		}
		return self::$_instance;
	}

	public function query($sql, $bindvals=array()) {
        #pre_r($sql."\n{".implode(",", (array)$bindvals).'}');
		$this->_queryCount++;
		$this->_error = false;
        $this->_sql = $sql;
        $this->_bindvals = $bindvals;
        #dbg("query(): sql=$sql");
		if ($this->_query = $this->_pdo->prepare($sql)) {
			$x = 1;
            #var_dump($bindvals);
			if (count($bindvals)) {
				foreach ((array)$bindvals as $bindval) {
					$this->_query->bindValue($x++, $bindval);
				}
			}

			if ($this->_query->execute()) {
				if ($this->_query->columnCount() > 0) {
					$this->_results = $this->_query->fetchALL(PDO::FETCH_OBJ);
                    # Since we rarely use this associative array, calculate it only in results() and first()
					#$this->_resultsArray = json_decode(json_encode($this->_results), true);
				}
				$this->_count = $this->_query->rowCount();
				$this->_lastId = $this->_pdo->lastInsertId();
			} else {
				$this->_error = true;
                $this->_errorInfo = $this->_query->errorInfo();
			}
		}
		return $this;
	}

    public function queryAll($table, $where=[], $bindvals=[], $orderBy=null) {
		return $this->action('SELECT *', $table, $where, $bindvals, $orderBy);
    }
	public function findAll($table, $where=[], $bindvals=[], $orderBy=null) {
		if (!$this->queryAll($table, $where, $bindvals, $orderBy)->error()) {
            return $this;
        }
        return false;
        #var_dump($this);
	}

    public function queryById($table, $id) {
		return $this->queryAll($table, ['id', '=', $id]);
    }
	public function findById($table, $id) {
		if (!$this->queryById($table, $id)->error()) {
            return $this;
        }
		return false;
	}

    // synonym/alias for findAll()
	public function get($table, $where, $bindvals=[], $orderBy=null) {
        return $this->findAll($table, $where, $bindvals, $orderBy);
	}

	public function delete($table, $where, $bindvals=[]) {
        #dbg("DB::delete(): pre error=".($this->error()?'TRUE':"false"));
		if (!$this->action('DELETE', $table, $where, $bindvals)->error()) {
        #dbg("DB::delete(): RETURNING TRUE");
			return $this;
        }
        #var_dump($this->_errorInfo);
        #dbg("DB::delete(): RETURNING FALSE");
		return false;
	}

	public function deleteById($table, $id) {
		return $this->delete($table, 'id = ?', [$id]);
	}

	public function action($action, $table, $where=[], $bindvals=[], $orderBy=null) {
        global $T;
        if (@$T[$table]) {
            $table = $T[$table];
        }
		$sql = "{$action} FROM ".$table;
		$value = '';
        if ($whereClause = $this->calcWhereClause($where, $bindvals)) {
            $sql .= $whereClause;
            #dbg("ACTION: ".$sql." ==> ".print_r($bindvals,true));
        }
        if ($orderBy) {
            $sql .= " ORDER BY " . implode(",", (array)$orderBy);
        }
        #dbg($sql);
        #dbg('['.implode($bindvals, ',').']');
        return $this->query($sql, $bindvals);
	}
    public function calcWhereClause($where, &$bindvals) {
        $wherecond = ''; // default if nothing is specified
        if (!$where) {
            return '';
        }
        if (!is_array($where)) { // $where is a simple string
            if ($where) {
                $wherecond = " WHERE " . $where;
            }
            // $bindvals already set
        } else { // it's an array
            reset($where);
            $key = key($where);
            $ops = ['=', '>', '<', '>=', '<=', '!=', '<>'];
            if (count($where) == 3 && is_numeric($key) && in_array($where[1], $ops)) { // e.g. ['field', '=', 'value']
    			$field = $where[0];
    			$operator = $where[1];
    			$value = $where[2];

				$wherecond = " WHERE {$field} {$operator} ?";
                $bindvals = [$value];
            } else { // either ['field' => 'value'] -or- ['field' => ['!=', 'value']]
                $bindvals = [];
                $wherecond = ' WHERE ';
                $boolop = '';
                foreach ($where as $k => $v) {
                    if (is_numeric($k)) {
                        $boolop = $v;
                        continue;
                    }
                    if (is_array($v)) {
                        if (count($v) > 1) {
                            $op = $v[0];
                            $val = $v[1];
                        } elseif (count($v) == 1) {
                            $op = '=';
                            $val = $v[0];
                        }
                    } else { // $v is a simple value; assume op is =
                        $op = '=';
                        $val = $v;
                    }
                    if (is_null($val)) {
                        if ($op == '=' || strtolower($op) == 'is') {
                            $cond = $k.' IS NULL';
                        } else {
                            $cond = $k.' IS NOT NULL';
                        }
                        // no $bindvals
                    } else {
                        if (!in_array($op, $ops)) {
                            dbg("ERROR: '$op' is not a valid operator [where=".print_r($wherecond,true)."]");
                        }
                        $bindvals[] = $val;
                        $cond = $k.' '.$op.' ?';
                    }
                    $wherecond .= $boolop . ' (' . $cond . ') ';
                    $boolop = 'AND';
                }
    		}
        }
        return $wherecond;
    }

	public function insert($table, $fields = array()) {
        global $T;
        if (@$T[$table]) {
            $table = $T[$table];
        }
        $values = '?'.str_repeat(',?', sizeof($fields)-1);
		$sql = "INSERT INTO ".$table.
            " (`". implode('`,`', array_keys($fields))."`) ".
            "VALUES ({$values})";
		if (!$this->query($sql, $fields)->error()) {
			return true;
		}
		return false;
	}

    // alias for DB::update()
	public function updateById($table, $id, $fields) {
        return $this->update($table, $id, $fields);
    }
	public function update($table, $id, $fields) {
        return $this->updateAll($table, $fields, ['id'=>$id]);
	}
    public function updateAll($table, $fields, $where, $bindvals=[]) {
        global $T;
        if (@$T[$table]) {
            $table = $T[$table];
        }
		$set = $comma = '';
		foreach ($fields as $name => $value) {
			$set .= "{$comma}{$name} = ?";
            $comma = ', ';
		}

        $whereClause = $this->calcWhereClause($where, $bindvals);
        $bindvals = array_merge($fields, $bindvals);
		$sql = "UPDATE ".$table." SET {$set} {$whereClause}";
        #dbg("UPDATE: ".$sql." ==> ".print_r($bindvals,true));
        #$fields[] = $id; // add final bind value
		return !$this->query($sql, $bindvals)->error();
    }

	public function results($assoc = false) {
		if ($assoc) {
            return json_decode(json_encode($this->_results), true);
        }
		return $this->_results;
	}

	public function first($assoc = false) {
		if ($this->count() > 0) {
            if ($assoc) {
    			return (array)$this->results()[0];
            } else {
    			return $this->results()[0];
            }
		} else {
			return false;
        }
	}

	public function found() {
		return $this->_count; // 0=false, non-zero=true
	}

	public function count() {
		return $this->_count;
	}

	public function errorString($sql=false) {
        $rtn = '';
        if ($this->_errorInfo) {
            if ($sql) {
                $rtn .= $this->_sql;
            }
    		$rtn .= $this->_errorInfo[0].': '.$this->_errorInfo[2].' ('.$this->_errorInfo[1].')';
        }
        return $rtn;
	}
    # alias
    public function errorDisplay($sql=false) {
        return $this->errorString($sql);
    }

	public function error() {
		return $this->_error;
	}
    # alias
    public function errors() {
        return $this->error();
    }
    public function checkErrors(&$errors, $fatal=false) {
        $rtn = $this->errorSetMessage($errors);
        if ($fatal && $rtn) {
            resultBlock($errors, $successes); // display any other errors
            throw new Exception("FATAL SQL ERROR: ".$this->errorString());
        }
        return $rtn;
    }
    public function errorSetMessage(&$errors) {
        if ($this->error()) {
            $errors[] = lang('SQL_ERROR');
            $errors[] = $this->errorString();
        }
        return $this->error();
    }

	public function lastId() {
		return $this->_lastId;
	}

	public function getQueryCount() {
		return $this->_queryCount;
	}

	public function getAttribute($attributeValue=null) {
		return $this->_pdo->getAttribute(constant($attributeValue));
	}
}
