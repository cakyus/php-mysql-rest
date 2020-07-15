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
				$cacheData['objectColumnTypes'][$objectName][$row['Field']] = $row['Type'];
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

	if (preg_match("/^\/([^\/]+)/", $PATH_INFO, $match) == TRUE){

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

	if (preg_match("/^\/[^\/]\//", $PATH_INFO, $match) == TRUE){

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
