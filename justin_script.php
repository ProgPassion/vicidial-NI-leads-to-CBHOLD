<?php

	$date = date("Y-m-d");
  
  	//assuming that the cron user credencials are like this
	$conn = new mysqli("localhost", "cron", "1234", "asterisk");

	if($conn->connect_errno) {
		die("Failed to connect to MySQL: " . $conn->connect_error);
	}


	setNILeadsToCallBack($date);

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



	function queryExec($query) {

		global $conn;

		$result = $conn->query($query);
		if(!$result) {
			die($conn->error);
		}

		return $result;
	}


	function setDateAfterFourMonths($lead_date) {

		// This function takes the lead datetime and returns it with 120 days (4 months) plus value 

		$date_without_time = substr($lead_date, 0, strpos($lead_date, " "));
		$time_from_date    = substr($lead_date, strpos($lead_date, " ") + 1);
		$lead_date_array   = explode("-", $date_without_time);
		
		$lead_year  = $lead_date_array[0];
		$lead_month = $lead_date_array[1];
		$lead_day   = $lead_date_array[2];
		
		if(date("l", mktime(0, 0, 0, $lead_month, $lead_day, $lead_year) + (86400 * 120)) == "Saturday") { 
			//Add plus 2 days to put the callback for the Monday if rescheduling day happens to be Saturday
			return date("Y-m-d", mktime(0, 0, 0, $lead_month, $lead_day, $lead_year) + (86400 * 122)) . " " . $time_from_date; 
		}
		elseif(date("l", mktime(0, 0, 0, $lead_month, $lead_day, $lead_year) + (86400 * 120)) == "Sunday") {
			//Add plus 1 day to put the callback for the Monday if rescheduling day happens to be Sunday
			return date("Y-m-d", mktime(0, 0, 0, $lead_month, $lead_day, $lead_year) + (86400 * 121)) . " " . $time_from_date;
		}
		else {
			return date("Y-m-d", mktime(0, 0, 0, $lead_month, $lead_day, $lead_year) + (86400 * 120)) . " " . $time_from_date;
		}
				
	}


	function setNILeadsToCallBack($date) {

		$result = queryExec("SELECT lead_id, list_id, user, modify_date FROM vicidial_list WHERE status='NI' AND modify_date LIKE'$date%'");
		
		while($row = $result->fetch_array(MYSQLI_ASSOC)) {
			$lead_id = $row['lead_id'];
			$list_id = $row['list_id'];
			$user    = $row['user'];
			$modify_date = $row['modify_date'];

			insertCallback($lead_id, $list_id, $modify_date, $user);
			updateLeadStatusCBHOLD($lead_id, $user); // UPDATE status of this lead on vicidial_list TABLE
		}
	}

	function insertCallback($lead_id, $list_id, $modify_date, $user) {

		$campaign_id   = getListCampaignId($list_id);
		$status        = "ACTIVE";
		$entry_time    = $modify_date;
		$callback_time = setDateAfterFourMonths($modify_date);
		$recipient     = "USERONLY";
		$user_group    = getUserGroup($user);
		$lead_status   = "CALLBK";

		queryExec("INSERT INTO vicidial_callbacks(lead_id, list_id, campaign_id, status, entry_time, callback_time, modify_date, user, recipient, user_group, lead_status) VALUES('$lead_id', '$list_id', '$campaign_id', '$status', '$entry_time', '$callback_time', '$modify_date', '$user', '$recipient', '$user_group', '$lead_status')");
	}

	function getUserGroup($user) {

		$result = queryExec("SELECT user_group FROM vicidial_users WHERE user='$user'");
		$user = $result->fetch_array(MYSQLI_ASSOC);

		return $user["user_group"];
	}

	function getListCampaignId($list_id) {

		$result = queryExec("SELECT campaign_id FROM vicidial_lists WHERE list_id='$list_id'");
		$campaign_id = $result->fetch_array(MYSQLI_ASSOC);

		return $campaign_id['campaign_id'];
	}

	function updateLeadStatusCBHOLD($lead_id, $user) {
		queryExec("UPDATE vicidial_list SET status='CBHOLD', user='$user' WHERE lead_id='$lead_id'");
	}

?>
