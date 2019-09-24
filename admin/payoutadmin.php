<?php 
// Mark all entry pages with this definition. Includes need check check if this is defined
// and stop processing if called direct for security reasons.
define('ESRC', TRUE);

// for debug only
/*
 error_reporting(E_ALL);
 ini_set('display_errors', 'on');
*/

require_once '../class/db.class.php';
include_once '../includes/auth-inc.php';
require_once '../class/output.class.php';

// if start and end dates are not set, set them to default values
if (!isset($_REQUEST['start'])) {
	$start = gmdate('Y-m-d', strtotime("- 7 day"));
	$startPD = gmdate('Y-M-d', strtotime("- 7 day")); // formatted for Pikaday widget
}
if (!isset($_REQUEST['end'])) {
	$end = gmdate('Y-m-d', strtotime("now"));
	$endPD = gmdate('Y-M-d', strtotime("now")); // formatted for Pikaday widget
}

// set start and end dates to submitted values (GET or POST)
if (isset($_REQUEST['start']) && isset($_REQUEST['end'])) {
	// start date
	$arrStart = explode('-', $_REQUEST['start']);
	$startYear = intval(substr($arrStart[0], -3)) + 1898;
	$startMonth = intval(date('m', strtotime($arrStart[1])));
	$startDay = intval($arrStart[2]);
	$start = gmdate('Y-m-d', strtotime($startYear. '-' . $startMonth. '-' . $startDay));
	
	// end date
	$arrEnd = explode('-', $_REQUEST['end']);
	$endYear = intval(substr($arrEnd[0], -3)) + 1898;
	$endMonth = intval(date('m', strtotime($arrEnd[1])));
	$endDay = intval($arrEnd[2]);
	$end = gmdate('Y-m-d', strtotime($endYear. '-' . $endMonth. '-' . $endDay));
	
	// special string for Pikaday widget
	$startPD = htmlspecialchars_decode(date("Y-M-d", strtotime($startYear. '-' . $startMonth. '-' . $startDay)));
	$endPD = htmlspecialchars_decode(date("Y-M-d", strtotime($endYear. '-' . $endMonth. '-' . $endDay)));
}

?>
<html>

<head>
	<?php
	$pgtitle = 'Payout Admin';
	include_once '../includes/head.php'; 
	?>
	<style>
	<!--
		table {
			table-layout: fixed;
			word-wrap: break-word;
		}
		a,
		a:visited,
		a:hover {
			color: aqua;
		}
	-->
	</style>
	<script type="text/javascript">
		$(document).ready(function() {
		    $('#example').DataTable( {
		        "order": [[ 0, "desc" ]],
		        "pagingType": "full_numbers",
		        "pageLength": 15
		    } );
		} );
	</script>
</head>

<body>
<div class="container">
	<div class="row" id="header" style="padding-top: 10px;">
		<?php include_once '../includes/top-left.php'; ?>
		<div class="col-sm-8" style="text-align: center; height: 100px; vertical-align: middle;">
			<span style="font-size: 125%; font-weight: bold; color: white;">Payouts: 
				ESRC &gt;&gt; 
				<a href="payoutadmin_sar.php">SAR Dispatch</a> &gt;&gt; 
				<a href="payoutadmin_sar_rescue.php">SAR Locate/Rescue</a></span>
			<span class="pull-right"><a class="btn btn-danger btn-md" href="index.php" role="button">
				Admin Index</a></span><br />
			<form method="post" class="form-inline" action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">
				<div class="input-daterange input-group" id="datepicker" style="margin-bottom: 5px;">
					<input type="text" class="input-sm form-control" name="start" id="start" 
						value="<?php echo isset($startPD) ? $startPD : '' ?>" />
					<span class="input-group-addon">to</span>
					<input type="text" class="input-sm form-control" name="end" id="end" 
						value="<?php echo isset($endPD) ? $endPD : '' ?>" />
				</div>
				<div class="checkbox">
					<label class="white"><input type="checkbox" name="details" value="yes"> Payout</label>
				</div>
				&nbsp;&nbsp;&nbsp;&nbsp;<button type="submit" class="btn btn-sm">Search</button><br /><br />
				<label class="white">Total Weekly Payout: </label>
				<input type="text" name="totamt" 
					value="<?php echo (isset($_REQUEST['totamt'])) ? $_REQUEST['totamt'] : '500000000'; ?>" /> (ISK)
			</form>
		</div>
		<?php include_once '../includes/top-right.php'; ?>
	</div>
	<div class="ws"></div>
	<?php
	// display results for the selected date range
	$db = new Database();
		
	//show detailed records if "Payout" is not checked
	if (!isset($_POST['details']) && $_POST['details'] != 'yes') {	
	?>
	<div class="row" id="systable">
		<div class="col-sm-10 white">
			<table id="example" class="table display" style="width: auto;">
				<thead>
					<tr>
						<th class="white">Date</th>
						<th class="white">Pilot</th>
						<th class="white">Type</th>
						<th class="white">System</th>
						<th class="white">Aided&nbsp;Pilot</th>
						<th class="white" style="width: 35%;">Note</th>
					</tr>
				</thead>
				<tbody>
				<?php
				$ctrtotact = $ctrsow = $ctrtend = $ctradj = 0;
				$db->query("SELECT * FROM activity 
							WHERE ActivityDate BETWEEN :start AND :end 
							ORDER By ActivityDate DESC");
				$db->bind(':start', $start);
				$db->bind(':end', $end);
				$rows = $db->resultset();
				foreach ($rows as $value) {
					$ctrtotact++;
					// calculate action cell format
					$actioncellformat= '';
					switch ($value['EntryType']) {
						case 'sower':
							$actioncellformat = ' style="background-color:#ccffcc;color:black;"';
							break;
						case 'tender':
							$actioncellformat= ' style="background-color:#d1dffa;color:black;"';
							break;
						case 'agent':
							$actioncellformat= ' style="background-color:#fffacd;color:black;"';
							break;
						default:
							// ??
					}
					echo '<tr>';
					// add 4 hours to convert to UTC (EVE) for display
					echo '<td class="white text-nowrap">'. 
							date("Y-m-d H:i:s", strtotime($value['ActivityDate'])) .
						 '</td>';
					echo '<td class="text-nowrap">
							<a target="_blank" href="/esrc/personal_stats.php?pilot='. urlencode($value['Pilot']) .'">'. 
							$value['Pilot'] .'</a> - <a target="_blank" 
							href="https://evewho.com/pilot/'. $value['Pilot'] .'">EW</a></td>';
					echo '<td class="white" '. $actioncellformat .'>'. ucfirst($value['EntryType']) .'</td>';
					switch ($value['EntryType']) {
						case 'sower':
							$ctrsow++;
							break;
						case 'tender':
							$ctrtend++;
							break;
						case 'agent':
							$ctradj++;
							break;
					}
					echo '<td><a href="/esrc/search.php?sys='. $value['System'] .'" target="_blank">'. 
							$value['System'] .'</a></td>';
					echo '<td><a target="_blank" 
							href="https://evewho.com/pilot/'. $value['AidedPilot'] .'">'. 
							Output::htmlEncodeString($value['AidedPilot']) .'</td>';
					echo '<td class="white">'. Output::htmlEncodeString($value['Note']) .'</td>';
					echo '</tr>';
				}
		
				$db->query("SELECT COUNT(*) as cnt FROM cache WHERE Status <> 'Expired'");
				$row = $db->single();
				$ctrtot = $row['cnt'];
				?>
				</tbody>
			</table>
		</div>
		<div class="col-sm-2 white">
			<?php echo gmdate('Y-m-d H:i:s', strtotime("now"));?><br /><br />
			Actions this period: <?php echo $ctrtotact; ?><br />
			Sowed: <?php echo $ctrsow; ?><br />
			Tended: <?php echo $ctrtend; ?><br />
			Agent: <?php echo $ctradj; ?><br /><br />
			Total caches in space:<br />
			<?php echo $ctrtot; ?> of 2603 (<?php echo round((intval($ctrtot)/2603)*100,1); ?>%)
		</div>
	</div>
	<?php
	}
	//show payout data if "Payout" is checked
	else {	
		//count of all actions performed in the specified period
		//$db->query("SELECT COUNT(DISTINCT(System)) as cnt FROM activity WHERE ActivityDate BETWEEN :start AND :end");
		$db->query("SELECT Pilot, COUNT(DISTINCT(System)) as cnt FROM activity WHERE ActivityDate 
						BETWEEN :start AND :end GROUP BY Pilot");
		$db->bind(':start', $start);
		$db->bind(':end', $end);
		$rows = $db->resultset();
		$ctrtot = 0;
		foreach ($rows as $value) {
			$ctrtot = $ctrtot + intval($value['cnt']);
		}
		
	?>
	<div class="row" id="systable">
		<div class="col-sm-10">
			<table class="table" style="width: auto;">
				<thead>
					<tr>
						<th class="white">Pilot</th>
						<th class="white">Count</th>
						<th class="white">Payout</th>
					</tr>
				</thead>
				<tbody>
					<?php
					//summary data
					$db->query("SELECT Pilot, COUNT(DISTINCT(System)) as cnt FROM activity WHERE ActivityDate 
									BETWEEN :start AND :end GROUP BY Pilot");
					$db->bind(':start', $start);
					$db->bind(':end', $end);
					$rows = $db->resultset();
					$ctr = 0;
					foreach ($rows as $value) {
						$ctr++;
						echo '<tr>';
						echo '<td><a target="_blank" href="/esrc/personal_stats.php?pilot='. urlencode($value['Pilot']) .'">'. 
							$value['Pilot'] .'</a> - <a target="_blank" 
							href="https://evewho.com/pilot/'. $value['Pilot'] .'">EW</a></td>';
						echo '<td class="white" align="right">'. $value['cnt'] .'</td>';
						echo '<td><input type="text" id="amt'.$ctr.'" value="'. 
									round((intval($value['cnt'])/intval($ctrtot))*intval($_REQUEST['totamt']),2) .'" />
									<i id="copyclip" class="fa fa-clipboard" onClick="SelectAllCopy(\'amt'.$ctr.'\')"></i>
							  </td>';
						echo '</tr>';
					}
					?>
					<tr>
						<td class="white" align="right">Participants: <?php echo $ctr; ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;TOTAL: </td>
						<td class="white" align="right"><?php echo $ctrtot; ?></td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="col-sm-2 white">
			<?php echo gmdate('Y-m-d H:i:s', strtotime("now"));?><br /><br />
			Note that total count of actions here may differ from what is listed on non-Payout summary.
			This is because pilots are only paid for sows/tends in a given system once each week. 
			Multiple tends in the same system in the same week, e.g., do not count toward the total count for payout.
			However, they <i>do</i> count toward activity counts for medals and such.
		</div>
	</div>
<?php
	}
?>
</div>

<script type="text/javascript">
	// datepicker
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    var startDate,
    endDate,
    updateStartDate = function() {
        startPicker.setStartRange(startDate);
        endPicker.setStartRange(startDate);
        endPicker.setMinDate(startDate);
    },
    updateEndDate = function() {
        startPicker.setEndRange(endDate);
        startPicker.setMaxDate(endDate);
        endPicker.setEndRange(endDate);
    },
    startPicker = new Pikaday({
        field: document.getElementById('start'),
        minDate: new Date('03/18/2017'),
        showMonthAfterYear: true,
        format: 'YYYY-MMM-DD',
        toString(date, format) {
            const day = ("0" + date.getDate()).slice(-2);
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear() - 1898;
            return `YC${year}-${month}-${day}`;
        },
        onSelect: function() {
            startDate = this.getDate();
            updateStartDate();
        }
    }),
    endPicker = new Pikaday({
        field: document.getElementById('end'),
        minDate: new Date('03/18/2017'),
        showMonthAfterYear: true,
        format: 'YYYY-MMM-DD',
        toString(date, format) {
            const day = ("0" + date.getDate()).slice(-2);
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear() - 1898;
            return `YC${year}-${month}-${day}`;
        },
        onSelect: function() {
            endDate = this.getDate();
            updateEndDate();
        }
    }),
    _startDate = startPicker.getDate(),
    _endDate = endPicker.getDate();

    if (_startDate) {
        startDate = _startDate;
        updateStartDate();
    }

    if (_endDate) {
        endDate = _endDate;
        updateEndDate();
    }
</script>

<script type="text/javascript">
	function SelectAllCopy(id) {
	    document.getElementById(id).focus();
	    document.getElementById(id).select();
	    document.execCommand("Copy");
	}
</script>

</body>
</html>

<?php 
function debug($variable){
	if(is_array($variable)){
		echo "<pre>";
		print_r($variable);
		echo "</pre>";
		exit();
	}
	else{
		echo ($variable);
		exit();
	}
}
?>