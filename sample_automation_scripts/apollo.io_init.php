public $baseUrl = 'https://app.apollo.io/#/login';
public $loginUrl = 'https://app.apollo.io/#/login';
public $invoicePageUrl = 'https://app.apollo.io/#/settings/plans/billing';
public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '//label[.//div[@data-cy-status="unchecked"]]';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'form span[id*="desc"]';
public $check_login_success_selector = '[data-tour="user-profile-button"]';
public $isNoInvoice = true;
public $restrictPages = 3;

/**

	* Entry Method thats called for a portal

	* @param Integer $count Number of times portal is retried.

	*/
private function initPortal($count)
{
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
	$this->exts->log('Begin initPortal ' . $count);
	$this->exts->loadCookiesFromFile();
	$this->exts->openUrl($this->loginUrl);
	sleep(3);

	$this->check_solve_cloudflare_page();
	if (!$this->checkLogin()) {
		$this->exts->log('NOT logged via cookie');
		$this->fillForm(0);
		sleep(5);
		$this->checkFillTwoFactor();
		$this->exts->waitTillPresent($this->check_login_success_selector);
	}

	if ($this->checkLogin()) {
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

		$this->exts->success();
	} else {
		if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), " don't match with any") !== false) {
			$this->exts->log("Wrong credential !!!!");
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function check_solve_cloudflare_page()
{
	$unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
	$solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
	$this->exts->capture("cloudflare-checking");
	if (
		!$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
		$this->exts->exists(selector_or_xpath: '#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
	) {
		for ($waiting = 0; $waiting < 10; $waiting++) {
			sleep(2);
			if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
				sleep(3);
				break;
			}
		}
	}

	if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
		$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
		sleep(5);
		$this->exts->capture("cloudflare-clicked-1", true);
		sleep(3);
		if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
			$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
			sleep(5);
			$this->exts->capture("cloudflare-clicked-2", true);
			sleep(15);
		}
		if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
			$this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
			sleep(5);
			$this->exts->capture("cloudflare-clicked-3", true);
			sleep(15);
		}
	}
}

public function fillForm($count)
{
	$this->exts->log("Begin fillForm " . $count);
	try {
		if ($this->exts->querySelector($this->username_selector) != null) {

			$this->exts->capture("1-pre-login");
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);

			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			sleep(1);

			if ($this->exts->exists($this->remember_me_selector)) {
				$this->exts->click_element($this->remember_me_selector);
				sleep(1);
			}
			$this->exts->capture('2-login-page-filled');
			$this->exts->moveToElementAndClick($this->submit_login_selector);
			sleep(7); // Portal itself has one second delay after showing toast
			try {
				$this->check_solve_cloudflare_page();
				$this->check_solve_cloudflare_page();
			} catch (TypeError $e) {
				$this->exts->capture('2-script-error');
				$this->exts->log($e->getMessage());
				sleep(20);
			}
		}
	} catch (\Exception $exception) {

		$this->exts->log("Exception filling loginform " . $exception->getMessage());
	}
}

private function checkFillTwoFactor()
{
	$two_factor_selector = 'input[name="otp"]';
	$two_factor_message_selector = 'h2 + p';
	$two_factor_submit_selector = 'button[type="submit"]';

	if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if ($this->exts->getElement($two_factor_message_selector) != null) {
			$this->exts->two_factor_notif_msg_en = "";
			for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
			}
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
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(1);
			$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(5);

			if ($this->exts->getElement($two_factor_selector) == null) {
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->notification_uid = '';
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


/**

	* Method to Check where user is logged in or not

	* return boolean true/false

	*/
public function checkLogin()
{
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if ($this->exts->exists($this->check_login_success_selector)) {

			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

			$isLoggedIn = true;
		}
	} catch (Exception $exception) {

		$this->exts->log("Exception checking loggedin " . $exception);
	}

	return $isLoggedIn;
}