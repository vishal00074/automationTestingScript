public $baseUrl = 'https://portal.klarna.com/';
public $invoicePageUrl = 'https://portal.klarna.com/settlements/reports';
public $username_selector = 'form#login input#username';
public $password_selector = 'form#login input#password';
public $submit_login_selector = 'form#login button#loginBtn';
public $check_login_failed_selector = 'main div.non-2fa-class';
public $check_login_success_selector = 'a[href*="/settlements"], #header-usermenu-button button#header-usermenu-icon';

public $isNoInvoice = true;
public $only_monthly_statements = 0;

/**
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/

private function initPortal($count)
{
	$this->disable_extensions();
	$this->exts->log('Begin initPortal ' . $count);
	$this->only_monthly_statements = isset($this->exts->config_array["only_monthly_statements"]) ? (int)@$this->exts->config_array["only_monthly_statements"] : $this->only_monthly_statements;
	$this->exts->openUrl($this->baseUrl);
	sleep(10);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if ($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		for ($i = 0; $i < 5; $i++) {
			if ($this->exts->getElement('iframe[src*="/recaptcha/api2/anchor?"]') != null) {
				$this->checkFillRecaptcha($i);
			}
		}

		if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
			$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
			sleep(1);
		}

		$this->checkFillLogin();
		sleep(5);

		$this->solve_captcha_by_clicking();
		$this->solve_captcha_by_clicking();
		$this->solve_captcha_by_clicking();
		$this->solve_captcha_by_clicking();
		$this->solve_captcha_by_clicking();

		for ($i = 0; $i < 5; $i++) {
			if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
				$this->checkFillRecaptcha($i);
			}
		}

		if ($this->exts->exists('iframe[src*="/authentication"]')) {
			$this->switchToFrame('iframe[src*="/authentication"]');

			sleep(2);
			if ($this->exts->exists('button#otp-intro-send-button')) {
				$this->exts->moveToElementAndClick('button#otp-intro-send-button');
				sleep(10);
			}
			$this->checkFillTwoFactor();
			sleep(5);
			$this->exts->switchToDefault();
		}
		if ($this->exts->exists('button#otp-intro-send-button')) {
			$this->exts->moveToElementAndClick('button#otp-intro-send-button');
			sleep(5);
		}
		$this->checkFillTwoFactor();
		sleep(5);
	}
	for ($i = 0; $i < 5; $i++) {
		if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {
			$this->checkFillRecaptcha($i);
		}
	}

	if ($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__ . '::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__ . '::Use login failed');
		$this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
		if (
			stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'oder passwort') !== false ||
			stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'invalid username or password') !== false ||
			stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'please contact support') !== false
		) {
			$this->exts->loginFailure(1);
		} else if ($this->exts->getElement('//span[contains(text(),"Please follow the setup steps below to secure your account")]') != null) {
			$this->exts->account_not_ready();
		} else if ($this->exts->getElement('//span[text()="You need to change your password to activate your account."]') != null) {
			$this->exts->account_not_ready();
		} else if ($this->exts->getElement('button#auth-app-btn') != null) {
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function disable_extensions()
{
	$this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
	sleep(2);
	$this->exts->execute_javascript("
	let manager = document.querySelector('extensions-manager');
	if (manager && manager.shadowRoot) {
		let itemList = manager.shadowRoot.querySelector('extensions-item-list');
		if (itemList && itemList.shadowRoot) {
			let items = itemList.shadowRoot.querySelectorAll('extensions-item');
			items.forEach(item => {
				let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
				if (toggle) toggle.click();
			});
		}
	}
");
}




private function checkFillLogin()
{
	if ($this->exts->getElement($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(2);

		if ($this->exts->exists('iframe[src*="/recaptcha/enterprise"]')) {
			$this->checkFillRecaptcha(4);
		}

		$this->exts->capture("2-login-page-filled");

		if ($this->exts->exists($this->submit_login_selector)) {
			$this->exts->moveToElementAndClick($this->submit_login_selector);
			sleep(7);
		}

		if ($this->exts->exists('iframe[src*="/recaptcha/enterprise"]')) {
			$this->checkFillRecaptcha(5);
		}

		sleep(5);
	} else {
		$this->exts->log(__FUNCTION__ . '::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}


private function solve_captcha_by_clicking($count = 1)
{
	$this->exts->log("Checking captcha");
	$language_code = '';
	$hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';
	$this->exts->waitTillPresent($hcaptcha_challenger_wraper_selector, 20);
	if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {

		$this->exts->capture("re-captcha");

		$captcha_instruction = '';

		//$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
		$this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
		sleep(5);
		$captcha_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';

		if ($this->exts->exists($captcha_wraper_selector)) {
			$coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);

			// if($coordinates == '' || count($coordinates) < 2){
			//  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
			// }
			if ($coordinates != '') {
				// $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

				foreach ($coordinates as $coordinate) {
					$this->click_captcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
				}

				$this->exts->capture("re-captcha-selected " . $count);
				$this->exts->makeFrameExecutable('div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
				sleep(10);
				return true;
			}
		}

		return false;
	}
}

private function click_captcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
	$this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
	$selector = base64_encode($selector);
	$element_coo = $this->exts->execute_javascript('
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
	// sleep(1);
	$this->exts->log("Browser clicking position: $element_coo");
	$element_coo = explode('|', $element_coo);

	$root_position = $this->exts->get_brower_root_position();
	$this->exts->log("Browser root position");
	$this->exts->log(print_r($root_position, true));

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

private function getCoordinates(
	$captcha_image_selector,
	$instruction = '',
	$lang_code = '',
	$json_result = false,
	$image_dpi = 75
) {
	$this->exts->log("--GET Coordinates By 2CAPTCHA--");
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
				$array = explode("coordinates:", $output);
				$response = trim(end($array));
				$coordinates = [];
				$pairs = explode(';', $response);
				foreach ($pairs as $pair) {
					preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
					if (!empty($matches)) {
						$coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
					}
				}
				$this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
				$this->exts->log(print_r($coordinates, true));
				return $coordinates;
			}
		}
	}

	if ($response == '') {
		$this->exts->log("Can not get result from API");
	}
	return $response;
}


public function switchToFrame($query_string)
{
	$this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
	$frame = null;
	if (is_string($query_string)) {
		$frame = $this->exts->queryElement($query_string);
	}

	if ($frame != null) {
		$frame_context = $this->exts->get_frame_excutable_context($frame);
		if ($frame_context != null) {
			$this->exts->current_context = $frame_context;
			return true;
		}
	} else {
		$this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
	}

	return false;
}

private function checkFillTwoFactor()
{
	$two_factor_selector = 'input#otp, div#authentication-ui__container input';
	$two_factor_message_selector = 'header ~ main section > p, div#authentication-ui__container p';
	$two_factor_submit_selector = 'form[action*="/authenticate"] button#kc-login';
	$this->exts->waitTillPresent($two_factor_selector, 10);
	if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		if ($this->exts->getElement($two_factor_message_selector) != null) {
			$this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
		}
		if ($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
		}
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if (!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
			$this->exts->click_by_xdotool($two_factor_selector);
			sleep(2);
			$this->exts->type_text_by_xdotool($two_factor_code);

			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


			$this->exts->click_by_xdotool($two_factor_submit_selector);
			sleep(15);
			if ($this->exts->querySelector($two_factor_selector) == null) {
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->checkFillTwoFactor();
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else {
			$this->exts->log("Not received two factor code");
		}
	}
}

private function checkFillRecaptcha($count = 1)
{
	$this->exts->log(__FUNCTION__);
	$recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"]';
	$recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
	$this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
	if ($this->exts->exists($recaptcha_iframe_selector)) {
		$iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
		$data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
		$this->exts->log("iframe url  - " . $iframeUrl);
		$this->exts->log("SiteKey - " . $data_siteKey);

		$isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
		$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

		if ($isCaptchaSolved) {
			// Step 1 fill answer to textarea
			$this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
			$recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
			for ($i = 0; $i < count($recaptcha_textareas); $i++) {
				$this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
			}
			sleep(2);
			$this->exts->capture('recaptcha-filled');

			$gcallbackFunction = $this->exts->execute_javascript('
			(function() { 
				if(document.querySelector("[data-callback]") != null){
					return document.querySelector("[data-callback]").getAttribute("data-callback");
				}

				var result = ""; var found = false;
				function recurse (cur, prop, deep) {
					if(deep > 5 || found){ return;}console.log(prop);
					try {
						if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
						if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
						} else { deep++;
							for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
						}
					} catch(ex) { console.log("ERROR in function: " + ex); return; }
				}

				recurse(___grecaptcha_cfg.clients[0], "", 0);
				return found ? "___grecaptcha_cfg.clients[0]." + result : null;
			})();
		');
			$this->exts->log('Callback function: ' . $gcallbackFunction);
			$this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
			if ($gcallbackFunction != null) {
				$this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
				sleep(10);
			}
		} else {
			// try again if recaptcha expired
			if ($count < 3) {
				$count++;
				$this->checkFillRecaptcha($count);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
	}
}