    <?php

$mysqli = new mysqli("127.0.0.1", "root", "livegoals", "livegoals");
if (!$mysqli) {
    echo "Error: Unable to connect to MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}

$sql    = "SELECT DISTINCT bet365_id,fixture from scan_games";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    // output data of each row
    
    $success = 0;
    $total   = 0;
    $t = 0;
    $oddstotal = 0;
    while ($row = $result->fetch_assoc()) {
        
     //   echo "<h2>" . $row['fixture'] . "</h2> (" . $row['bet365_id'] . ")<br />";
        
        $sql   = "SELECT * from scan_games where bet365_id = '" . $row['bet365_id'] . "' ORDER BY id ASC";
        $data  = $mysqli->query($sql);
        $goals = 0;
        $odds  = array();
        $i     = 0;
        
  
       	$totalrows = $data->num_rows;
        while ($row = $data->fetch_assoc()) {
            
         	$t++;
            $i++;
            $win = 0;
            $target = array();
            $odds[$i] = $row['odds'];
            $shotson = $row['home_shotson'] + $row['away_shotson'];
            $shotsoff = $row['home_shotsoff'] + $row['away_shotsoff'];

		
		
            
            $oldodds = $odds[$i - 1];
 
            
            
            if (($oldodds >= $_GET['minodds']) && ($oldodds <= $_GET['maxodds']) && $_GET['minshotson'] <= $shotson && $_GET['minshotsoff'] <= $shotsoff && $oldodds != "N/A") {
            
            if(!isset($target[$goals+1]) && $oldodds != "N/A") { // Essentially place a bet
             $target[$row['bet365_id']][$goals+1]['odds'] = $oldodds;
             $target[$row['bet365_id']][$goals+1]['TIME'] = $row['TIME'];
             
             $total++;
            }
            
                       
            if (  $target[$row['bet365_id']][$goals+1] > $goals) {
                
                echo $row['fixture'].' - Over '.($row['home_goals'] + $row['awaygoals']-1).'.5 goals @ '.$oldodds.' Other odds: '.$target[$row['bet365_id']][$goals+1]['odds'].' (Placed at '.$target[$row['bet365_id']][$goals+1]['TIME'] .'Settled by Time:'.$row['TIME'].') Current goals: '.$row['home_goals'] + $row['awaygoals'].'<br />';
                $win = 1;
                
                if(isset($_GET['bet'])) {
                
                $winnings = $winnings + ($_GET['bet'] * $oldodds);      
             //    echo $winnings;        
                }
                $success++;
                
                $oddstotal = $oddstotal + $oldodds;
                
            }
            
            if($i = $totalrows) {
            
            // Last one
            
           // var_dump($target);
        
            }
            
            if ($goals < $row['home_goals'] + $row['awaygoals']) {
                $goals = $row['home_goals'] + $row['awaygoals'];
            }
            
  
            }
            
         
            
            
            
        }
        
    }
    
    echo "Success: " . $success . "<br /> Total: " . $total . " Win: " . (number_format($success / $total, 2) * 100) . "% Avg Win Odds: ".number_format($oddstotal/$success,2)."<br />";
    
    if(isset($_GET['bet'])) {
    
    $losses = ($total - $success) * $_GET['bet'];
    $profit = $winnings - $losses;
    
  //  echo "Total Staked: &pound;".($_GET['bet'] * $total)." returns &pound;".$profit;
    
    }
    
}

?>