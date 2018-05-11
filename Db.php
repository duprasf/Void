<?php
/**
 * This is a basic DB class with little useful functions
 *
 * Created by Francois Dupras
 * January 2012
 */
namespace Void;

class Db
{
	const DATE_FORMAT = "Y-m-d";
	const DATE_TIME_FORMAT = "Y-m-d H:i:s";

	static private $_savedConnections = array();
	static private $_defaultConnection = array();
	static private $requiredConnectionStringElement = array("connectionString"=>1, "username"=>1, "password"=>1);

    static private $allQueries = array();
    static private $lastQuery = "";

    static public function buildPreparedToFullQuery($query, array $executeData, \PDO $pdo)
    {
        $executeData = array_map(function($v) use($pdo) { return $pdo->quote($v); }, $executeData);
        if(preg_match('(\b:\w+\b)', $query)) {
            return strtr($query, $executeData);
        }
        else {
            return preg_replace(array_fill(0, count($executeData), '(\?)'), $executeData, $query, 1);
        }
    }

	/**
	* Get a PDO connection to a database
	*
	* @param array|string $connectionDetails an array with connectionString, username and password or a string of a previously saved connection
	* @param string $saveName Save as a specific name, if "default" is used it will also save it as the default connection no matter what $saveAsDefault is set to.
	* @param bool $saveAsDefault save as the default connection when no param is set to this function
	* @return PDO
	*/
	static public function get($connectionDetailsParam = array(), $saveName= null, $saveAsDefault = false)
    {
		if(is_string($connectionDetailsParam)) {
			if(isset(self::$_savedConnections[$connectionDetailsParam])) $connectionDetails = self::$_savedConnections[$connectionDetailsParam];
			else throw new Exception\Db("The specified connection was not found.");
		}
		if(count($connectionDetailsParam) == 0) {
			if(count(self::$_defaultConnection) >= 3) $connectionDetails = self::$_defaultConnection;
			else throw new Exception\Db("No default saved connection found");
		}

		if(count($connectionDetailsParam) > 0) {
			$connectionDetails = static::set($connectionDetailsParam, $saveName, $saveAsDefault);
		}

		if(count(array_intersect_key($connectionDetails, self::$requiredConnectionStringElement)) != 3) {
			throw new Exception\Db("Connection string does not contains required elements");
		}

		$pdo = new \PDO($connectionDetails["connectionString"], $connectionDetails["username"], $connectionDetails["password"]);
		$pdo->exec('SET CHARACTER SET utf8');

		return $pdo;
	}

	/**
	* Set a PDO connection to a database
	*
	* @param array $connectionDetails an array with connectionString, username and password or a string of a previously saved connection
	* @param string $saveName Save as a specific name, if "default" is used it will also save it as the default connection (except if $saveAsDefault is set to false).
	* @param bool $saveAsDefault save as the default connection when no param is set to this function
	* @return returns the connection string just saved
	*/
	static public function set(array $connectionDetails, $saveName, $saveAsDefault = null)
    {
		if(!isset($connectionDetails["connectionString"])) {
			if(isset($connectionDetails["conStr"])) $connectionDetails["connectionString"] = $connectionDetails["conStr"];
			else throw new Exception\Db("A connection String is required.");
            unset($connectionDetails["conStr"]);
		}
		if(!isset($connectionDetails["username"])) {
			if(isset($connectionDetails["usr"])) $connectionDetails["username"] = $connectionDetails["usr"];
			elseif(isset($connectionDetails["user"])) $connectionDetails["username"] = $connectionDetails["user"];
			else throw new Exception\Db("You must provide your username.");
            unset($connectionDetails["usr"]);
            unset($connectionDetails["user"]);
		}
		if(!isset($connectionDetails["password"])) {
			if(isset($connectionDetails["pwd"])) $connectionDetails["password"] = $connectionDetails["pwd"];
			elseif(isset($connectionDetails["pass"])) $connectionDetails["password"] = $connectionDetails["pass"];
			else throw new Exception\Db("You must provide your username.");
            unset($connectionDetails["pwd"]);
            unset($connectionDetails["pass"]);
		}

		$connectionDetails = array_intersect_key($connectionDetails, self::$requiredConnectionStringElement);
		if($saveName != '') {
			self::$_savedConnections[$saveName] = $connectionDetails;
		}
		if(($saveName == "default" && $saveAsDefault === false) || $saveAsDefault == true) {
			self::$_defaultConnection = $connectionDetails;
		}
		return $connectionDetails;
	}

	/**
	* Combined a implode and a quote for each elements
	*
	* @param string $glue same as glue for implode
	* @param array $array array to be quoted and imploded
	* @param \PDO $db the PDO element from which to quote the elements
	* @return string a full string of the imploded quoted elements
	*/
	static public function implodeQuote($glue, array $array, \PDO $db=null)
	{
        if(!$db instanceof \PDO) $db = \Void\Db::get();
        if(!is_string($glue)) { $glue=''; }
        return implode($glue, array_map(array($db, 'quote'), $array));
	}

    /**
    * Build and execute a INSERT or UPDATE query with the table name, ID key value and
    *
    * @param PDO $pdo
    * @param array $options array that contains tablename, id, data(array), idName(if different than tablenameId), returnRow(bool), returnId(bool) and/or updateOnly(bool)
    * @return mixed
    */
    public static function save($pdo, $options=null) {
        if($options==null && is_array($pdo)) {
            $options = $pdo;
            $pdo = self::get(isset($options["connectionDetails"])?$options["connectionDetails"]:null);
        }
        if(!$pdo instanceOf \PDO) {
            var_dump($pdo);
            debug_print_backtrace();
            exit('not PDO');
        }
        $now = "";
        $separator = array('before'=>'`', 'after'=>'`');
        switch(strtolower($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME))) {
            case "mysql": $now = "NOW()"; break;
            case "mssql": $now = "GETDATE()"; $separator = array('before'=>'[', 'after'=>']'); break;
            default: $now = date("Y/m/d H:i:s"); break;
        }

        $tablename=isset($options["tableName"])?$options["tableName"]:(isset($options["tablename"])?$options["tablename"]:"");
        $id=isset($options["id"])?$options["id"]:"";
        $arg_array=isset($options["data"]) && is_array($options["data"])?$options["data"]:array();
        $idName=isset($options["idName"])?$options["idName"]:"";
        $returnRow=isset($options["returnRow"])?($options["returnRow"]?true:false):false;
        $returnId=isset($options["returnId"])?($options["returnId"]?true:false):false;
        $updateOnly=isset($options["updateOnly"])?($options["updateOnly"]?true:false):false;

        if(preg_match('!'.$separator['before']."|".$separator['after'].'!i', $tablename))
            throw new Void_Exception_Databroker('Table name is not safe', Void_Exception_Databroker::INFO_NOT_SAFE);
        if($tablename == "")
            throw new Void_Exception_Databroker('No table specified for save action', Void_Exception_Databroker::TABLENAME_NOT_SET);
        if(is_array($id) && $idName != '') {
            throw new Void_Exception_Databroker('idName should not be used when id is an array for a combined primary key request', Void_Exception_Databroker::IDNAME_SHOULD_NOT_BE_SET);
        }
        if($idName == "") $idName = $tablename."Id";

        if(count($arg_array) == 0) return true;


        $values = array();
        $queryParam = array();
        $queryFields = array();
        $queryValues = array();

        foreach($arg_array as $key=>$val) {
            $queryFields[] = $key;
            $queryValues[] = ($val === null)?"NULL":(is_string($val)&&strtolower($val)=='now()'?$now:$pdo->quote($val));
            $queryParam[] = "{$key} = ".($val === null?"NULL":(strtolower($val)=='now()'?$now:$pdo->quote($val)));
//            $values[":{$key}"] = ($val === null)?"NULL":$this->db->quote($val);
        }
        $newRequest = false;

        if(!self::entryExists($pdo, $tablename, $id, $idName)) {
            if($updateOnly == true) {
                throw new Void_Exception_Databroker('Row not found for update only', Void_Exception_Databroker::ROW_NOT_FOUND_FOR_UPDATE_ONLY);
            }
            // this is a new request (insert into) if the id is not specified OR if the row does not exist
            $newRequest = true;

            $query = "INSERT INTO {$tablename} ( ";
            $query.= implode(', ', $queryFields);
            $query.= ") VALUES (";
            $query.= implode(', ', $queryValues);
            $query.= ")";
        }
        else {
            $query = "UPDATE {$tablename} SET ";
            $query.= implode(', ', $queryParam);
            $query.= " WHERE ";
            if(is_array($id)) {
                $idArray = array();
                foreach($id as $key=>$val) {
                    $idArray[] = "{$key} = ".$pdo->quote($val);
                }
                $query.= implode(' AND ', $idArray);
            }
            else {
                $query.="{$idName} = ".$pdo->quote($id);
            }
        }

        self::$allQueries[] = $query;
        self::$lastQuery = $query;
        $stmt = $pdo->query($query);
        if(!$stmt) {
            $error = $pdo->errorInfo();
            throw new Exception\Db($query . '   ' .(isset($error[2])?$error[2]:null));
            return false;
        }
        // Return the modified row if requested
        else {
            if(!$id) {
                $id = $pdo->lastInsertId();
            }

            if($returnRow) {
                $query = "SELECT * FROM {$separator['before']}{$tablename}{$separator['after']} WHERE ";
                if(is_array($id)) {
                    $idArray = array();
                    foreach($id as $key=>$val) {
                        $idArray[] = "{$separator['before']}{$key}{$separator['after']} = ".$pdo->quote($val);
                    }
                    $query.= implode(' AND ', $idArray);
                }
                else {
                    $query.="{$separator['before']}{$idName}{$separator['after']} = ".$pdo->quote($id);
                }
                self::$allQueries[] = $query;
                $recordSet = $pdo->query($query);
                if($recordSet) {
                    $result = $recordSet->fetch(PDO::FETCH_ASSOC);
                    $recordSet->closeCursor();
                    return $result;
                }
                else
                    return false;
            }
            else if($returnId) {
                return $id;
            }
        }
        return true;
    }

    static public function entryExists($pdo, $tablename, $id, $idName)
    {
        if(is_null($id) || $id == "") return false;
        if($pdo instanceOf \PDO) {
            return $pdo->query("SELECT * FROM ".$tablename." WHERE ".$idName." = ".$pdo->quote($id));
        }
    }
}
