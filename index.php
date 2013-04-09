<?php

	session_start();

	$your_domain = "yourdomain.com";
	$reseller_api_url = "https://www.activecampaign.com";
	$reseller_api_key = "";
	$path_to_api_wrapper = "../../activecampaign-api-php/includes";

	function dbg($var, $continue = 0, $element = "pre")
	{
	  echo "<" . $element . ">";
	  echo "Vartype: " . gettype($var) . "\n";
	  if ( is_array($var) )
	  {
	  	echo "Elements: " . count($var) . "\n\n";
	  }
	  elseif ( is_string($var) )
	  {
			echo "Length: " . strlen($var) . "\n\n";
	  }
	  print_r($var);
	  echo "</" . $element . ">";
		if (!$continue) exit();
	}

?>

<style type="text/css">

	body {
		font-family: Arial;
		font-size: 12px;
		margin: 30px;
	}

</style>

<?php

	if (!$reseller_api_key) {
		echo "<span style=\"color: red;\">Please put your reseller API Key into the script (at the top for the <code>\$reseller_api_key</code> variable).</span>";
		exit();
	}

	if (!file_exists($path_to_api_wrapper)) {
		echo "<span style=\"color: red;\">Please put the correct relative path to the <a href=\"https://github.com/ActiveCampaign/activecampaign-api-php\">ActiveCampaign PHP wrapper</a> class files into the script (at the top for the <code>\$path_to_api_wrapper</code> variable).</span>";
		exit();
	}

	$api_url = (isset($_SESSION["account_api_url"]) && $_SESSION["account_api_url"]) ? $_SESSION["account_api_url"] : $reseller_api_url;
	$api_key = (isset($_SESSION["account_api_key"]) && $_SESSION["account_api_key"]) ? $_SESSION["account_api_key"] : $reseller_api_key;

	define("ACTIVECAMPAIGN_URL", $api_url);
	define("ACTIVECAMPAIGN_API_KEY", $api_key);

	require_once($path_to_api_wrapper . "/ActiveCampaign.class.php");
	$ac = new ActiveCampaign(ACTIVECAMPAIGN_URL, ACTIVECAMPAIGN_API_KEY);

	$alert = "";
	$step = ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["step"]) && (int)$_GET["step"]) ? (int)$_GET["step"] : 1;

	if ($_SERVER["REQUEST_METHOD"] == "POST") {

		if ($_POST["submit"] == "Reset") {
			unset($_SESSION["account_api_url"]);
			unset($_SESSION["account_api_key"]);
		}
		else {

			$form_step = $step = (int)$_POST["step"];

			if ($form_step == 1) {

				$account_name = $_POST["account_name"];
				$client_name = $_POST["client_name"];
				$client_email = $_POST["client_email"];
				$plan = $_POST["plan"];
				//$plan = "100"; // forcing credit-based so we are not charged on creation

				// check if account name already exists
				$name_exists = $ac->api("account/name_check?account={$account_name}");

				if (!(int)$name_exists->success) {
					$alert = $name_exists->error;
				}
				else {

					// name is available. proceed with adding account.

					$_SESSION["account_name"] = $account_name;
					$_SESSION["client_name"] = $client_name;

					// add account
					$account = get_object_vars(json_decode('{
					  "account": "' . $account_name . '",
					  "cname": "' . $account_name . '.' . $your_domain . '",
					  "name": "' . $client_name . '",
					  "email": "' . $client_email . '",
					  "notification": "' . $client_email . '",
					  "plan": "' . $plan . '"
					}'));

					$account = $ac->api("account/add", $account);

					if (!(int)$account->success) {
						$alert = $name_exists->error;
					}
					else {

						// account added successfully.
						// check account status
						$account_status = $ac->api("account/status?account={$account_name}");
//dbg($account_status);

						while ($account_status->status == "creating") {
							echo "<p style=\"background-color: yellow;\">Account still being created... (waiting 15 seconds before checking again)</p>";
						  sleep(15);
						  $account_status = $ac->api("account/status?account={$account_name}");
						}

						if ($account_status->status != "active") {
							$alert = "Account Status is " . $account_status->status;
						}
						else {

							$_SESSION["account_admin_password"] = $account->password;

							// obtain this account's API URL and Key
							$ac2 = new ActiveCampaign("https://{$account_name}.activehosted.com", null, "admin", $account->password);
							$user_me = $ac2->api("user/me");

							// save account-specific API connection info to session
							$_SESSION["account_api_url"] = $user_me->apiurl;
							$_SESSION["account_api_key"] = $user_me->apikey;

						}

					}

				}

			}
			elseif ($form_step == 2) {

				$name = $_POST["template_name"];
				$html = $_POST["template_html"];

				$template = array(
					"name" => $name,
					"html" => htmlspecialchars($html),
					"template_scope" => "all",
				);

				$template = $ac->api("message/template_add", $template);

				if (!(int)$template->success) {
					$alert = $template->error;
				}
				else {

					$template_edit_link = "http://" . $_SESSION["account_name"] . ".{$your_domain}/admin/main.php?action=template#form-{$template->id}";

					// get SSO link
					//$username = $_SESSION["username"];
					$username = "admin";
					$ip = $_SERVER["REMOTE_ADDR"];
					if ($ip == "::1") $ip = "127.0.0.1";

					$sso = $ac->api("auth/singlesignon?sso_user={$username}&sso_addr={$ip}&sso_duration=20");

					if (!(int)$sso->success) {
						$alert = $sso->error;
					}
					else {

						$ui_url = "http://" . $_SESSION["account_name"] . ".{$your_domain}/admin";
						$sso_url = "http://" . $_SESSION["account_name"] . ".{$your_domain}/admin/main.php?_ssot={$sso->token}";

					}

				}

			}
			elseif ($step == 101) {

				$_SESSION["account_list_search"] = $search = $_POST["account_filter"];
				$_SESSION["account_list"] = $account_list = $ac->api("account/list?search={$search}");
//dbg($account_list);

			}
			elseif ($step == 102) {

				$account_edit = $cancel_results = array();

				if (isset($_POST["edit"]) && isset($_POST["edit"][0])) {

					$account_edit = $_POST["edit"][0];

					// we already have the accounts in the session, so just look for a match
					foreach ($_SESSION["account_list"]->accounts as $account) {

						if ($account->account == $account_edit) {

							// set var for HTML form
							$account_edit = get_object_vars($account);

							// so we know if they changed this value on the next submit
							$_SESSION["account_edit_reseller_status"] = $account_edit["reseller_status"];

						}

					}

				}

				if (isset($_POST["cancels"])) {

					$cancels = $_POST["cancels"];

					$cancel_results = array();

					foreach ($cancels as $account_name) {

						$cancel = $ac->api("account/cancel?account={$account_name}&reason=testing");

						if (!(int)$cancel->success) {
							//$alert = $cancel->error;
							$cancel_results[$account_name] = $cancel->error;
						}
						else {
							$cancel_results[$account_name] = $cancel->message;
						}

						sleep(10);

					}

				}

			}
			elseif ($step == 103) {

				// editing account

				$subdomain = $_POST["account_edit_subdomain"];
				$fulldomain = $subdomain . ".activehosted.com";
				$cname = $_POST["account_edit_cname"];
				$notification = $_POST["account_edit_notification"];
				$plan = $_POST["account_edit_plan"];
				$reseller_status = (int)$_POST["account_edit_reseller_status"];
				$reseller_status_message = $_POST["account_edit_reseller_status_message"];

				$account = array(
					"notification" => $notification,
					"account" => $fulldomain,
					"cname" => $cname,
					"plan" => $plan,
				);

				$edit = $ac->api("account/edit", $account);
//dbg($edit);

				if (!(int)$edit->success) {
					$alert = $template->error;
				}
				else {

					// saved the main information fine.

					// now try to update the reseller status
					if ($reseller_status != (int)$_SESSION["account_edit_reseller_status"]) {
						// if they changed the value, then update it
						$status_set = $ac->api("account/status_set?account=" . $fulldomain . "&status={$reseller_status}&message=" . urlencode($reseller_status_message));

						if (!(int)$status_set->success) {
							$alert = $status_set->error;
						}
					}

					$search = $_SESSION["account_list_search"];
					$_SESSION["account_list"] = $ac->api("account/list?search={$search}");
					$step = 101;
				}

			}

			if (!$alert) $step++;

		}

	}

	if ($step == 1 || $step == 103) {
		// get plans
		$plans = $ac->api("account/plans");
	}

?>

<div style="color: red; font-weight: bold; margin: 20px 0;<?php if (!$alert) echo " display: none;"; ?>"><?php echo $alert; ?></div>

<form method="get">
	Process:
	<select name="step" onchange="this.form.submit();">
		<option value="1"<?php if ($step >= 1 && $step <= 100) echo " selected=\"selected\""; ?>>Add Account</option>
		<option value="101"<?php if ($step >= 101 && $step <= 200) echo " selected=\"selected\""; ?>>View/Edit/Cancel Accounts</option>
	</select>
</form>

<form method="POST">

	<input type="hidden" name="step" value="<?php echo $step; ?>" />

	<?php

		if ($step == 1) {

			?>

			<h1>Add Account</h1>

			<h2>Account Name</h2>
			<input type="text" name="account_name" size="30" /><code>.<?php echo $your_domain; ?></code>

			<h2>Client Name</h2>
			<input type="text" name="client_name" size="30" />

			<h2>Client Email</h2>
			<input type="text" name="client_email" size="30" />

			<h2>Plan</h2>
			<select name="plan">
				<?php

					foreach ($plans->plans as $plan) {
						?>

						<option value="<?php echo $plan->id; ?>"><?php echo $plan->limit_sub; ?> Subscribers (Plan ID: <?php echo $plan->id; ?>)</option>

						<?php
					}

				?>
			</select>

			<?php

		}
		elseif ($step == 2) {

			?>

			<h1>Add Email Template</h1>

			<h2>Internal Name</h2>
			<input type="text" name="template_name" size="30" />

			<h2>HTML</h2>
			<textarea name="template_html">&lt;html&gt;
  &lt;head&gt;
    &lt;title&gt;Template added via API&lt;/title&gt;
  &lt;/head&gt;

  &lt;body&gt;

    &lt;p&gt;This template was edited via the API&lt;/p&gt;

  &lt;/body&gt;

&lt;/html&gt;</textarea>

			<?php

		}
		elseif ($step == 3) {

			?>

			<h1>User Log-In</h1>

			<p><b>User:</b> <code>admin</code></p>
			<p><b>Password:</b> <code><?php echo $_SESSION["account_admin_password"]; ?></code></p>
			<p><a href="<?php echo $sso_url; ?>" target="_blank">Log-in as user automatically</a>.</p>

			<h1>Sample Email Text</h1>

			<p>Hi, <?php echo $_SESSION["client_name"]; ?>!</p>
			<p>We have successfully set up your account and you can log-in right now at this URL:</p>
			<p><a href="<?php echo $ui_url; ?>"><?php echo $ui_url; ?></a></p>
			<p>Your username is <code>admin</code>, and your password is <code><?php echo $_SESSION["account_admin_password"]; ?></code>.</p>
			<p>You can also automatically log-in by <a href="<?php echo $sso_url; ?>">clicking this link</a>!</p>
			<p>We have created a default template for you, which can be <a href="<?php echo $template_edit_link; ?>">edited here</a>. This template can be used when creating new campaigns.</p>

			<?php

		}
		elseif ($step == 101) {

			?>

			<h1>View/Edit/Cancel Accounts</h1>

			<h2>Search Phrase (Optional)</h2>
			<input type="text" name="account_filter" size="30" />

			<?php

		}
		elseif ($step == 102) {

			?>

			<h2>Accounts</h2>

			<table border="1" cellspacing="0" cellpadding="3">

				<tr>
					<th>ID</th>
					<th>Account</th>
					<th>CNAME</th>
					<th>Plan ID</th>
					<th>Client Name</th>
					<th>Client Email</th>
					<th>Reseller Status</th>
					<th>Edit?</th>
					<th>Cancel?</th>
				</tr>

				<?php

					foreach ($_SESSION["account_list"]->accounts as $account) {

						$reseller_status_word = ((int)$account->reseller_status == 0) ? "Active" : "Inactive";

						?>

						<tr>
							<td><?php //echo $account->id; ?></td>
							<td><?php echo $account->account; ?></td>
							<td><?php echo $account->cname; ?></td>
							<td><?php echo $account->planid; ?></td>
							<td><?php echo $account->client_name; ?></td>
							<td><?php //echo $account->client_email; ?></td>
							<td><?php echo $reseller_status_word; ?></td>
							<td><input type="radio" name="edit[]" value="<?php echo $account->account; ?>" /></td>
							<td><input type="checkbox" name="cancels[]" value="<?php echo $account->account; ?>" /></td>
						</tr>

						<?php

					}

				?>

			</table>

			<?php

		}
		elseif ($step == 103) {

			?>

			<h2>
				Edit Account
				<?php

					if ($account_edit) {
						echo "(" . $account_edit["account"] . ")";
					}

				?>
			</h2>

			<?php

				if ($account_edit) {

					// grab just the sub-domain name (IE: myaccount.activehosted.com grabs just "myaccount")
					$account_subdomain_preg = preg_match("/^[^\.]*/", $account_edit["account"], $account_subdomain);
					//$reseller_status_word = ((int)$account_edit["reseller_status"] == 0) ? "Active" : "Inactive";

					?>

					<h3>Account Subdomain</h3>
					<input type="text" name="account_edit_subdomain" value="<?php echo $account_subdomain[0]; ?>" size="30" />

					<h3>CNAME</h3>
					<input type="text" name="account_edit_cname" value="<?php echo $account_edit["cname"]; ?>" size="30" />

					<!--
					<h3>Notification Email</h3>
					<input type="text" name="account_edit_notification" value="<?php //echo $account_edit["client_email"]; ?>" size="30" />
					-->

					<h3>Plan</h3>
					<select name="account_edit_plan">
						<?php

							foreach ($plans->plans as $plan) {
								?>

								<option value="<?php echo $plan->id; ?>"<?php if ((int)$plan->id == (int)$account_edit["planid"]) echo " selected=\"selected\""; ?>><?php echo $plan->limit_sub; ?> Subscribers (Plan ID: <?php echo $plan->id; ?>)</option>

								<?php
							}

						?>
					</select>

					<h3>Reseller Status</h3>
					<select name="account_edit_reseller_status">
						<option value="0"<?php if ((int)$account_edit["reseller_status"] == 0) echo " selected=\"selected\""; ?>>Active</option>
						<option value="1"<?php if ((int)$account_edit["reseller_status"] == 1) echo " selected=\"selected\""; ?>>Inactive</option>
					</select>

					<p><input type="text" name="account_edit_reseller_status_message" value="" size="60" placeholder="If Inactive, provide optional message" /></p>

					<?php

				}
				else {

					?>

					<p>No edits submitted.</p>

					<?php

				}

			?>

			<h2 style="margin-top: 30px;">Cancellation Results</h2>

			<?php

			if ($cancel_results) {

				foreach ($cancel_results as $account_name => $api_result) {

					?>

					<p><b><?php echo $account_name; ?>:</b> <?php echo $api_result; ?></p>

					<?php
				}

			}
			else {

				?>

				<p>No cancellations submitted.</p>

				<?php

			}

		}

	?>

	<p style="<?php if ($step == 3) echo "display: none; "; ?>margin-top: 30px;"><input type="submit" name="submit" value="Submit" style="background-color: green; color: white;" /></p>

	<p style="<?php if ($step == 99999) echo "display: none; "; ?>float: right;"><input type="submit" name="submit" value="Reset" style="background-color: red;" /></p>

</form>