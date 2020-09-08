<?php

// Mark all entry pages with this definition. Includes need check check if this is defined
// and stop processing if called direct for security reasons.
define('ESRC', TRUE);

include_once '../includes/auth-inc.php';
include_once '../class/users.class.php';
require_once '../class/db.class.php';
require_once '../class/systems.class.php';
require_once '../class/storms.class.php';

$database = new Database();
$storms = new Storms($database);
$systems = new Systems($database);
$users = new Users($database);


// check if the user is alliance member
if (!Users::isAllianceUserSession())
{
	// check if last login was already a non auth user
	if (isset($_SESSION['AUTH_NOALLIANCE']))
	{
		// set redirect to root path
		$_redirect_uri = Config::ROOT_PATH;
	}
	else
	{
		// set redirect to requested path
		$_redirect_uri = $_SERVER['REQUEST_URI'];
	}

	// void the session entries on 'attack'
	session_unset();
	// save the redirect URL to current page
	$_SESSION['auth_redirect']=$_redirect_uri;
	// set a flag for alliance user failure
	$_SESSION['AUTH_NOALLIANCE'] = 1;
	// no, redirect to home page
	header("Location: ".Config::ROOT_PATH."auth/login.php");
	// stop processing
	exit;
}

// check for SAR Coordinator login
$isCoord = ($users->isSARCoordinator($charname) || $users->isAdmin($charname));

// handle form submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['formtype']) &&  $_POST['formtype'] == 'delrow') {
        $storms->removeStormEntry($_POST['rowid']);
        $errmsg = 'Storm report removed from database.';
    }
    elseif (empty($_POST['evesystem']) || empty($_POST['stormtype'])) { // validate form
        $errmsg = 'Please select a storm center system and storm type.';
    }

    if (empty($errmsg)) {
        $newStormEntry = $storms->createStormEntry($_POST['evesystem'], $_POST['pilot'], 
            $_POST['stormtype'], 'Strong');
        
        if ($newStormEntry) {   // Broadcast any new storm to Discord	
            $webHook = 'https://discordapp.com/api/webhooks/' . Config::DISCORDEXPLO;
            $user = 'Storm Tracker';
            $alert = 0;
            $message = "_New storm report from " . $_POST['pilot'] . "_\n" 
                . ' Strong Metaliminal '. $_POST['stormtype'] .' Ray Storm in '. $_POST['evesystem'];
            $skip_the_gif = 1;

            $result = Discord::sendMessage($webHook, $user, $alert, $message, $skip_the_gif);
        }
        else {  // new storm report could not be created
            $errmsg = 'Storm report could not be created. Make sure it is not a duplicate.';
        }
    }
}

?>
<html>

<head>
	<?php
	$pgtitle = 'Storm Tracker';
	include_once '../includes/head.php';
    ?>
</head>

<?php

$database = new Database();

if (!isset($charname))
{
	// no, set a dummy char name
	$charname = 'charname_not_set';
}

if(isset($_REQUEST['errmsg'])) { $errmsg = $_REQUEST['errmsg']; }


?>
<body class="white" style="background-color: black;">
<div class="container">

    <div class="row" id="header" style="padding-top: 20px;">
        <?php include_once '../includes/top-right.php'; ?>
        <?php include_once '../includes/top-left.php'; ?>
        <?php //include_once 'top-middle.php'; ?>

    </div>
    <div class="ws"></div>

    <!-- NAVIGATION TABS -->
    <?php include_once 'navtabs.php'; ?>

    <div class="ws"></div>

<?php
// display error message if there is one
if (!empty($errmsg)) {
?>
	<div class="row" id="errormessage" style="background-color: #ff9999;" >
		<div class="col-md-12 message">
			<?php echo nl2br($errmsg); ?>
		</div>
	</div>
	<div class="ws"></div>
<?php
}
?>

<style>
.col-md-4 {
  float: none;
  margin: 0 auto;
}
</style>

<div class="row">
    <div class="col-md-7">
        <h2>Storm Tracker is currently in early beta testing</h2>
        <p>Dear Signaleer, thank you for being out there chasing storms!</p>
        <p><strong>Remember: We only need the centre of the storm reported.</strong> You can find 
        it easily from your in-game map, as shown to the right. Since the centre is always of the STRONG 
        variety, that will automatically be added to your report.</p>
        <p>Thank you for your report! o7</p>

        <div class="ws"></div>

        <div class="row">
            <div class="col-md-4">
        <form name="stormform" id="stormform" class="form-horizontal" 
            action="<?=htmlentities($_SERVER['PHP_SELF'])?>" method="POST">
            <div class="form-group">
                <select class="form-control" id="evesystem" name="evesystem" style="width: auto;">
                    <option value="">Choose the storm center...</option>

                    <?php
                    require_once '../resources/stormSystems.php';
                    foreach ($stormSystems as $value) {
                        echo Output::prepSelectListOption($value['solarSystemName'], 
                        $value['solarSystemName']);
                    }
                    ?>

                </select>
            </div>
            <div class="form-group">
                <select id="stormtype" name="stormtype" class="form-control" style="width: auto;">
                    <option value="">Choose the storm type...</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Exotic">Exotic</option>
                    <option value="Gamma">Gamma</option>
                    <option value="Plasma">Plasma</option>
                </select>
            </div>
            <div class="form-group text-center">
                <input type="hidden" name="pilot" id="pilot" value="<?=$charname?>">
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
            </div></div>
    </div>
    <div class="col-md-5">
        <img src="https://cdn.discordapp.com/attachments/551081375424446483/749915372471451720/unknown.png">
    </div>
</div>
<div class="ws"></div>

<hr class="white">

<div class="row" id="systable">
	<div class="col-sm-12">
        <p class="white">Storms (most recent report first)</p>
		<table class="table display" style="width: auto;">
			<thead>
				<tr>
                    <th class="white">System</th>
                    <th class="white">Type</th>
					<th class="white">Pilot</th>
                    <th class="white">Report Date</th>
                    <?php if ($isCoord) { echo '<th class="white">&nbsp;</th>'; } ?>
				</tr>
			</thead>
			<tbody>
			
			<?php
            $rows = $storms->getStorms();
            if (empty($rows)) {
                echo '<td colspan="5" class="white">No data available</td>';
            }
            else {
                $i = 0;
			    foreach ($rows as $value) {	?>
				
				<tr>
                    <td class="white text-nowrap"><?=$value['evesystem']?></td>
                    <td class="white text-nowrap"><?=$value['stormtype']?></td>
                    <td class="white text-nowrap"><?=$value['pilot']?></td>
                    <td class="white text-nowrap"><?=$value['dateobserved']?></td>
                    
                    <?php 
                    if ($isCoord) { // ESR Coords have option to delete entry   ?>

                    <td>
                        <form method="post" id="delform<?=$i?>" class="form-inline"
                            style="margin-bottom:-2px !important;"
                            action="<?=htmlentities($_SERVER['PHP_SELF'])?>">
                            <input type="hidden" name="formtype" value="delrow">
                            <input type="hidden" name="rowid" value="<?=$value["id"]?>">
                            <button type="submit" class="btn btn-xs btn-danger">
                                <i class="fa fa-trash-o" aria-hidden="true"></i></button>
                        </form>
                    </td>

                    <?php
                    }   ?>

				</tr>

                    <?php
                    $i++;
                }
			}	?>

			</tbody>
		</table>
    </div>
</div>

</body>
</html>
