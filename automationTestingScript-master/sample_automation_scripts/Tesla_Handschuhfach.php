<?php// updated login code
// Server-Portal-ID: 1317274 - Last modified: 21.02.2025 04:38:41 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://auth.tesla.com/';
public $invoicePageUrl = 'https://www.tesla.com/teslaaccount/payment-history';

public $username_selector = 'div.email input:not([type="hidden"]), input[name="identity"]:not([type="hidden"])';
public $password_selector = 'div.password input, input[name="credential"][type="password"]';
public $submit_login_selector = 'button.login-button, #form-submit-continue';
public $check_login_success_selector = 'li a[href*="/auth/logout"]';

public $isNoInvoice = true;
public $previously_owned = 0;
public $exclude_documents = ['_manual_', '/order-agreement/', 'ORDER_AGREEMENT', 'WARRANTY', 'owners-manua'];
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	exec("sudo docker exec -i --user root ".$this->exts->node_name." sh -c 'sudo chmod -R 777 /home/seluser/Downloads/'");
	exec("sudo docker exec -i --user root ".$this->exts->node_name." sh -c 'sudo chown -R seluser /home/seluser/Downloads/'");
	if(isset($this->exts->config_array["previously_owned"])){
		$this->previously_owned = (int)$this->exts->config_array["previously_owned"];
	} else if(isset($this->exts->config_array["PREVIOUSLY_OWNED"])){
		$this->previously_owned = (int)$this->exts->config_array["PREVIOUSLY_OWNED"];
	}
	// Load cookies
	// $this->exts->loadCookiesFromFile();
	// sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->querySelector($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		// $this->exts->openUrl('https://www.tesla.com');
		// sleep(6);
		// $this->exts->moveToElementAndClick('a[href="/teslaaccount"]');
		// sleep(10);
		$this->check_solve_human_checkbox();
		$this->checkFillLogin();
		sleep(5);

		$this->checkFillTwoFactor();
	}
	
	if ($this->exts->querySelector('div#locale-modal button.modal-close') != null) {
		$this->exts->moveToElementAndClick('div#locale-modal button.modal-close');
		sleep(2);
	}
	
	$this->processAfterLogin();
}

private function checkFillLogin() {
	$this->exts->capture("2-login-page");
	if($this->exts->exists($this->username_selector)) {
		$this->exts->log("Enter Username");
		$this->exts->click_by_xdotool($this->username_selector);
		$this->exts->type_key_by_xdotool("ctrl+a");
		$this->exts->type_key_by_xdotool("Delete");
		$this->exts->type_text_by_xdotool($this->username);

		if ($this->exts->exists('img[src="/captcha"]')) {
			$this->exts->processCaptcha('img[src="/captcha"]', 'input[name="captcha"]');
		}
		$this->exts->capture("2-username-filled");
		$this->exts->click_by_xdotool('button#form-submit-continue');
		sleep(7);
		// if($this->exts->exists($this->username_selector) && $this->exts->exists('.sign-in-form #recaptcha')) {
		// 	$this->checkFillRecaptcha();
		// 	$this->exts->click_by_xdotool('button#form-submit-continue');
		// 	sleep(5);
		// }
		$got_human_checkbox = $this->check_solve_human_checkbox();

		if($got_human_checkbox && $this->exts->exists($this->username_selector)){
			$this->exts->log("Enter Username");
			$this->exts->click_by_xdotool($this->username_selector);
			$this->exts->type_key_by_xdotool("ctrl+a");
			$this->exts->type_key_by_xdotool("Delete");
			$this->exts->type_text_by_xdotool($this->username);
			$this->exts->capture("2-username-filled");
			$this->exts->click_by_xdotool('button#form-submit-continue');
			sleep(7);
		}

		$this->exts->waitTillPresent($this->password_selector);
	}

	if($this->exts->exists($this->password_selector)) {
		$this->exts->log("Enter Password");
		$this->exts->click_by_xdotool($this->password_selector);
		$this->exts->click_by_xdotool($this->password_selector);
		sleep(2);
		$this->exts->type_key_by_xdotool("ctrl+a");
		$this->exts->type_key_by_xdotool("Delete");
		$this->exts->type_text_by_xdotool($this->password);
		sleep(1);
		if ($this->exts->exists('img[src="/captcha"]')) {
			$this->exts->processCaptcha('img[src="/captcha"]', 'input[name="captcha"]');
		}
		// $this->checkFillRecaptcha();
		$this->exts->capture("2-password-filled");
		$this->exts->moveToElementAndClick('button#form-submit-continue');
		sleep(7);
		if($this->exts->exists($this->password_selector)){
			$this->exts->waitFor(
				function (){
					return $this->exts->count_elements($this->password_selector) > 0 && 
						$this->exts->count_elements('div#h-captcha-challenge iframe[data-hcaptcha-response=""]') > 0;
				},
				22
			);
		}
		$this->exts->capture("2-password-submit-1");

		$this->check_solve_hcaptcha_challenge();
		
	} else {
			$this->exts->capture("2-password-not-found");
	}
}




// Captcha code start from here
private function check_solve_hcaptcha_challenge()
{
	$this->exts->log("Start Solving Captcha");
	$unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]'; // script selector
	$hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'; // script selector
	if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
		// Check if challenge images hasn't showed yet, Click checkbox to show images challenge
		$this->exts->log("Captcha found");
		if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
			$this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
			sleep(5);
		}

		if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // Select language English always
			$this->exts->log("Select language English always");
			$wraper_side = $this->exts->evaluate('
			window.lastMousePosition = null;
			window.addEventListener("mousemove", function(e){
				window.lastMousePosition = e.clientX +"|" + e.clientY;
			});
			var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
			coo.width + "|" + coo.height;
		');
			$evalJson = json_decode($wraper_side, true);
			$wraper_side = $evalJson['result']['result']['value'];

			$this->exts->log('Select English language ' . $wraper_side);
			$wraper_side = explode('|', $wraper_side);
			$this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 20, (int)$wraper_side[1] - 71);
			sleep(1);
			$this->exts->type_key_by_xdotool('e');
			sleep(1);
			$this->exts->type_key_by_xdotool('Return');
			sleep(2);
		}
		$this->exts->log("prcess hcaptcha start");

		$this->process_hcaptcha_by_clicking();
		$this->process_hcaptcha_by_clicking();
		sleep(5);
		if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
			$this->process_hcaptcha_by_clicking();
			$this->process_hcaptcha_by_clicking();
			$this->process_hcaptcha_by_clicking();
			$this->process_hcaptcha_by_clicking();
			sleep(5);
		}
		if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
			$this->process_hcaptcha_by_clicking();
			$this->process_hcaptcha_by_clicking();
			sleep(5);
		}
		sleep(10);
		$this->exts->capture("2-after-solving-hcaptcha");
	} else {
		$this->exts->log("Captcha Not found");
	}
}
private function process_hcaptcha_by_clicking()
{
	$unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
	$hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
	if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
		$this->exts->capture("hcaptcha");
		// Check if challenge images hasn't showed yet, Click checkbox to show images challenge
		if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
			$this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
			sleep(5);
		}
		// $this->exts->switchToDefault();
		if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
			$captcha_instruction = '';
			$old_height = $this->exts->evaluate('
			var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
			var old_height = wrapper.style.height;
			wrapper.style.height = "600px";
			old_height
		');
			$evalJson = json_decode($old_height, true);
			$old_height = $evalJson['result']['result']['value'];
			$coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
			if ($coordinates == '') {
				$coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
			}
			if ($coordinates != '') {
				if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
					if (!empty($old_height)) {
						$this->exts->evaluate('
						document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
					');
					}

					foreach ($coordinates as $coordinate) {
						if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
							$this->exts->log('Error');
							return;
						}
						$this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
						// sleep(1);
						if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
							$this->exts->log('Error');
							return;
						}
					}
					$marked_time = time();
					$this->exts->capture("hcaptcha-selected-" . $marked_time);

					$wraper_side = $this->exts->evaluate('
					var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
					coo.width + "|" + coo.height;
				');

					$evalJson = json_decode($wraper_side, true);
					$wraper_side = $evalJson['result']['result']['value'];

					$wraper_side = explode('|', $wraper_side);
					$this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$wraper_side[0] - 50, (int)$wraper_side[1] - 30);

					sleep(5);
					$this->exts->capture("hcaptcha-submitted-" . $marked_time);
					
				}
			}
			
		}
		return true;
	}
	return false;
}

private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
	$this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
	$selector = base64_encode($selector);
	$element_coo = $this->exts->evaluate('
	var x_on_element = ' . $x_on_element . '; 
	var y_on_element = ' . $y_on_element . ';
	var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
	// Default get center point in element, if offset inputted, out put them
	if(x_on_element > 0 || y_on_element > 0) {
		Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
	} else {
		Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
	}
	
');
	$evalJson = json_decode($element_coo, true);
	$element_coo = $evalJson['result']['result']['value'];
	// sleep(1);
	$this->exts->log("Browser clicking position: $element_coo");
	$element_coo = explode('|', $element_coo);

	$root_position = $this->get_brower_root_position();
	$this->exts->log("Browser root position");
	print_r($root_position);

	$clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
	$clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
	$this->exts->log("Screen clicking position: $clicking_x $clicking_y");
	$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
	// move randomly
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

	exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
}

private function get_brower_root_position($force_relocated = false)
{
	if (isset($GLOBALS['browser_root_position']) && is_array($GLOBALS['browser_root_position']) && !$force_relocated) {
		return $GLOBALS['browser_root_position'];
	}

	$GLOBALS['browser_root_position'] = null;
	$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
	for ($i = 0; $i < 5; $i++) {
		$x = rand(100, 355);
		$y = rand(370, 500);
		$this->exts->log("Getting browser current cursor... Screen reference point $x $y");
		$this->exts->evaluate('
		window.localStorage["lastMousePosition"] = "";
		window.addEventListener("mousemove", function(e){
			window.localStorage["lastMousePosition"] = e.clientX +"|" + e.clientY;
		});
	');
		exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync $x $y '");
		exec("sudo docker exec " . $node_name . " bash -c 'xdotool getmouselocation'", $output);
		$this->exts->log("Latest mouse posision on screen: ");
		print_r($output);

		$result = $this->exts->evaluate('window.localStorage["lastMousePosition"]');
		$evalJson = json_decode($result, true);
		$result = $evalJson['result']['result']['value'];

		if (isset($result['value']) && !empty($result['value'])) {
			$this->exts->log('Browser current cursor: ' . $result['value']);
			$current_cursor = explode('|', $result['value']);
			$GLOBALS['browser_root_position'] = array(
				'root_x' => $x - (int)$current_cursor[0],
				'root_y' => $y - (int)$current_cursor[1],
			);
			return $GLOBALS['browser_root_position'];
		}
	}

	$this->exts->log('CAN NOT detect root position of browser webview');
	return null;
}


private function processClickCaptcha(
	$captcha_image_selector,
	$instruction = '',
	$lang_code = '',
	$json_result = false,
	$image_dpi = 90
) {
	$this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
	$response = '';
	$image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
	$source_image = imagecreatefrompng($image_path);
	imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

	$cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
	$this->exts->log('Executing command : ' . $cmd);
	exec($cmd, $output, $return_var);
	$this->exts->log('Command Result : ' . print_r($output, true));

	if (!empty($output)) {
		$output = trim($output[0]);
		if ($json_result) {
			if (strpos($output, '"status":1') !== false) {
				$response = json_decode($output, true);
				$response = $response['request'];
			}
		} else {
			if (strpos($output, 'coordinates:') !== false) {
				$response = trim(end(explode("coordinates:", $output)));
			}
		}
	}
	if ($response == '') {
		$this->exts->log("Can not get result from API");
	}
	return $response;
}










private function check_solve_human_checkbox()
{
	if($this->exts->exists('iframe#sec-cpt-if')){
		$this->exts->capture('human-checkbox');
		// $this->exts->switchToFrame('iframe#sec-cpt-if');
		// sleep(1);
		$this->exts->execute_javascript('
			document.querySelector("iframe#sec-cpt-if").contentWindow.document.querySelector("#robot-checkbox").click();
		');
		sleep(2);
		$this->exts->execute_javascript('
			document.querySelector("iframe#sec-cpt-if").contentWindow.document.querySelector("div#progress-button").click();
		');
		// $this->exts->moveToElementAndClick('div#progress-button');
		for ($i=0; $i < 10; $i++) { 
			sleep(2);
			if(!$this->exts->exists('iframe#sec-cpt-if')){
				sleep(3);
				break;
			}
		}
		return true;
	}
	return false;
}
private function checkFillTwoFactor() {
	$two_factor_selector = 'input#form-input-passcode, [name="passcode"]';
	$two_factor_message_selector = 'p[data-i18n-key="login:mfaTOTPDescription"], [data-i18n-key="login:mfaTOTPDescription"] ';
	$two_factor_submit_selector = 'button#form-submit';
	if($this->exts->exists('div#available-factors input[name="factorID"]')){
		$this->exts->moveToElementAndClick('div#available-factors input[name="factorID"]');
		sleep(1);
		$this->exts->moveToElementAndClick('button[data-onclick="selectFactor"]');
		sleep(5);
	}
	if($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		
		if($this->exts->querySelector($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
			
			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(2);
			$this->exts->capture('after-click-submit-2fa');
			sleep(7);

			if($this->exts->querySelector($two_factor_selector) == null){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = '';
				$this->checkFillTwoFactor();
			} else {
				$this->exts->log("Two factor can not solved");
			}
			
		} else {
			$this->exts->log("Not received two factor code");
		}
	}
}
private function incorrectCredential(){
	$error_message = $this->exts->extract('[role="alert"]:not(.tds--is_hidden) .tds-status_msg-body', null, 'innerText');
	if(
		stripos($error_message, 'email address and password combination') !== false ||
		stripos($error_message, 'E-Mail-Adresse und Passwort nicht') !== false ||
		stripos($error_message, 'Wir konnten Sie mit den von Ihnen gemachten Angaben nicht anmelden') !== false ||
		stripos($error_message, 'sign you in using the information you provided') !== false ||
		stripos($error_message, 'recognize this sign in combination') !== false
	) {
		return true;
	}
	return false;
}




public function capture_system_screen($name='image'){
	$this->exts->log(__FUNCTION__);
	try {
		$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
		$image_name = '/home/seluser/Downloads/'.$this->exts->process_uid.'/'.$name.'.png';
		exec("sudo docker exec ".$node_name." bash -c 'sudo scrot $image_name'");

		$target = $this->exts->screen_capture_location.$name.'.png';
		$source = $this->exts->config_array['download_folder'].$name.'.png';
		if(file_exists($source)){
			exec("sudo mv -f $source $target");
		}
	} catch (\Exception $e) {
		$this->exts->log($e->getMessage());
	}
}
// Huy END block

private function processAfterLogin(){
	$this->exts->waitTillPresent($this->check_login_success_selector);
	if($this->exts->querySelector($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// after url open, don't know why website open language panel by default, Click to close it.
		$this->exts->click_if_existed('header button#dx-nav-item--locale-selector.tds--highlighted');

		// HUY UPDATE 2024-Sep-03: Seem charging invoices have not been able to download from web anymore.
		// They moved those invoice to phone app only
		// $this->exts->openUrl('https://www.tesla.com/teslaaccount/charging');
		// $this->downloadChargingInvoices();
		
		// download invoice from multi subcriptions/purchased cars
		$this->processSubscriptions();
		
		// Also have invoice in cars which user solved out
		if($this->previously_owned == 1){
			$this->processPreviouslyOwnedInvoices();
		}
		
		// We also have https://shop.tesla.com/orders?tesla_logged_in=Y for products purchased, but need a user have invoice on these to implement code
		// $this->exts->openUrl('https://shop.tesla.com/orders?tesla_logged_in=Y');
		// Then download orders invoice if it have
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');

		$error_message = $this->exts->extract('[role="alert"]:not(.tds--is_hidden) .tds-status_msg-body', null, 'innerText');
		if($this->incorrectCredential()) {
			$this->exts->loginFailure(1);
		} elseif (stripos($this->exts->extract('[role="alert"]:not(.tds--is_hidden) .tds-status_msg-body', null, 'innerText'), 'Your account is locked') !== false) {
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function downloadChargingInvoices($paging_count=1) {
	sleep(25);
	$this->exts->capture("4-charginginvoices-page");
	$invoices = [];
	if ($this->exts->exists('a[href*="/invoice/"]')) {
		$rows = $this->exts->getElements('tr');
		foreach ($rows as $row) {
			if($this->exts->querySelector('a[href*="/invoice/"]', $row) != null) {
				$invoiceUrl = $this->exts->querySelector('a[href*="/invoice/"]', $row)->getAttribute("href");
				$invoiceName = end(explode('/invoice/', $invoiceUrl));
				$invoiceDate = trim($this->exts->extract('[headers="date"]', $row));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '',$this->exts->extract('[headers="total"]', $row))) . ' EUR';
				
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			}
		}
		
		// Download all invoices
		$this->exts->log('Invoices found: '.count($invoices));
		foreach ($invoices as $invoice) {
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice['invoiceName']);
			$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
			
			$invoiceFileName = $invoice['invoiceName'].'.pdf';
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
			
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}
	} else if ($this->exts->exists('tr button[data-test*="charging-export-table"][data-test*="download"]')) {
	
	}
	
	
	$rows_len = count($this->exts->getElements('a[data-test="charging-manage-payment"] ~ div table tr'));
	for ($i = 0; $i < $rows_len; $i++) {
		$row = $this->exts->getElements('a[data-test="charging-manage-payment"] ~ div table tr')[$i];
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 2 && $this->exts->querySelector('button[data-test*="charging-export-table"][data-test*="download"]', $row) != null) {
			$download_button = $this->exts->querySelector('button[data-test*="charging-export-table"][data-test*="download"]', $row);
			$invoiceName = trim(str_replace(' ', '', $this->exts->extract('td[headers="month"]', $row, 'innerText')));
			$invoiceDate = trim($this->exts->extract('td[headers="month"]', $row, 'innerText'));
			$invoiceAmount = '';
			
			$this->isNoInvoice = false;
			
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'F Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoiceDate);
			
			if ($this->exts->document_exists($invoiceFileName)) {
				continue;
			}
			
			try{
				$this->exts->log('Click download button');
				$download_button->click();
			} catch(\Exception $exception){
				$this->exts->log('Click download button by javascript');
				$this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
			}
			sleep(5);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
			
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
			}
		}
	}
	
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($this->exts->config_array["restrictPages"] == '0' &&
		$paging_count < 30 &&
		$this->exts->querySelector('[data-test="charging-pagination-next"]:not([disabled])') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('[data-test="charging-pagination-next"]:not([disabled])');
		sleep(5);
		$this->downloadChargingInvoices($paging_count);
	}
}
private function processSubscriptions() {
	// open https://www.tesla.com/teslaaccount
	// it will list out all purchased cars, subcription
	// On each subcription, click "Manage" to open detail page
	$this->exts->openUrl('https://www.tesla.com/teslaaccount');
	sleep(8);
	// after url open, don't know why website open language panel by default, Click to close it.
	$this->exts->click_if_existed('header button#dx-nav-item--locale-selector.tds--highlighted');
	$this->exts->capture("4-teslaaccount-page");
	// Get owned car links
	$subcriptions = $this->exts->getElementsAttribute('[data-test="teslaaccount-tile"] a[href*="/teslaaccount/ownership?"]', 'href');
	// [data-test="teslaaccount-tile"] a[href*="/teslaaccount/ownership?"]
	// Add more links for car is in booking process, user has not received car yet
	$subcriptions = array_merge($subcriptions, $this->exts->getElementsAttribute('[data-test="teslaaccount-tile"] a[href*="/teslaaccount/profile?"]', 'href'));
	$subcriptions = array_merge($subcriptions, $this->exts->getElementsAttribute('[data-test="teslaaccount-tile"] a[href*="/teslaaccount/order/"]', 'href'));// seem now it become /order/
	print_r($subcriptions);
	foreach($subcriptions as $subcription_url) {
		$this->exts->openUrl($subcription_url);
		sleep(7);
		$this->exts->click_if_existed('header button#dx-nav-item--locale-selector.tds--highlighted');
		$this->downloadSubscriptionsInvoices();
	}
}
private function downloadSubscriptionsInvoices() {
	if(stripos($this->exts->getUrl(), 'rn=') !== false){
		$subcription_id = reset(explode('&', end(explode('rn=', $this->exts->getUrl()))));
	} else if(stripos($this->exts->getUrl(), '/order/') !== false){
		$subcription_id = reset(explode('/', end(explode('/order/', $this->exts->getUrl()))));
	}
	
	$this->exts->capture('subcription_'.$subcription_id);
	// In subcription detail page, We have invoice in multi places
	// 1. Download documents from "Contracts and other documents"
	$invoice_links = $this->exts->getElements(
		'[data-test*="toggle-drawer-documents"] a[href*="/pdf"]'.
		', [data-test*="toggle-drawer-documents"] a[href*="files/"]'. 
		', [data-test*="toggle-drawer-documents"] a[href*="invoice/"]'
	);
	foreach ($invoice_links as $invoice_link) {
		$this->isNoInvoice = false;
		$invoice_url = $invoice_link->getAttribute('href');
		for ($i=0, $skip = false; $i < count($this->exclude_documents); $i++) {
			if(stripos($invoice_url, $this->exclude_documents[$i]) !== false){
				$this->exts->log('Skip non-invoice document: ' .$invoice_url);
				$skip = true;
				break;
			}
			$refer_text = $invoice_link->getHtmlAttribute('data-test');
			if(stripos($refer_text, $this->exclude_documents[$i]) !== false){
				$this->exts->log('Skip non-invoice document: ' .$refer_text);
				$skip = true;
				break;
			}
		}
		if($skip) continue;

		if(stripos($invoice_url, 'files/') !== false) {
			$temp_array = explode('files/', $invoice_url);
			$invoice_name = end($temp_array);
			$temp_array = explode('?', $invoice_name);
			$invoice_name = reset($temp_array);
		} else if(stripos($invoice_url, 'invoice/') !== false) {
			$temp_array = explode('invoice/', $invoice_url);
			$invoice_name = end($temp_array);
			$temp_array = explode('?', $invoice_name);
			$invoice_name = reset($temp_array);
		} else {
			$invoice_name = $subcription_id.'-'.preg_replace("/[^\w]/", '', $invoice_link->getAttribute('innerText'));
		}
		$this->exts->log('--------------------------');
		$this->exts->log($invoice_link->getAttribute('innerText'));
		$this->exts->log('invoiceName: '.$invoice_name);
		
		$invoiceFileName = $invoice_name.'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			$this->capture_system_screen('no-download');
		}
	}
	// 2. Upgrade invoices
	// Click "Detail" button, It open a popup, If user has upgraded anything, invoices listed here, else, no document listed on detail box
	$upgrade_invoice_links = $this->exts->getElements('[data-test="ownership-main-car-details-upgrades"] a[href*="/show-upgrade-invoice/"]');
	foreach ($upgrade_invoice_links as $upgrade_invoice_link) {
		$this->isNoInvoice = false;
		$invoice_url = $upgrade_invoice_link->getAttribute('href');
		$invoice_name = explode('?', end(explode('upgrade-invoice/', $invoice_url)))[0];
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice_name);
		
		$invoiceFileName = $invoice_name.'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice_name, '', '', $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
	
	// 3. If the order is in waiting status, user has not received the car yet, We don't have "Contracts and other documents" or Upgrade invoices. BUT it list out other invoices, example: Booking deposit invoice
	$subcription_id = explode('&', end(explode('rn=', $this->exts->getUrl())))[0];
	$this->exts->click_if_existed('#documents:not([open]) .tds-icon--small');
	sleep(2);
	// $document_rows = $this->exts->getElements('.hide-on-mobile .document-hub-documents .content-row');
	$document_rows = $this->exts->getElements('#documents #tsla-documents--content li');
	foreach ($document_rows as $document_row) {
		$invoice_link = $this->exts->getElement('a[href*="/documents/"]', $document_row);
		if($invoice_link != null){
			$this->isNoInvoice = false;
			// $invoice_name = $subcription_id.'-'.preg_replace("/[^\w]/", '', $this->exts->extract('.document-name', $document_row));
			$invoice_url = $invoice_link->getAttribute('href');
			$temp_array = explode('/documents/', $invoice_url);
			$invoice_name = end($temp_array);
			$temp_array = explode('/', $invoice_name);
			$invoice_name = reset($temp_array);

			for ($i=0, $skip = false; $i < count($this->exclude_documents); $i++) {
				if(strpos($invoice_url, $this->exclude_documents[$i]) !== false){
					$this->exts->log('Skip non-invoice document: ' .$invoice_url);
					$skip = true;
					break;
				}
			}
			if($skip) continue;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice_name);
			
			$invoiceFileName = $invoice_name.'.pdf';
			$downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}
	}
}
private function processPreviouslyOwnedInvoices() {
	// open https://www.tesla.com/teslaaccount/ownership/previously-owned
	// it will list out all previosly owned cars,
	// loop through all and get invoice
	$this->exts->openUrl('https://www.tesla.com/teslaaccount/ownership/previously-owned');
	sleep(15);
	$this->exts->capture("4-previously-owned-page");
	// Get owned car links
	$previously_owned_cars = $this->exts->getElementsAttribute('a[data-test="previously-owned-cars-item-view-button"], a[data-test="previously-owned-cars-tile-view-link"]', 'href');
	foreach($previously_owned_cars as $previously_owned_car) {
		$this->exts->openUrl($previously_owned_car);
		sleep(15);
		
		$refer_number = explode('&', end(explode('rn=', $this->exts->getUrl())))[0];
		$invoice_links = $this->exts->getElements('[data-test="previously-owned-details-glovebox-docs"] a, [data-test="previously-owned-details-subscription-docs"] a');
		foreach ($invoice_links as $invoice_link) {
			$this->isNoInvoice = false;
			$invoice_name = $refer_number.'-'.preg_replace("/[^\w]/", '', $invoice_link->getAttribute('innerText'));
			$invoice_url = $invoice_link->getAttribute('href');
			for ($i=0, $skip = false; $i < count($this->exclude_documents); $i++) {
				if(strpos($invoice_url, $this->exclude_documents[$i]) !== false){
					$this->exts->log('Skip non-invoice document: ' .$invoice_url);
					$skip = true;
					break;
				}
				$refer_text = $invoice_link->getHtmlAttribute('data-test');
				if(stripos($refer_text, $this->exclude_documents[$i]) !== false){
					$this->exts->log('Skip non-invoice document: ' .$refer_text);
					$skip = true;
					break;
				}
			}
			if($skip) continue;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice_name);
			
			$invoiceFileName = $invoice_name.'.pdf';
			$downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice_name, '', '', $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}
	}
}