<?php
	############################################################################
	## Initialize script and assert session validity						  ##
	############################################################################

	ini_set('display_errors', 'On');

	include_once '../../model/admin/Clocker_Admin.php';
	include_once '../../session.php';

	session_start();

	assert_session($type = 'administrators');
	assert_container($container = $admin_url['editor']);

	############################################################################



	############################################################################
	## Set variables and error codes										  ##
	############################################################################

	$db = new Clock_Admin();

	$aid		= isset($_POST['aid'])			? $_POST['aid']			: NULL;
	$name		= isset($_POST['name'])			? $_POST['name']		: NULL;
	$time		= isset($_POST['time'])			? $_POST['time']		: NULL;
	$action		= isset($_POST['action'])		? $_POST['action']		: NULL;
	# Number of new values to be added
	$adds		= isset($_POST['adds'])			? $_POST['adds']		: NULL;
	# New values to be added; stores timestamps
	$addition	= isset($_POST['addition'])		? $_POST['addition']	: NULL;

	$adds		= sizeof($addition);
	$size		= sizeof($time);
	$in			= $size % 2 == 1 ? true : false;

	$error1		= "Fatal error: data posted for non-employee. Update aborted.";
	$error2		= "Fatal error: encountered erroneous timestamp format. Update aborted.";
	$error3		= "Fatal error: encountered negative work period. Update aborted.";
	$error4		= "Fatal error: encountered overlapping work periods. Update aborted.";
	$error5		= "Fatal error: single action additions must have latest timestamp. Update aborted.";

	############################################################################



	############################################################################
	## Validate posted data													  ##
	############################################################################

	# If this assert fails, shenanigans are afoot
	isset($_POST['name']) or die('stop trying to break shit');

	# Ensure posted netid is an employee
	$db->assert_employee($name) or die($error1);
	# Ensure no negative periods are entered
	$db->assert_positive($time) or die($error3);
	# Ensure no periods overlap
	$db->assert_periods($time, $time) or die($error4);
	# Validate datetimes
	if($time) $db->assert_timestamp($time) or die($error2);
	if($time) $db->assert_datetime($time) or die($error2);
	if($adds) $db->assert_timestamp($addition) or die($error2);
	if($adds) $db->assert_datetime($addition) or die($error2);
	# If adding new action, ensure its action time is the latest action time
	if($adds == 1) $db->assert_latest($time, $addition[0]) or die($error5);
	# If employee is clocked in, ensure that it's the latest action
	if($in) $db->assert_latest($time, $time[$size - 1]) or die($error5);
	# Ensure newly added period is not negative
	if($adds == 2) $db->assert_positive($addition) or die($error3);
	# If adding new period, ensure it does not overlap with any other period
	if($adds == 2) $db->assert_periods($time, $addition) or die($error4);
	# If adding new period and user is clocked in, ensure new period's time is less than current clock in action time
	if($adds == 2 && $in) $db->assert_latest($addition, $time[$size - 1]) or die($error3);

	############################################################################



	############################################################################
	## Update database to reflect posted data								  ##
	############################################################################

	# Update existing entries
	for($i = 0; $i < $size; $i++)
	{
		$current = mysqli_fetch_array($db->query("SELECT * FROM $db->table WHERE aid = " . $aid[$i]));

		if($current['time'] != $time[$i])
			$db->update_entry($aid[$i], $name, $time[$i], $action[$i]);
	}

	$ac = $in ? 'clock_out' : 'clock_in';	

	# Insert new entries
	if($adds == 2)
	{
		$db->add_entry($name, $addition[0], 'clock_in');
		$db->add_entry($name, $addition[1], 'clock_out');
	}
	else if($adds == 1)
	{
		$db->add_entry($name, $addition[0], $ac);
	}

	############################################################################



	############################################################################
	## Redraw editor, commit and close database								  ##
	############################################################################

	# Table is redrawn in respect to currently selected user
	echo $db->draw_editor($name);

	$db->commit();
	$db->close();

	exit();

	############################################################################
?>
