public $baseUrl = "https://login.sipgate.com/";
public $afterLoginUrl = "https://app.sipgate.com/";
public $basicInvoiceUrl = "https://app.sipgatebasic.de/account";
public $teamInvoiceUrl = "https://app.sipgate.com/w0/team/settings/invoices";
public $teamInvoiceLatestUrl = "https://app.sipgate.com/administration/invoices/settings/invoices";
public $loginUrl = "https://login.sipgate.com/";
public $username_selector = "form.flex-container input[name=username],input#username";
public $username_selector_1 = "div.login__body form input#username";
public $password_selector = "input#password, form.flex-container input[name=password]";
public $password_selector_1 = "div.login__body form input#password";
public $submit_button_selector = "form.flex-container button[type=submit],button#kc-login";
public $submit_button_selector_1 = "div.login__body form button[type=submit], button.g-recaptcha.login__submit[data-action=submit]";
public $linq_username_selector = 'input#username';
public $linq_password_selector = 'input#password';
public $linq_submit_selector = 'button.login__submit';
public $login_tryout = 0;
public $restrictPages = 0;
public $remember_me_selector = 'input#rememberMe';

/**
 * Method to change value of select box
 * @param String $sel Css selector
 * @param String $value Value to send
 * @param Integer $sleep Sleep time (in seconds) after sendKeys
 */

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	
	$this->exts->openUrl($this->loginUrl);
	sleep(5);
	$this->exts->capture("Home-page-without-cookie");
	
	$isCookieLoginSuccess = false;
	if($this->exts->loadCookiesFromFile()) {
		sleep(2);
		
		$this->exts->openUrl($this->afterLoginUrl);
		sleep(5);
		$this->exts->capture("Home-page-with-cookie");
		
		if($this->checkLogin()) {
			$isCookieLoginSuccess = true;
		} else {
			$this->clearChrome();
			sleep(2);
			
			$this->exts->openUrl($this->loginUrl);
			sleep(5);
		}
	} else {
		$this->clearChrome();
		sleep(2);
		$this->exts->openUrl($this->loginUrl);
		sleep(5);
	}
	
	if(!$isCookieLoginSuccess) {
		if ($this->exts->exists('a[id="login"][href*="clinq"]')) {
			$this->exts->openUrl('https://www.clinq.app');
			sleep(10);
		}
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
		$this->fillForm(0);
		sleep(10);
		if($this->exts->exists('a[href="javascript:location.replace(location.href)"]') && strpos($this->exts->extract('.jsonly > h1 + p'), 'not correct') !== false && !$this->checkLogin()){
			$this->exts->moveToElementAndClick('a[href="javascript:location.replace(location.href)"]');
			sleep(10);
			$this->fillForm(0);
		}
	
	
		$this->exts->waitTillAnyPresent(explode(',','input[name="emailCode"], form#loginform input#otp'), 10);
		if($this->exts->exists('input[name="emailCode"], form#loginform input#otp')){
			$this->checkFillTwoFactor();
		}

		if($this->exts->exists('input[name="trust-device"].trust_device__submit')){
			$this->exts->moveToElementAndClick('input[name="trust-device"].trust_device__submit');
			sleep(10);
		}

		$isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or email.")');
		$isInvalidTwoFA = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid confirmation code.")');

		$this->exts->log('isErrorMessage:' . $isErrorMessage);

		if($this->checkLogin()) {
			$this->exts->capture("LoginSuccess-if");
			
			if (!empty($this->exts->config_array['allow_login_success_request'])) {
				$this->exts->triggerLoginSuccess();
			}
		} else if (strpos($this->exts->extract('div.login div.alert--error'), 'passwor') !== false){
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure(1);
		} else if ($isErrorMessage) {
			$this->exts->capture("LoginFailed");
			$this->exts->log("Invalid username or email.");
			$this->exts->loginFailure(1);
		} else if ($isInvalidTwoFA) {
			$this->exts->capture("LoginFailed");
			$this->exts->log("Invalid confirmation code.");
			$this->exts->loginFailure(1);
		}else {
			$this->exts->log('Login failed');
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure();
		}
	} else {
		$this->exts->capture("LoginSuccess-else");
		
	    if (!empty($this->exts->config_array['allow_login_success_request'])) {
 
            $this->exts->triggerLoginSuccess();
        }
	}
}

private function clearChrome()
{
	$this->exts->log("Clearing browser history, cookie, cache");
	$this->exts->openUrl('chrome://settings/clearBrowserData');
	sleep(10);
	$this->exts->capture("clear-page");
	for ($i = 0; $i < 2; $i++) {
		$this->exts->type_key_by_xdotool('Tab');
		sleep(1);
	}
	$this->exts->type_key_by_xdotool('Tab');
		sleep(1);
	$this->exts->type_key_by_xdotool('Return');
		sleep(1);
	$this->exts->type_key_by_xdotool('a');
	sleep(1);
	$this->exts->type_key_by_xdotool('Return');
	sleep(3);
	$this->exts->capture("clear-page");
	for ($i = 0; $i < 5; $i++) {
		$this->exts->type_key_by_xdotool('Tab');
		sleep(1);
	}
	$this->exts->type_key_by_xdotool('Return');
	sleep(15);
	$this->exts->capture("after-clear");
}

/**
 * Method to fill login form
 * @param Integer $count Number of times portal is retried.
 */
function fillForm($count){
	$this->exts->log("Begin fillForm " . $count);
	$this->exts->waitTillPresent($this->username_selector, 5);
	try {
		if ($this->exts->querySelector($this->username_selector) != null) {

			$this->exts->capture("1-pre-login");
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(2);

			if ($this->exts->exists($this->remember_me_selector)) {
				$this->exts->click_element($this->remember_me_selector);
				sleep(2);
			}

			if ($this->exts->exists($this->submit_button_selector)) {
				$this->exts->click_by_xdotool($this->submit_button_selector);
				sleep(7);
			}

			$isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or email.")');

			$this->exts->log('isErrorMessage:' . $isErrorMessage);

			if(!$isErrorMessage){
				$this->findPasswordPage($this->password_selector, $this->submit_button_selector);
				$this->exts->log("Enter Password");
				$this->exts->moveToElementAndType($this->password_selector, $this->password);
				sleep(2);
				$this->exts->capture("1-login-page-filled");
				sleep(5);
				if ($this->exts->exists($this->submit_button_selector)) {
					$this->exts->click_by_xdotool($this->submit_button_selector);
				}
				sleep(5);
				$isErrorPassMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid password.")');
				$this->exts->log('isErrorPassMessage:' . $isErrorPassMessage);
				if (!$isErrorPassMessage && !$this->checkLogin()) {
					$this->findPasswordPage('input[name="emailCode"], form#loginform input#otp');
				}
				
			}
			
            
		}
	} catch (\Exception $exception) {

		$this->exts->log("Exception filling loginform " . $exception->getMessage());
	}
}

private function findPasswordPage($selector, $button = null)
{
    $this->exts->waitTillAnyPresent(explode(',',$selector), 10);
    $timeout = 200; // Max wait time in seconds
    $interval = 5;  // Time to wait between checks (adjust as needed)
    $startTime = time();
   

    while (time() - $startTime < $timeout) {
         $this->exts->log("Finding selector ". time());
        if ($this->exts->exists($selector)) {
            $this->exts->log("selector Found");
            break;
        }
        if($button != null){
            $this->exts->click_by_xdotool($button);
        }
       
        $this->exts->waitTillAnyPresent(explode(',',$selector), 10);
        sleep($interval); 
    }

    // Optional: Handle case where the element was not found within 200 seconds
    if (!$this->exts->exists($selector)) {
          $this->exts->log("selector not found within 200 seconds.");
    }
}

function checkLogin(){
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	
	if($this->exts->getElement('a[href*="/logout"], a[href*="/channel/"], , a[href*="/users"], a[href*="/groups"]') != null && !$this->exts->exists($this->password_selector_1) && !$this->exts->exists($this->password_selector)) {
		$isLoggedIn = true;
	} else if($this->exts->getElement("body[data-tracking-email*=\"@\"]") != null) {
		$isLoggedIn = true;
	} else if($this->exts->getElement("[data-test-selector=\"app-web-authenticated\"]") != null) {
		$isLoggedIn = true;
	}
	
	return $isLoggedIn;
}

private function checkFillTwoFactor()
{
	$two_factor_selector = 'input[name="emailCode"], form#loginform input#otp';
	$two_factor_message_selector = 'form#loginform div.otpMessage';
	$two_factor_submit_selector = 'input[type="submit"][class="login__submit"], form#loginform button#kc-login';
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