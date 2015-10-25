<?php
error_reporting(E_ALL);

echo "Start";
	
	require('proto.php');

	$timeStart = microtime(true);

	$p = new proto();
	
	if($p) { echo "Works"; } else { "Something has gone wrong"; }

//	var_dump($p->connect());
	$p->connect();
	$timeEnd = microtime(true);

	echo("<br /><br />Script took " . (($timeEnd - $timeStart)) . " seconds!\n");
?>
