public $baseUrl = 'https://www.rogers.com/consumer/self-serve/overview';
public $loginUrl = 'https://www.rogers.com/consumer/profile/signin';
public $invoicePageUrl = 'https://www.rogers.com/consumer/self-serve/view-bill';

public $username_selector = 'input#username, input#ds-form-input-id-0';
public $password_selector = 'input#password, input#input_password';
public $remember_me_selector = '[formcontrolname="rememberMe"] .ds-checkbox__box:not(.rds-icon-check)';
public $submit_login_selector = '.signInButton button[type="submit"]';

public $check_login_failed_selector = 'ds-alert#ds-alert-0';
public $check_login_success_selector = '//*[contains(text(),"Billing")]';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->exts->openUrl($this->baseUrl);
	sleep(15);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector, null, 'xpath') == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		$this->checkFillLogin();

		$this->exts->waitTillPresent('button[title*="email"]', 20);

		if($this->exts->exists('button[title*="email"]')){
			$this->exts->moveToElementAndClick('button[title*="email"]');
			sleep(20);
		}

		$this->checkFillTwoFactor();
		
	}
	if ($this->exts->getElement('form#phoneNumberForm') != null) {
		$this->exts->moveToElementAndClick('button.ds-button.ds-corners.ds-pointer:not([type="submit"])');
		sleep(10);
	}
	if ($this->exts->getElement('.modal-eop-notification-modal') != null) {
		$this->exts->moveToElementAndClick('.modal-eop-notification-modal button.close');
		sleep(5);
	}
	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector, null, 'xpath') != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {

			$this->exts->triggerLoginSuccess();
		}

	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'password is not recognized') !== false) {
			$this->exts->loginFailure(1);
		} else if ($this->exts->getElement('//h1[contains(text(),"Details not available")]', null, 'xpath') != null) {
			$this->exts->account_not_ready();
		} else if(strpos(strtolower($this->exts->extract('ds-alert p', null, 'innerText')), 'sername is unrecognized') !== false
			|| strpos(strtolower($this->exts->extract('ds-alert p', null, 'innerText')), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		$this->exts->moveToElementAndClick($this->password_selector);
		sleep(1);
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		$this->checkFillRecaptcha();
	} else if($this->exts->getElement($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndClick($this->username_selector);
		sleep(1);
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(5);
		$this->exts->moveToElementAndClick($this->password_selector);
		sleep(1);
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		$this->checkFillRecaptcha();
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkFillRecaptcha() {
	$this->exts->log(__FUNCTION__);
	$recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
	$recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
	if($this->exts->exists($recaptcha_iframe_selector)) {
		$iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
		$data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
		$this->exts->log("iframe url  - " . $iframeUrl);
		$this->exts->log("SiteKey - " . $data_siteKey);
		
		$isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
		$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
		
		if($isCaptchaSolved) {
			// Step 1 fill answer to textarea
			$this->exts->log(__FUNCTION__."::filling reCaptcha response..");
			$recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
			for ($i=0; $i < count($recaptcha_textareas); $i++) { 
				$this->exts->executeSafeScript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
			}
			sleep(2);
			$this->exts->capture('recaptcha-filled');

			// Step 2, check if callback function need executed
			$gcallbackFunction = $this->exts->executeSafeScript('
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
			');
			$this->exts->log('Callback function: '.$gcallbackFunction);
			if($gcallbackFunction != null){
				$this->exts->executeSafeScript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
				sleep(10);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}
private function checkFillTwoFactor() {
	$two_factor_selector = 'div.ds-codeInput__wrapper .ds-codeInput__inputContainer input';
	$two_factor_message_selector = '.verify-component > p';
	$two_factor_submit_selector = '.code-input-container button[type="submit"]';

	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector) != null){
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

			$resultCodes = str_split($two_factor_code);

			for ($i = 0; $i < 4; $i++) {
				$this->exts->type_key_by_xdotool('Tab');
				sleep(1);
			}
			$this->exts->type_key_by_xdotool('Return');
			sleep(2);
			foreach($resultCodes as $inputVal){
				$this->exts->log("inputVal" . $inputVal);
				sleep(2);
				$this->exts->type_text_by_xdotool($inputVal);
			}
			
			
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if($this->exts->getElement($two_factor_selector) == null){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->notification_uid = "";
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
