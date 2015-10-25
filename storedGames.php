
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

<!-- Latest compiled and minified JavaScript -->
<script src="http://code.jquery.com/jquery-1.11.3.min.js"></script>

<script src="http://cdn.datatables.net/1.10.8/js/jquery.dataTables.min.js"></script>

<script type="text/javascript"> 
$(document).ready( function() {
  $('.table').dataTable( {
    "iDisplayLength": 25
	//"bSort": false
  } );
} )
</script>

    <?php
if(isset($_POST['datePicker'])) {

$mysqli = new mysqli("127.0.0.1", "root", "livegoals", "livegoals");
if (!$mysqli) {
    echo "Error: Unable to connect to MySQL." . PHP_EOL;
    echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
    echo "Debugging error: " . mysqli_connect_error() . PHP_EOL;
    exit;
}

	$date1 = $selected1.'00:00:00';
	$date2 =  $selected2. '23:59:59';
	
$sql    = "SELECT DISTINCT bet365_id,fixture from scan_games  where last_updated > '".$date1."' AND last_updated < '".$date2."'";
$result = $mysqli->query($sql);

if(isset($_GET['delete'])) {
	
	
					$sql   = "update games set live = '0'";
					$stmt1 = $mysqli->prepare( "DELETE from scan_games where bet365_id = ?" );
					$stmt1->bind_param( "s", $_GET['delete'] );
					$stmt1->execute();
					
					echo "Deleted";
	
}

if ($result->num_rows > 0) {
    // output data of each row
    
    $success = 0;
    $total   = 0;
    $t = 0;
    $oddstotal = 0;
    
    $gamecount = 0;
    while ($row = $result->fetch_assoc()) {
    
    $gamecount++;
        
       echo "<h2>" . $row['fixture'] . " ".$row['last_updated']." <a href=\"?delete=".$row['bet365_id']."\">[x]</a> </h2><br />";
       ?>
	   
	<?php
	

        $sql   = "SELECT * from scan_games where bet365_id = '" . $row['bet365_id'] . "' ORDER BY last_updated ASC";
        $data  = $mysqli->query($sql);
        $goals = 0;
        $odds  = array();
        $i     = 0;
        
  
       	$totalrows = $data->num_rows;
		
		if($totalrows)
        while ($row = $data->fetch_assoc()) {
			
			if(strtotime($row['last_updated']) < ( time() - (10800) ) && $totalrows < 7) {

				
					$sql   = "update games set live = '0'";
					$stmt1 = $mysqli->prepare( "DELETE from scan_games where bet365_id = ?" );
					$stmt1->bind_param( "s", $row['bet365_id'] );
					$stmt1->execute();
					
					echo "Deleted";

			}
     ?>
	 
	 
	
							<?php echo " <b>Time: </b> ".$row['TIME']." <b>Score:</b> ".$row['home_goals']." - ". $row['away_goals']; ?>  <?php echo " <b>Next Goal Odds:</b> ".$row['odds']; ?>  <?php echo " <b>Odds:</b> (".$row['odds_homewin'].") / (".$row['odds_draw'].") / (".$row['odds_awaywin']; ?>) Home Shots ON: <?php echo $row['home_shotson']; ?> Away Shots ON: <?php echo $row['away_shotson']." <br />";
							

 }

echo "Total: ". $totalrows;
?>

  <hr> <br /> 
  
  <?php
  
  
  echo "Total Games:".$gamecount;

}

}
}
?>
<style type="text/css">
    .bs-example{
    	margin: 20px;
    }
</style>
<div class="bs-example">
    <form>
        <div class="form-group">
            <label for="inputEmail">Email</label>
            <input type="email" class="form-control" id="inputEmail" placeholder="Email">
        </div>
        <div class="form-group">
            <label for="inputPassword">Password</label>
            <input type="password" class="form-control" id="inputPassword" placeholder="Password">
        </div>
        <div class="checkbox">
            <label><input type="checkbox"> Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
</div>