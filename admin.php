<?php
mysql_connect("abram.cs.mercer.edu", "tourguide", "Wheretogo?") or (die(mysql_error()));
mysql_select_db("Map Page Coords");

session_start();

//15 minute timeout
define("SESSION_TIMEOUT", 60 * 15);
define("WORKDIR", "../../");
define("IMAGEDIR", "/images/map/thumbnails");

if(!isset($_REQUEST["q"]))
{
	echo "Error, no query\n";
}
if($_REQUEST["q"] != "login" && $_REQUEST["q"] != "isloggedin")
{
	loggedin() or die("Error: Not logged in");
	switch($_REQUEST["q"])
	{
		case "initpos":
		     initpos();
		     break;
		case "getpaths":
		     getpaths();
		     break;
		case "addpoi":
		     addpoi();
		     break;
		case "delmarker":
		     delmarker();
		     break;
		case "delpath":
		     delpath();
		     break;
		case "addpath":
		     addpath();
		     break;
		case "updatepoi":
		     updatepoi();
		     break;
		case "uploadpic":
		     uploadpic();
		     break;
		case "logout":
		     logout();
		     break;
		case "refresh":
		     break;
	}
}
else
{
	switch($_REQUEST["q"])
	{
		case "login":
		     login();
		     break;
		case "isloggedin":
		     echo loggedin();
		     break;
	}
}

function initpos()
{
	$query = "SELECT id, place, lat, lng, infoimg FROM poi";
	$poi = mysql_query($query) or die(mysql_error());
	$once = false;
	while($point = mysql_fetch_row($poi))
	{
		if($once)
			echo "\n";
		$once = true;
		echo "$point[0]|$point[1]|$point[2]|$point[3]|$point[4]";
	}
	mysql_free_result($poi);
}

function getpaths()
{
	//Output Format: dest1:lat1,lng1|lat2,lng2|...:enabled
	//		 dest2:lat1,lng1|...:enabled
	$query = "SELECT DISTINCT poi2, enabled FROM paths WHERE poi1 = " . $_REQUEST["id"];
	$destq = mysql_query($query) or (die(mysql_error()));
	$onced = false;
	while($dest = mysql_fetch_row($destq))
	{
		if($onced)
			echo "\n";
		$onced = true;
		$query = "SELECT lat, lng FROM paths WHERE poi1 = " . $_REQUEST["id"] . " AND poi2 = " . $dest[0] . " ORDER BY elnum";
		$path = mysql_query($query) or (die(mysql_error()));
		echo $dest[0] . ":";
		$oncep = false;
		while($point = mysql_fetch_row($path))
		{
			if($oncep)
				echo "|";
			$oncep = true;
			echo $point[0] . "," . $point[1];
		}
		echo ":" . $dest[1];
		mysql_free_result($path);
	}
	mysql_free_result($destq);
}

function addpoi()
{
	$query = "INSERT INTO poi (lat, lng) VALUES(" . $_REQUEST["lat"] . ", " . $_REQUEST["lng"] . ")";
	mysql_query($query) or (die(mysql_error()));
	echo mysql_insert_id();
}

function delmarker()
{
	$query = "DELETE FROM poi WHERE id = " . $_REQUEST["id"];
	mysql_query($query) or (die(mysql_error()));
	$query = "DELETE FROM paths WHERE poi1 = " . $_REQUEST["id"] . " OR poi2 = " . $_REQUEST["id"];
	mysql_query($query) or (dir(mysql_error()));
}

function delpath()
{
	//We aren't certain which is poi1 and which is poi2, so use both combinations
	$query = "DELETE FROM paths WHERE ";
	$query = $query . "(poi1 = " . $_REQUEST['poi1'] . " AND poi2 = " . $_REQUEST['poi2'] . ") OR ";
	$query = $query . "(poi1 = " . $_REQUEST['poi2'] . " AND poi2 = " . $_REQUEST['poi1'] . ")";
	mysql_query($query);
}

function addpath()
{
        //Input format:
        //cnt=val&lat1=val&lng1=val&lat2=val&...
	$count = intval($_REQUEST["cnt"]);
	$poi1 = $_REQUEST["poi1"];
	$poi2 = $_REQUEST["poi2"];
	$query = "INSERT INTO paths (poi1, poi2, elnum, lat, lng) VALUES ";
	$once = false;
	for($i = 1; $i <= $count; $i++)
	{
		if($once)
			$query = $query . ", ";
		$once = true;
		$lat = $_REQUEST["lat$i"];
		$lng = $_REQUEST["lng$i"];
		$query = $query . "($poi1, $poi2, $i, $lat, $lng)";
	}
	mysql_query($query);
}

function updatepoi()
{
	$query = "UPDATE poi SET place=\"" . $_REQUEST["place"] . "\" WHERE id=" . $_REQUEST["id"];
	mysql_query($query);
}

//Currently PHP cannot write any files.
//Ask Michael to fix this
function uploadpic()
{
	$id = $_REQUEST['id'];
	$output = IMAGEDIR . "/$id.jpg";
	$contents = file_get_contents('php://input');
	if($contents == false)
	{
		echo "Failed to read the uploaded file";
	}
	else
	{
		file_put_contents(WORKDIR . $output, $contents);
		echo $output;
		$query = "UPDATE poi SET infoimg=\"$output\" WHERE id=$id";
//Uncomment the following when PHP is allowed to write the image
		mysql_query($query);
	}
}

function login()
{
	$user = $_REQUEST["user"];
	$pass = $_REQUEST["pass"];
	$query = "SELECT salt, digest, id FROM users WHERE name=\"$user\"";
	$validate = mysql_query($query);
	$userinf = mysql_fetch_row($validate);
	$digest = crypt($pass, $userinf[0]);
	if($digest == $userinf[1])
	{
		//Valid login credentials, let them through
		session_regenerate_id();
		$_SESSION['login'] = true;
		$_SESSION['userid'] = $userinf[3];
		$_SESSION['prvtime'] = time();
		echo "true";
	}
    else
        echo "$user\n$pass\n$query\n$digest\n$userinf[1]";
	mysql_free_result($validate);
}

function logout()
{
	$_SESSION = array();
	session_destroy();
}

function loggedin()
{
	$chk = true;
	if(!isset($_SESSION['login']) && !$_SESSION['login'])
	{
		$chk = false;
	}
	else
	{
		$curtime = time();
		$dtime = $curtime - $_SESSION['prvtime'];
		if($dtime > SESSION_TIMEOUT)
			$chk = false;
		$_SESSION['prvtime'] = $curtime;
	}
	return $chk;
}

function checktype($file)
{
	$finfo = new FileInfo(null);
	$type = "";
	switch($finfo->file($_FILES['image'][$file], FILEINFO_MIME))
	{
		case 'image/jpg':
			$type = 'jpg';
			break;
		case 'image/png':
			$type = 'png';
			break;
		case 'image/gif':
			$type = 'gif';
			break;
	}
	return $type;
}

mysql_close();
?>