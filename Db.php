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
	/**
	* Get a PDO connection to a database
	*
	* @param array|string $connectionDetails an array with connectionString, username and password or a string of a previously saved connection
	* @param string $saveName Save as a specific name, if "default" is used it will also save it as the default connection no matter what $saveAsDefault is set to.
	* @param bool $saveAsDefault save as the default connection when no param is set to this function
	* @return PDO
	*/
	static public function get($connectionDetailsParam = array(), $saveName= null, $saveAsDefault = false) {
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
	static public function set(array $connectionDetails, $saveName, $saveAsDefault = null) {
		if(!isset($connectionDetails["connectionString"])) {
			if(isset($connectionDetails["conStr"])) $connectionDetails["connectionString"] = $connectionDetails["conStr"];
			else throw new Exception\Db("A connection String is required.");
		}
		if(!isset($connectionDetails["username"])) {
			if(isset($connectionDetails["usr"])) $connectionDetails["username"] = $connectionDetails["usr"];
			elseif(isset($connectionDetails["user"])) $connectionDetails["username"] = $connectionDetails["user"];
			else throw new Exception\Db("You must provide your username.");
		}
		if(!isset($connectionDetails["password"])) {
			if(isset($connectionDetails["pwd"])) $connectionDetails["password"] = $connectionDetails["pwd"];
			elseif(isset($connectionDetails["pass"])) $connectionDetails["password"] = $connectionDetails["pass"];
			else throw new Exception\Db("You must provide your username.");
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
		if(!is_string($glue)) { $glue=''; }
		if($db == null) $db = static::get();
		return implode($glue, array_map(array($db, 'quote'), $array));
	}
}
