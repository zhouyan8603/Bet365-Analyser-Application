<?php

require_once( 'http.php' );
class proto
{
	var $homePage;
	var $sessionId;
	var $powConnectionDetails;
	var $clientRn;
	var $clientId;
	var $serverNum = 0;
	var $readItConstants = array( 'RECORD_DELIM' => "\x01", 'FIELD_DELIM' => "\x02", 'MESSAGE_DELIM' => "\b", 'CLIENT_CONNECT' => 0, 'CLIENT_POLL' => 1, 'CLIENT_SEND' => 2, 'INITIAL_TOPIC_LOAD' => 20, 'DELTA' => 21, 'CLIENT_SUBSCRIBE' => 22, 'CLIENT_UNSUBSCRIBE' => 23, 'CLIENT_SWAP_SUBSCRIPTIONS' => 26, 'NONE_ENCODING' => 0, 'ENCRYPTED_ENCODING' => 17, 'COMPRESSED_ENCODING' => 18, 'BASE64_ENCODING' => 19, 'SERVER_PING' => 24, 'CLIENT_PING' => 25, 'CLIENT_ABORT' => 28, 'CLIENT_CLOSE' => 29, 'ACK_ITL' => 30, 'ACK_DELTA' => 31, 'ACK_RESPONSE' => 32 );
	

	function getPropData( $name )
	{
		if ( preg_match_all( '#"' . $name . '":(\x20|)\{(.*?)\},#ims', $this->homePage, $matches ) ) {
			return json_decode( '{' . $matches[ 2 ][ 0 ] . '}', true );
		}
		if ( preg_match_all( '#"' . $name . '"(\x20|):\[(.*?)\]#ims', $this->homePage, $matches ) ) {
			return json_decode( '[' . $matches[ 2 ][ 0 ] . ']', true );
		}
		if ( preg_match_all( '#"' . $name . '":(.*?),#ims', $this->homePage, $matches ) ) {
			$var = rtrim( ltrim( $matches[ 1 ][ 0 ] ) );
			if ( substr( $var, 0, 1 ) == '"' ) {
				return substr( $var, 1, -1 );
			}
			return $var;
		}
		echo "Name: " . $name . "<br />";
		echo "Home page:" . $this->homePage . "<br />";
		echo "Matches: " . $matches . "<br />";
		return NULL;
	}
	
	function powRequest( $sid, $specialHeaders = array(), $postData = '' )
	{
		$defaultHeaders = array(
			'Content-Type:  ; charset=UTF-8',
			'Referer: https://mobile.bet365.com/',
			'Origin: https://mobile.bet365.com' 
			);
		if ( !empty( $this->clientId ) ) {
			array_push( $defaultHeaders, 'clientid: ' . $this->clientId );
		}
		if ( $sid != 0 ) {
			array_push( $defaultHeaders, 's: ' . $this->serverNum );
			$this->serverNum++;
		}
		$totalHeaders = array_merge( $specialHeaders, $defaultHeaders );
								//var_dump($totalHeaders);
		return http::post( $this->powConnectionDetails[ 1 ][ 'Host' ] . '/pow/?sid=' . $sid . '&rn=' . $this->clientRn, $postData, $totalHeaders );
	}
	
	function parameterizeLine( $line )
	{
		$chunk = explode( ';', $line );
		if ( empty( $chunk ) )
			return FALSE;
		$cmd = $chunk[ 0 ];
								// Remove cmd element
		array_shift( $chunk );
		$params = array();
		foreach ( $chunk as $pstr ) {
			$pdata = explode( '=', $pstr );
			if ( count( $pdata ) != 2 )
				continue;
			$params[ $pdata[ 0 ] ] = $pdata[ 1 ];
		}
		return array(
			'cmd' => $cmd,
			'params' => $params 
			);
	}
	
	function connect()
	{
		http::setCookieJar( 'cookie.txt' );
		$this->homePage = http::get( 'http://mobile.bet365.com' );
		if ( $this->homePage === FALSE || empty( $this->homePage ) )
			return FALSE;
		$this->sessionId = $this->getPropData( 'sessionId' );
		if ( $this->sessionId === NULL || empty( $this->sessionId ) )
			return FALSE;
								//echo("Session ID: " . $this->sessionId . "\n");
		$this->powConnectionDetails = $this->getPropData( 'ConnectionDetails' );
		if ( $this->powConnectionDetails === NULL || empty( $this->powConnectionDetails ) )
			return FALSE;
		if ( !isset( $this->powConnectionDetails[ 0 ] ) || !isset( $this->powConnectionDetails[ 0 ][ 'Host' ] ) )
			return FALSE;
								//echo("Pow HTTPS Host: {$this->powConnectionDetails[1]['Host']}:{$this->powConnectionDetails[1]['Port']}\n");
		$this->clientRn = substr( str_shuffle( "0123456789" ), 0, 16 );
								// echo("Pow Random Number: {$this->clientRn}\n");
		$requestPow     = $this->powRequest( 0, array(
			'method: 0',
			'transporttimeout: 20',
			'type: F',
			'topic: S_' . $this->sessionId 
			) );
								//	var_dump($requestPow);
		if ( $requestPow === FALSE || empty( $requestPow ) )
			return FALSE;
		$data = explode( $this->readItConstants[ 'FIELD_DELIM' ], $requestPow );
		if ( empty( $data ) || count( $data ) == 0 || count( $data ) == 1 )
			return FALSE;
								//	echo("Constant: {$data[0]}\n");
								//	echo("Pow Session Id: {$data[1]}\n");
		$this->clientId = $data[ 1 ];
		$sslStatus      = urlencode( $this->powConnectionDetails[ 1 ][ 'Host' ] . ':' . $this->powConnectionDetails[ 1 ][ 'Port' ] );
								// Inform the main site of our connection
		http::post( 'https://mobile.bet365.com/pushstatus/logpushstatus.ashx?state=true', 'sslStatus=' . $sslStatus . '&connectionID=' . $this->clientId . '&uid=' . $this->clientRn . '&connectionStatus=0&stk=' . $this->sessionId, array(
			'X-Requested-With: XMLHttpRequest',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' 
			) );
		$requestPow = $this->powRequest( 2, array(
			'method: 1' 
			) );
								// Subscribe to the InPlay list
		$this->subscribe( 'OVInPlay_1_3//' );
		$requestPow = $this->powRequest( 2, array(
			'method: 1' 
			) );
		if ( substr( $requestPow, 0, 1 ) != "\x14" ) {
			echo ( "Unexpected InPlay packet header" );
			echo "PoW: " . $requestPow;
												//return FALSE;
		}
								// Here we have some soccer data!!! wow!!
		$gameData = explode( $this->readItConstants[ 'RECORD_DELIM' ], $requestPow );
		$gameData = explode( "|", $gameData[ count( $gameData ) - 1 ] );
								array_shift( $gameData ); // "F"
								$initialCL = $this->parameterizeLine( $gameData[ 0 ] );
								if ( $initialCL === FALSE )
									return FALSE;
								if ( $initialCL[ 'cmd' ] != 'CL' )
									return FALSE;
								if ( $initialCL[ 'params' ][ 'NA' ] != 'Soccer' )
												return FALSE; // It isn't soccer!!??
											$events = array();
								// skip the initial CL (soccer)
											for ( $i = 1; $i < count( $gameData ); $i++ ) {
												$lineData = $this->parameterizeLine( $gameData[ $i ] );
												//echo "GAME DATA: ".$gameData[$i];
												if ( $lineData === FALSE )
													continue;
												// "EV" == EVENT
												// "CT" == COMPETITION_NAME
												// "PA" == PARTICIPANT
												// "MA" == MARKET
												// "CL" == CLASSIFICATION
												// "OR" == ORDER
												var_dump( $lineData[ 'cmd' ] );
												if ( $lineData[ 'cmd' ] == 'EV' ) {
																//if($lineData['params']['ID'] != '1')
																//	continue;
													array_push( $events, $lineData[ 'params' ] );
												} elseif ( $lineData[ 'cmd' ] == 'CT' ) {
													if ( $lineData[ 'params' ][ 'NA' ] == 'Coupons' ) {
																				break; // It adds some kind of coupon stuff... what
																			}
																//array_push($events, $lineData['params']);
																		} elseif ( $lineData[ 'cmd' ] == 'CL' ) {
																break; // Not soccerevent
															}
														}
														
														$requestPow = $this->powRequest( 2, array(
															'method: 1' 
															) );
								//	echo("Trying for ID: {$events[0]['ID']}\n");
														$this->unsubscribe( 'OVInPlay_1_3//' );
														$i = 0;
														?>
														<div class="row">
															<div class="col-md-12">
																
																<?php
																$mysqli = new mysqli( "127.0.0.1", "root", "livegoals", "livegoals" );
																if ( !$mysqli ) {
																	echo "Error: Unable to connect to MySQL." . PHP_EOL;
																	echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
																	echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
																	exit;
																} else {
																	echo "Connection Successful";
																}
																$mysqli->connect( "127.0.0.1", "root", "livegoals", "livegoals" );
																$sql   = "update games set live = '0'";
																$live  = "0";
																$stmt1 = $mysqli->prepare( "UPDATE games set live = ?" );
																$stmt1->bind_param( "s", $live );
																if ( !$stmt1->execute() ) {
																	die( "Update Error" );
																}
								//$stmt1->close();
								//$result = $mysqli->execute($sql);	
																foreach ( $events as $value ) {
																	echo $i . "<br />";
																	$soccerEvent = $this->getSoccerEventInformation( $events[ $i ][ 'ID' ] );
																	$id          = $events[ $i ][ 'ID' ];
																	$result      = $mysqli->query( "SELECT * from scan_games where bet365_id='" . $id . "' ORDER BY id DESC LIMIT 1;" );
																	$old         = $result->fetch_array();
																	$c      = $mysqli->query( "SELECT count(*) from scan_games where bet365_id='" . $id . "' ORDER BY id DESC;" );
																	$count        = $result->fetch_array();
																	$live = "1";
																	$stmt = $mysqli->prepare( "REPLACE INTO games(bet365_id, fixture, competition, TIME , home_goals, away_goals, odds_homewin, odds_draw, odds_awaywin, home_corners, away_corners, home_danger, away_danger, home_shotson, away_shotson, home_shotsoff, away_shotsoff, odds, home_possession, away_possession, fh_odds, home_reds, away_reds) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)" );
																	$scan = $mysqli->prepare( "INSERT INTO scan_games(bet365_id, fixture, competition, TIME, home_goals, away_goals, odds_homewin, odds_draw, odds_awaywin, home_corners, away_corners, home_danger, away_danger, home_shotson, away_shotson, home_shotsoff, away_shotsoff, odds, home_possession, away_possession, fh_odds, home_reds, away_reds) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)" );
																	//echo $mysqli->error_list;
																	if ( $stmt ) {
																		if ( !$events[ $i ][ 'NA' ] ) {
																			$events[ $i ][ 'NA' ] = "0";
																		}
																		if ( !$events[ $i ][ 'CT' ] ) {
																			$events[ $i ][ 'CT' ] = "0";
																		}
																		if ( !$events[ $i ][ 'TU' ] ) {
																			$events[ $i ][ 'TU' ] = "0";
																		}
																		if ( !$events[ $i ][ 'TM' ] ) {
																			$events[ $i ][ 'TM' ] = "0";
																		}
																		if ( !$events[ $i ][ 'UC' ] ) {
																			$events[ $i ][ 'UC' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'IGoal' ] ) {
																			$soccerEvent[ 'team1' ][ 'IGoal' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team2' ][ 'IGoal' ] ) {
																			$soccerEvent[ 'team2' ][ 'IGoal' ] = "0";
																		}
																		if ( !$soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ] ) {
																			$soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ] = "0";
																		}
																		if ( !$soccerEvent[ 'Fulltime Result' ][ "Draw" ] ) {
																			$soccerEvent[ 'Fulltime Result' ][ "Draw" ] = "0";
																		}
																		if ( !$soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ] ) {
																			$soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'ICorner' ] ) {
																			$soccerEvent[ 'team1' ][ 'ICorner' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'ICorner' ] ) {
																			$soccerEvent[ 'team2' ][ 'ICorner' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'Dangerous Attacks' ] ) {
																			$soccerEvent[ 'team1' ][ 'Dangerous Attacks' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team2' ][ 'Dangerous Attacks' ] ) {
																			$soccerEvent[ 'team2' ][ 'Dangerous Attacks' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'On Target' ] ) {
																			$soccerEvent[ 'team1' ][ 'On Target' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team2' ][ 'On Target' ] ) {
																			$soccerEvent[ 'team2' ][ 'On Target' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'Off Target' ] ) {
																			$soccerEvent[ 'team1' ][ 'Off Target' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team2' ][ 'Off Target' ] ) {
																			$soccerEvent[ 'team2' ][ 'Off Target' ] = "0";
																		}
																		if ( !$soccerEvent[ 'team1' ][ 'Possession %' ] ) {
																			$home_possession = "N/A";
																		} else {
																			
																		$home_possession = $soccerEvent[ 'team1' ][ 'Possession %' ];		
																		}															
																		if ( !$soccerEvent[ 'team2' ][ 'Possession %' ] ) {
																			$away_possession = "N/A";
																		} else {
																			
																		$away_possession = $soccerEvent[ 'team2' ][ 'Possession %' ];		
																		}	
																		if ( !$soccerEvent[ 'team1' ][ 'IRedCard' ] ) {
																			$home_reds = "N/A";
																		} else {
																			
																		$home_reds = $soccerEvent[ 'team1' ][ 'IRedCard' ];		
																		}
																		if ( !$soccerEvent[ 'team2' ][ 'IRedCard' ] ) {
																			$away_reds = "N/A";
																		} else {
																			
																		$away_reds = $soccerEvent[ 'team2' ][ 'IRedCard' ];		
																		}																																																											
																		$TUH = substr( $events[ $i ][ "TU" ], 8, 2 );
																		$TUM = substr( $events[ $i ][ "TU" ], 10, 2 );
																		$TUS = substr( $events[ $i ][ "TU" ], 12, 2 );
																		$UC  = $events[ $i ][ "UC" ];
																		$TM  = $events[ $i ][ "TM" ];
																		$CT  = explode( ":", date( "H:i:s" ) );
																		
								//echo($CT[0].":".$CT[1].":".$CT[2]." - ".$TUH.":".$TUM.":".$TUS);
																		if($CT[2]<$TUS){
																			$CT[2] = $CT[2] +60;
																			$CT[1] = $CT[1] -1;
																		}
																		if($CT[0]-$TUH>0){
																			$CT[1] = $CT[1]+60;
																		}
																		$secsElapsed = ($CT[2]-$TUS);
																		if($secsElapsed < 10){
																			$secsElapsed = "0".$secsElapsed;
																		}
																		$minsElapsed = ($CT[1]-$TUM);
																		if($TUH=="") {
																			$time = "HNS";
																		} else {
																			if(strpos($UC,'Half-Time')) { 
																				$time = "HT"; 
																			} else if (strpos($UC,'Full-Time')){
																				$time = "FT";
																			} else {
																				$time = (($minsElapsed+$TM).":".$secsElapsed);
																			}
																		}	
																		
																		if ( strlen( $time ) == "4" ) {
																			$time = "0" . $time;
																		}
																		
																
																		$totalgoals    = $soccerEvent[ 'team1' ][ 'IGoal' ] + $soccerEvent[ 'team2' ][ 'IGoal' ];
																		$totalsot      = $soccerEvent[ 'team1' ][ 'On Target' ] + $soccerEvent[ 'team2' ][ 'On Target' ];
																		$totalshotsoff = $soccerEvent[ 'team1' ][ 'Off Target' ] + $soccerEvent[ 'team2' ][ 'Off Target' ];
																		if ( isset( $soccerEvent[ 'Match Goals' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$nextgoalodds = $soccerEvent[ 'Match Goals' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "Match Goals";
																		} elseif ( isset( $soccerEvent[ 'Alternative Match Goals' ][ $totalgoals . '.5' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$nextgoalodds = $soccerEvent[ 'Alternative Match Goals' ][ $totalgoals . '.5' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "Alt Match Goals";
																		} elseif ( isset( $soccerEvent[ 'Goal Line' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$nextgoalodds = $soccerEvent[ 'Goal Line' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "GL";
																		} else {
																			$nextgoalodds = "N/A";
																			
																			// No market
																		}
																					if ( isset( $soccerEvent[ 'First Half Goals' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$fh_odds = $soccerEvent[ 'First Half Goals' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "First Half Goals";
																		} elseif ( isset( $soccerEvent[ 'Alternative 1st Half Goal Line ('.$soccerEvent[ 'team1' ][ 'IGoal' ].' - '. $soccerEvent[ 'team2' ][ 'IGoal' ].')' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$fh_odds = $soccerEvent[ 'Alternative 1st Half Goal Line ('.$soccerEvent[ 'team1' ][ 'IGoal' ].' - '. $soccerEvent[ 'team2' ][ 'IGoal' ].')' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "Alt GL";
																		}  elseif ( isset( $soccerEvent[ '1st Half Goal Line ('.$soccerEvent[ 'team1' ][ 'IGoal' ].' - '. $soccerEvent[ 'team2' ][ 'IGoal' ].')' ][ 'Over ' . $totalgoals . '.5' ] ) ) {
																			$fh_odds = $soccerEvent[ '1st Half Goal Line ('.$soccerEvent[ 'team1' ][ 'IGoal' ].' - '. $soccerEvent[ 'team2' ][ 'IGoal' ].')' ][ 'Over ' . $totalgoals . '.5' ];
																			echo "GL";
																		} else {
																			$fh_odds = "N/A";
																			
																			// No market
																		}
																		$nextgoalodds = explode( "/", $nextgoalodds );
																		if ( $nextgoalodds[ 1 ] > 0 && $nextgoalodds[ 0 ] > 0 ) {
																			$odds = ( number_format( 1 + $nextgoalodds[ 0 ] / $nextgoalodds[ 1 ], 2 ) );
																		} else {
																			$odds = "N/A";
																		}
																		
																		$fh_odds = explode( "/", $fh_odds );
																		if ( $fh_odds[ 1 ] > 0 && $fh_odds[ 0 ] > 0 ) {
																			$fh_odds = ( number_format( 1 + $fh_odds[ 0 ] / $fh_odds[ 1 ], 2 ) );
																		} else {
																			$fh_odds = "N/A";
																		}
																		
																			echo "<br /><hr>Home Poss: ".$home_possession;
																	echo "<br />Away Poss: ".$away_possession;
																	echo "<br />FH Odds: ".$fh_odds;
																	
																		$stmt->bind_param( "sssssssssssssssssssssss", $events[ $i ][ 'ID' ], $events[ $i ][ 'NA' ], $events[ $i ][ 'CT' ], $time, $soccerEvent[ 'team1' ][ 'IGoal' ], $soccerEvent[ 'team2' ][ 'IGoal' ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ], $soccerEvent[ 'Fulltime Result' ][ "Draw" ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ], $soccerEvent[ 'team1' ][ 'ICorner' ], $soccerEvent[ 'team2' ][ 'ICorner' ], $soccerEvent[ 'team1' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team2' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team1' ][ 'On Target' ], $soccerEvent[ 'team2' ][ 'On Target' ], $soccerEvent[ 'team1' ][ 'Off Target' ], $soccerEvent[ 'team2' ][ 'Off Target' ], $odds, $home_possession, $away_possession, $fh_odds, $home_reds, $away_reds);
																		$timesplit = substr( $time, 0, 1 );
																		 
																		if(($timesplit == "9" || $time == "FT" ) && $count[0] < 9) {
																		
																		 $mysqli->query( "DELETE from scan_games where bet365_id='" . $soccerEvent['id'] . "';" );
																		 break 1;
																		 // We don't want games which are incomplete

																		}
																		
																		if(
																		 (empty($totalsot) && $odds == "N/A" && empty($soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ]) && empty($soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ]) && empty($soccerEvent[ 'Fulltime Result' ][ "Draw" ])  ))
																		{
																		
																		echo "<h1>Probably Suspended!!!</h1>";
																		// It is highly likely the market is suspended try again in a bit (it will always record the first entry)..
																	
																		
																		
																		} else {
																		echo "<h1>New Time: " . $time . " -  Old Time: " . $old[ 'TIME' ] . " TimeSplit: ". $timesplit ." Old Time Split: ". substr( $old[ 'TIME' ], 0, 1 ) ." </h1>";
																		if ( !isset( $old[ 'TIME' ] ) ) {
																			$scan->bind_param( "sssssssssssssssssssssss", $events[ $i ][ 'ID' ], $events[ $i ][ 'NA' ], $events[ $i ][ 'CT' ], $time, $soccerEvent[ 'team1' ][ 'IGoal' ], $soccerEvent[ 'team2' ][ 'IGoal' ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ], $soccerEvent[ 'Fulltime Result' ][ "Draw" ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ], $soccerEvent[ 'team1' ][ 'ICorner' ], $soccerEvent[ 'team2' ][ 'ICorner' ], $soccerEvent[ 'team1' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team2' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team1' ][ 'On Target' ], $soccerEvent[ 'team2' ][ 'On Target' ], $soccerEvent[ 'team1' ][ 'Off Target' ], $soccerEvent[ 'team2' ][ 'Off Target' ], $odds, $home_possession, $away_possession, $fh_odds, $home_reds, $away_reds );
																			if($scan->execute()) {
																				echo "Stored for the very first time..";
																			} else {
																				$scan->error_list;
																			}
																		} else {
																			if ( $timesplit == "H" || $timesplit == "F") {
																								// We don't want to store this
																			} elseif ( ( $timesplit != substr( $old[ 'TIME' ], 0, 1 ) ) || (substr($time,0,2) > "85") ) {
																				$scan->bind_param( "sssssssssssssssssssssss", $events[ $i ][ 'ID' ], $events[ $i ][ 'NA' ], $events[ $i ][ 'CT' ], $time, $soccerEvent[ 'team1' ][ 'IGoal' ], $soccerEvent[ 'team2' ][ 'IGoal' ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team1" ][ "name" ] ], $soccerEvent[ 'Fulltime Result' ][ "Draw" ], $soccerEvent[ 'Fulltime Result' ][ $soccerEvent[ "team2" ][ "name" ] ], $soccerEvent[ 'team1' ][ 'ICorner' ], $soccerEvent[ 'team2' ][ 'ICorner' ], $soccerEvent[ 'team1' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team2' ][ 'Dangerous Attacks' ], $soccerEvent[ 'team1' ][ 'On Target' ], $soccerEvent[ 'team2' ][ 'On Target' ], $soccerEvent[ 'team1' ][ 'Off Target' ], $soccerEvent[ 'team2' ][ 'Off Target' ], $odds, $home_possession, $away_possession, $fh_odds, $home_reds, $away_reds );
																				if($scan->execute()) {
																					echo "Scanned and stored<br />";
																				} else {
																					echo $scan->error_list;
																				}
																			}
																		}
																	
																
																	
																	
																	
																	if ( $stmt->execute() ) {
																		echo $i . " Success " . $events[ $i ][ 'NA' ];
																		$i++;
																	} else {
																		var_dump( $mysqli->error_list );
																	}
																	}
																}
																echo "Success (" . $i . ")";
															}
																return FALSE;
															}
															function getSoccerEventInformation( $id )
															{
																$this->subscribe( "$id//" );
								// Update
																$requestPow        = $this->powRequest( 2, array(
																	'method: 1' 
																	) );
																$eventExpandedData = explode( $this->readItConstants[ 'RECORD_DELIM' ], $requestPow );
																$eventExpandedData = explode( '|', $eventExpandedData[ count( $eventExpandedData ) - 1 ] );
								//var_dump($eventExpandedData);
																$res               = array();
																$res[ 'team1' ]    = array();
																$res[ 'team2' ]    = array();
																$evParsedData      = array();
																for ( $i = 0; $i < count( $eventExpandedData ); $i++ ) {
																	$parsedLine = $this->parameterizeLine( $eventExpandedData[ $i ] );
																	if ( $parsedLine === FALSE )
																		continue;
												if ( $parsedLine[ 'cmd' ] == 'EV' ) { // Event
													$evParsedData = $parsedLine[ 'params' ];
																//var_dump($evParsedData);
												} elseif ( $parsedLine[ 'cmd' ] == 'TE' ) { // "TE" = TEAM
												$currentArrayTeam                   = 'team' . ( $parsedLine[ 'params' ][ 'OR' ] + 1 );
												$res[ $currentArrayTeam ][ 'name' ] = $parsedLine[ 'params' ][ 'NA' ];
												for ( $stat = 1; $stat < 9; $stat++ ) {
													if ( array_key_exists( 'S' . $stat, $evParsedData ) ) {
														if ( empty( $parsedLine[ 'params' ][ 'S' . $stat ] ) )
															continue;
														$res[ $currentArrayTeam ][ $evParsedData[ 'S' . $stat ] ] = $parsedLine[ 'params' ][ 'S' . $stat ];
													}
												}
												} elseif ( $parsedLine[ 'cmd' ] == 'MA' ) { // Column Data
													$res[ $parsedLine[ 'params' ][ 'NA' ] ] = array();
													if ( $parsedLine[ 'params' ][ 'NA' ] == 'Alternative Match Goals' || $parsedLine[ 'params' ][ 'NA' ] == 'Match Goals' ) {
														$k          = $i + 1;
														$columnData = $this->parameterizeLine( $eventExpandedData[ $k ] );
														$parammeter = "";
														while ( 1 == 1 ) {
															if ( $columnData[ 'cmd' ] == 'CO' ) {
																												//do nothing
															} else {
																$columnFillData = $this->parameterizeLine( $eventExpandedData[ $k ] );
																$parameter      = trim( $columnFillData[ 'params' ][ 'HD' ] );
																if ( $parsedLine[ 'params' ][ 'NA' ] == 'Match Goals' ) {
																	$columnFillData[ 'params' ][ 'NA' ] .= $parameter;
																	$res[ $parsedLine[ 'params' ][ 'NA' ] ][ $columnFillData[ 'params' ][ 'NA' ] ] = $columnFillData[ 'params' ][ 'OD' ];
																} else if ( $parameter != "" )
																$res[ $parsedLine[ 'params' ][ 'NA' ] ][ $parameter ][ $columnFillData[ 'params' ][ 'NA' ] ] = $columnFillData[ 'params' ][ 'OD' ];
															}
															$columnData = $this->parameterizeLine( $eventExpandedData[ $k + 1 ] );
															$k++;
															if ( $columnData[ 'cmd' ] == 'MA' )
																break;
														}
													} else {
														$columnData = $this->parameterizeLine( $eventExpandedData[ $i + 1 ] );
														if ( $columnData[ 'cmd' ] == 'CO' && isset( $columnData[ 'params' ][ 'CN' ] ) && is_numeric( $columnData[ 'params' ][ 'CN' ] ) ) {
															for ( $cn = 1; $cn < ( $columnData[ 'params' ][ 'CN' ] + 1 ); $cn++ ) {
																$columnFillData = $this->parameterizeLine( $eventExpandedData[ $i + 1 + $cn ] );
																if ( $columnFillData[ 'cmd' ] == 'PA' ) {
																	$res[ $parsedLine[ 'params' ][ 'NA' ] ][ $columnFillData[ 'params' ][ 'NA' ] ] = $columnFillData[ 'params' ][ 'OD' ];
																}
															}
														}
													}
																/*
																$columnData = $this->parameterizeLine($eventExpandedData[$i + 1]);
																
																if($columnData['cmd'] == 'CO' && isset($columnData['params']['CN']) && is_numeric($columnData['params']['CN'])) {
																for($cn = 1; $cn < ($columnData['params']['CN'] + 1); $cn++) {
																$columnFillData = $this->parameterizeLine($eventExpandedData[$i + 1 + $cn]);
																
																if($columnFillData['cmd'] == 'PA') {
																$res[$parsedLine['params']['NA']][$columnFillData['params']['NA']] = $columnFillData['params']['OD'];
																}
																}
															}*/
												} elseif ( $parsedLine[ 'cmd' ] == 'SC' ) { // "SCORES_COLUMN"?
												if ( empty( $parsedLine[ 'params' ][ 'NA' ] ) )
																				continue; // no?
																			$dc11                                              = $this->parameterizeLine( $eventExpandedData[ $i + 1 ] );
																			$dc12                                              = $this->parameterizeLine( $eventExpandedData[ $i + 2 ] );
																			$res[ 'team1' ][ $parsedLine[ 'params' ][ 'NA' ] ] = $dc11[ 'params' ][ 'D1' ];
																			$res[ 'team2' ][ $parsedLine[ 'params' ][ 'NA' ] ] = $dc12[ 'params' ][ 'D1' ];
																		}
																	}
																	return $res;
																}
																function unsubscribe( $channel )
																{
																	$this->powRequest( 2, array(
																		'method: 23',
																		"topic: " . $channel 
																		) );
																}
																function subscribe( $channel )
																{
																	$this->powRequest( 2, array(
																		'method: 22',
																		"topic: " . $channel 
																		) );
																}
															}
															?>


															