<?php

putenv('MYSQL_HOSTNAME=127.0.0.1');
putenv('MYSQL_USERNAME=test');
putenv('MYSQL_PASSWORD=test');
putenv('MYSQL_DATABASE=test');
putenv('CACHE_FILE_PATH='.sys_get_temp_dir().'/cache.json');

$MYSQL_HOSTNAME  = getenv('MYSQL_HOSTNAME');
$MYSQL_USERNAME  = getenv('MYSQL_USERNAME');
$MYSQL_PASSWORD  = getenv('MYSQL_PASSWORD');
$MYSQL_DATABASE  = getenv('MYSQL_DATABASE');

$CACHE_FILE_PATH = getenv('CACHE_FILE_PATH');

$SERVER_PROTOCOL = $_SERVER['SERVER_PROTOCOL'];
$REQUEST_METHOD  = $_SERVER['REQUEST_METHOD'];
$PATH_INFO       = $_SERVER['PATH_INFO'];
$BASE_PATH       = '';

$QUERY_LIMIT_OFFSET   = 0;
$QUERY_LIMIT_SIZE     = 10;
$QUERY_LIMIT_SIZE_MAX = 1000;

// -- Auto configuration -----------------------------------------------

if ($REQUEST_METHOD == 'GET'){
	if (isset($_GET['offset']) == TRUE && ctype_digit($_GET['offset']) == TRUE){
		$QUERY_LIMIT_OFFSET = $_GET['offset'];
	}
	if (isset($_GET['limit']) == TRUE && ctype_digit($_GET['limit']) == TRUE){
		if ($QUERY_LIMIT_SIZE_MAX > $_GET['limit'] - 1){
			$QUERY_LIMIT_SIZE = $_GET['limit'];
		}
	}
}

// -- Read cache -------------------------------------------------------

clearstatcache();

$cacheData = array(
	'objectNames' => array()
	, 'objectColumnNames' => array()
	, 'objectColumnTypes' => array()
	, 'objectPrimaryKeys' => array()
	, 'creationDate' => NULL
);

if (is_file($CACHE_FILE_PATH) == TRUE) {
	if (is_readable($CACHE_FILE_PATH) == TRUE){

		$cacheFileText = file_get_contents($CACHE_FILE_PATH);
		$cacheFileData = json_decode($cacheFileText, TRUE);

		if (is_null($cacheFileData) == TRUE){
			$response = new \stdClass;
			$response->code = json_last_error();
			$response->message = json_last_error_msg();
			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		$cacheData = array_replace_recursive($cacheData, $cacheFileData);
	} else {

		$response = new \stdClass;
		$response->code = -32000;
		$response->message = 'Cache not readable';
		header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}
}

// -- GET _db/cache ----------------------------------------------------

if ($REQUEST_METHOD == 'GET' && $PATH_INFO == '/_db/cache'){

	$response = new \stdClass;
	$response->result = $cacheData;
	header($SERVER_PROTOCOL.' 200 OK', TRUE, 200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit();

// -- DELETE _db/cache ----------------------------------------------------

} elseif ($REQUEST_METHOD == 'DELETE' && $PATH_INFO == '/_db/cache'){

	if (is_file($CACHE_FILE_PATH)){
		unlink($CACHE_FILE_PATH);
	}

	$response = new \stdClass;
	$response->result = TRUE;
	header($SERVER_PROTOCOL.' 200 OK', TRUE, 200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit();
}

class Api_Request {

	public $_attributes;

	public function __construct() {

		$requestText = file_get_contents('php://input');

		if ($requestText === FALSE){

			$response = new \Api_Response_Error;
			$response->code = -32700;
			$response->message = 'Parse error';
			$response->write();
		}

		$requestData = json_decode($requestText);
		if (	is_null($requestData) == TRUE
			&&	json_last_error() != JSON_ERROR_NONE
			){

			$response = new \Api_Response_Error;
			$response->code = -32700;
			$response->message = 'Parse error';
			$response->data = json_last_error_msg();
			$response->write();
		}

		if (is_object($requestData) == FALSE){

			$response = new \Api_Response_Error;
			$response->code = -32600;
			$response->message = 'Invalid Request';
			$response->write();
		}

		$this->_attributes = array();

		foreach ($requestData as $attributeName => $attributeValue){
			$this->_attributes[$attributeName] = $attributeValue;
		}
	}

	public function __get($attributeName) {
		if (array_key_exists($attributeName, $this->_attributes)){
			return $this->_attributes[$attributeName];
		}
		return NULL;
	}

	public function count() {
		return count($this->_attributes);
	}

	public function getNames() {
		return array_keys($this->_attributes);
	}

	public function getValue($attributeName) {
		if (array_key_exists($attributeName, $this->_attributes)){
			return $this->_attributes[$attributeName];
		}
		return NULL;
	}
}

class Api_Response_Success {

	public $id;
	public $result;

	public function write() {

		if (is_null($this->result) == TRUE){
			$result = new \stdClass;
		} else {
			$result = $this->result;
		}

		$response = new \stdClass;

		$response->jsonrpc = '2.0';
		$response->result = $result;
		$response->id = $this->id;

		header($SERVER_PROTOCOL.' 200 OK', TRUE, 200);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}
}

class Api_Response_Error {

	public $id;
	public $code;
	public $message;
	public $data;

	public function write() {

		$response = new \stdClass;
		$error = new \stdClass;

		$error->code = $this->code;
		$error->message = $this->message;
		if (is_null($this->data) == FALSE){
			$error->data = $this->data;
		}

		$response->jsonrpc = '2.0';
		$response->error = $error;
		$response->id = $this->id;

		header($SERVER_PROTOCOL.' 200 OK', TRUE, 200);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}
}

// -- Connect to database ----------------------------------------------

$link = mysqli_connect($MYSQL_HOSTNAME, $MYSQL_USERNAME, $MYSQL_PASSWORD, $MYSQL_DATABASE);

if (!$link) {
	$response = new \stdClass;
	$response->code = mysqli_connect_errno();
	$response->message = mysqli_connect_error();
	header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit();
}

// -- Build cache ------------------------------------------------------


if (	is_null($cacheData['creationDate']) == TRUE
	||	(
						$REQUEST_METHOD == 'POST'
				&&	$PATH_INFO == '/_db/cache'
			)
	){

// -- POST _db/cache ----------------------------------------------------

	$cacheData['creationDate'] = date('c');

	if (is_writable(dirname($CACHE_FILE_PATH)) == FALSE){

		mysqli_close($link);

		$response = new \stdClass;
		$response->code = -32000;
		$response->message = 'Write cache is not possible';
		header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}

	$sql = "SHOW TABLES";
	if ($result = mysqli_query($link, $sql)){

		while ($row = mysqli_fetch_array($result,  MYSQLI_NUM)){
			$cacheData['objectNames'][] = $row[0];
		}

		mysqli_free_result($result);
	} else {

		mysqli_close($link);

		$response = new \stdClass;
		$response->code = mysqli_connect_errno();
		$response->message = mysqli_connect_error();
		header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit();
	}

	foreach ($cacheData['objectNames'] as $objectName){

		$sql = "SHOW COLUMNS FROM `$objectName`";
		if ($result = mysqli_query($link, $sql)){

			$cacheData['objectColumnNames'][$objectName] = array();
			$cacheData['objectColumnTypes'][$objectName] = array();
			while ($row = mysqli_fetch_array($result,  MYSQLI_ASSOC)){

				$cacheData['objectColumnNames'][$objectName][] = $row['Field'];

				// Map Column Types
				//   - integer  : no escape string nor single quote
				//   - datetime : no escape string and single quote
				//   - character : escape string and single quote

				if (	substr($row['Type'], 0, 3) == 'int'
					||	substr($row['Type'], 0, 7) == 'tinyint'
					||	substr($row['Type'], 0, 8) == 'smallint'
					||	substr($row['Type'], 0, 9) == 'mediumint'
					||	substr($row['Type'], 0, 6) == 'bigint'
					||	$row['Type'] == 'float'
					||	substr($row['Type'], 0, 6) == 'double'
					||	substr($row['Type'], 0, 7) == 'decimal'
					) {
					$cacheData['objectColumnTypes'][$objectName][$row['Field']] = 'integer';
				} elseif (
							substr($row['Type'], 0, 4) == 'char'
					||	substr($row['Type'], 0, 7) == 'varchar'
					||	$row['Type'] == 'tinytext'
					||	$row['Type'] == 'text'
					||	$row['Type'] == 'mediumtext'
					||	$row['Type'] == 'longtext'
					){
					$cacheData['objectColumnTypes'][$objectName][$row['Field']] = 'character';
				} elseif (
							$row['Type'] == 'date'
					||	$row['Type'] == 'datetime'
					||	$row['Type'] == 'timestamp'
					||	$row['Type'] == 'time'
					){
					$cacheData['objectColumnTypes'][$objectName][$row['Field']] = 'datetime';
				} elseif (
							substr($row['Type'], 0, 6) == "enum('"
					||	substr($row['Type'], 0, 5) == "set('"
					){
					$cacheData['objectColumnTypes'][$objectName][$row['Field']] = 'character';
				} elseif (
							substr($row['Type'], 0, 5) == "enum("
					||	substr($row['Type'], 0, 4) == "set("
					){
					$cacheData['objectColumnTypes'][$objectName][$row['Field']] = 'integer';
				} else {

					mysqli_close($link);

					$response = new \stdClass;
					$response->code = 400;
					$response->message = 'Unsupported Type '.$row['Type'];
					header($SERVER_PROTOCOL.' 400 Bad Request', TRUE, 400);
					header('Content-Type: application/json');
					echo json_encode($response);
					exit();
				}

				if ($row['Key'] == 'PRI'){
					$cacheData['objectPrimaryKeys'][$objectName] = $row['Field'];
				}
			}

			mysqli_free_result($result);
		} else {

			mysqli_close($link);

			$response = new \stdClass;
			$response->code = mysqli_connect_errno();
			$response->message = mysqli_connect_error();
			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}
	}

	mysqli_close($link);

	$cacheText = json_encode($cacheData);
	$tempFile = tempnam(sys_get_temp_dir(), '');
	$tempHandle = fopen($tempFile, "w");
	fwrite($tempHandle, $cacheText);
	fclose($tempHandle);
	rename($tempFile, $CACHE_FILE_PATH);

	header($SERVER_PROTOCOL.' 201 Created', TRUE, 201);
	header('ETag: '.md5($cacheText));
	header('Location: '.$BASE_PATH.'/_db/cache');
	exit();
}


// -- Handle request ---------------------------------------------------

if ($REQUEST_METHOD == 'GET'){

	// Handle GET /table-name

	if (preg_match("/^\/([^\/]+)$/", $PATH_INFO, $match) == TRUE){

		$objectName = $match[1];

		if (in_array($objectName, $cacheData['objectNames']) == FALSE){

			$response = new \stdClass;
			$response->code = '404';
			$response->message = 'Object not found';

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 404 Not Found', TRUE, 404);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		$sql = "SELECT `".implode("`, `", $cacheData['objectColumnNames'][$objectName])."`"
			." FROM `$objectName`"
			." LIMIT $QUERY_LIMIT_OFFSET, $QUERY_LIMIT_SIZE"
			;

		if ($result = mysqli_query($link, $sql)){

			$rows = array();
			while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
				$rows[] = $row;
			}

			mysqli_free_result($result);
			mysqli_close($link);

			$response = new \stdClass;
			$response->result = $rows;
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();

		} else {

			$response = new \stdClass;
			$response->code = mysqli_errno($link);
			$response->message = mysqli_error($link);

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();

		}
	}

	// Handle GET /table-name/{id}

	if (preg_match("/^\/([^\/]+)\/([^\/]+)$/", $PATH_INFO, $match) == TRUE){

		$objectName = $match[1];
		$objectKeyValue = $match[2];

		if (in_array($objectName, $cacheData['objectNames']) == FALSE){

			mysqli_close($link);

			$response = new \stdClass;
			$response->code = '404';
			$response->message = 'Object not found';

			header($SERVER_PROTOCOL.' 404 Not Found', TRUE, 404);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		if (isset($cacheData['objectPrimaryKeys'][$objectName]) == FALSE){

			mysqli_close($link);

			$response = new \stdClass;
			$response->code = '404';
			$response->message = 'Key not found';

			header($SERVER_PROTOCOL.' 404 Not Found', TRUE, 404);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		$objectKeyName = $cacheData['objectPrimaryKeys'][$objectName];
		$objectKeyType = $cacheData['objectColumnTypes'][$objectName][$objectKeyName];

		$sql = "SELECT `".implode("`, `", $cacheData['objectColumnNames'][$objectName])."`"
			." FROM `$objectName`"
			." WHERE `".$cacheData['objectPrimaryKeys'][$objectName]."` = "
			;

		if ($objectKeyType == 'integer'){
			$sql .= $objectKeyValue;
		} elseif ($objectKeyType == 'datetime'){
			$sql .= "'$objectKeyValue'";
		} elseif ($objectKeyType == 'character'){
			$sql .= "'".mysqli_real_escape_string($objectKeyValue, $link)."'";
		}

		if ($result = mysqli_query($link, $sql)){

			$rows = array();
			if ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){

				mysqli_free_result($result);
				mysqli_close($link);

				$response = new \stdClass;
				$response->result = $row;
				header('Content-Type: application/json');
				echo json_encode($response);
				exit();
			}

			mysqli_free_result($result);
			mysqli_close($link);

			$response = new \stdClass;
			$response->code = 404;
			$response->message = 'Record not found';

			header($SERVER_PROTOCOL.' 404 Not Found', TRUE, 404);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();

		} else {

			$response = new \stdClass;
			$response->code = mysqli_errno($link);
			$response->message = mysqli_error($link);

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();

		}
	}

} elseif ($REQUEST_METHOD == 'DELETE'){

	if (preg_match("/^\/([^\/]+)\/([^\/]+)/", $PATH_INFO, $match) == TRUE){

		// Handle DELETE /table-name/{id}

		$objectName = $match[1];
		$objectId = $match[2];

		// Check objectNames

		if (in_array($objectName, $cacheData['objectNames']) == FALSE){

			$response = new \stdClass;
			$response->code = '404';
			$response->message = 'Object not found';

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 404 Not Found', TRUE, 404);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		// Check objectPrimaryKeys

		if (isset($cacheData['objectPrimaryKeys'][$objectName]) == FALSE){

			$response = new \stdClass;
			$response->code = '500';
			$response->message = 'Object does not have a primary key';

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();
		}

		// TODO Check objectColumnTypes

		$sql = "DELETE FROM `$objectName`"
			." WHERE `".$cacheData['objectPrimaryKeys'][$objectName]."`"
				." = '".mysqli_real_escape_string($link, $objectId)."'"
			;

		if ($result = mysqli_query($link, $sql)){

			mysqli_close($link);
			header($SERVER_PROTOCOL.' 204 No Content', TRUE, 200);
			exit();

		} else {

			$response = new \stdClass;
			$response->code = mysqli_errno($link);
			$response->message = mysqli_error($link);

			mysqli_close($link);

			header($SERVER_PROTOCOL.' 500 Internal Server Error', TRUE, 500);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit();

		}
	}
}

mysqli_close($link);

$response = new \stdClass;
$response->code = -32000;
$response->message = 'Bad Request';
header($SERVER_PROTOCOL.' 400 Bad Request', TRUE, 400);
header('Content-Type: application/json');
echo json_encode($response);
exit();
