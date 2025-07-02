public $baseUrl = 'https://www.internetx.com/';
public $username_selector = '.collapse.in input[name="user"], form#lfrm input#txtUser, form[name="newLogin"] input[name="user"], form#ix-login-form input[name="userid"], form#login-autodns input[name="userid"]';
public $password_selector = '.collapse.in input[name="password"], form#lfrm input#txtPassword, form[name="newLogin"] input[name="password"], form#ix-login-form input[name="password"], form#login-autodns input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = '.collapse.in button[type="submit"], form#lfrm [type="submit"], form[name="newLogin"] button[type="submit"], form#ix-login-form button#ix-login-btn, form#login-autodns button[type="submit"]';

public $check_login_failed_selector = '#ix-login-form div.errors';
public $check_login_success_selector = '#btnManageAssignedUser, #ix-smenu-user-button';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/
private function initPortal($count)
{
	$this->exts->log('Begin initPortal ' . $count);

	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

	$custom_url = isset($this->exts->config_array["custom_url"]) ? $this->exts->config_array["custom_url"] : '';
	$this->exts->log('Config custom url: ' . $custom_url);
	if ($custom_url == null || trim($custom_url) == '') {
		$custom_url =  $this->baseUrl;
	}
	if (stripos($custom_url, 'https://') === false && stripos($custom_url, 'http://') === false) {
		$custom_url = 'https://' . $custom_url;
	}
	$this->exts->log('Final custom url: ' . $custom_url);
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($custom_url);
	sleep(10);
	$this->exts->capture('1-init-page');

	$this->accept_cookies();

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if ($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		//$this->exts->clearCookies();
		$this->exts->openUrl($custom_url);
		sleep(15);

		$this->accept_cookies();

		if ($this->exts->querySelector('nav ul li button') != null) {
			$this->exts->moveToElementAndClick('nav ul li button');
			sleep(5);
		}
		$this->checkFillLogin();
		sleep(20);
		$this->checkFillTwoFactor();
	}

	if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
		$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
		sleep(5);
	}

	// then check user logged in or not
	if ($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__ . '::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
		
		$this->exts->success();
	} else {
		//Check if again login page of autodns has come if yes fill it again
		if ($this->exts->getElement($this->password_selector) != null) {
			$this->checkFillLogin();
			sleep(20);

			if ($this->exts->exists('div.modal-dialog .modal-footer button.btn-secondary')) {
				$this->exts->moveToElementAndClick('div.modal-dialog .modal-footer button.btn-secondary');
				sleep(5);
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
				$mes_login = $this->exts->extract('form#ix-login-form .errors', null, 'innerText');
				$this->exts->log('message login failed: ' . $mes_login);
				if (strpos(strtolower($mes_login), 'login failed. please check the data provided') !== false) {
					$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
					$this->exts->loginFailure(1);
				} else if ($this->getElementByText('div', ['User does not exist or password incorrect.', 'Benutzer existiert nicht oder Passwort falsch.'])) {
					$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
					$this->exts->loginFailure(1);
				} else {
					$this->exts->log(__FUNCTION__ . '::Use login failed even filling 2nd time also');
					$this->exts->loginFailure();
				}
			}
		} else {
			$this->exts->log(__FUNCTION__ . '::Use login failed');
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin()
{
	if ($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		if ($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
	} else {
		$this->exts->log(__FUNCTION__ . '::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkFillTwoFactor()
{
	$two_factor_selector = '#token-modal.show input#token';
	$two_factor_message_selector = '#token-modal.show .modal-body > p';
	$two_factor_submit_selector = '#token-modal.show button#tokenLogin';

	if ($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3) {
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
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if ($this->exts->getElement($two_factor_selector) == null) {
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

private function getElementByText($selector, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
{
	$this->exts->log(__FUNCTION__);
	if (is_array($multi_language_texts)) {
		$multi_language_texts = join('|', $multi_language_texts);
	}
	// Seaching matched element
	$object_elements = $this->exts->getElements($selector, $parent_element);
	foreach ($object_elements as $object_element) {
		$element_text = trim($object_element->getAttribute('innerText'));
		// First, search via text
		// If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
		if ($is_absolutely_matched) {
			$multi_language_texts = explode('|', $multi_language_texts);
			foreach ($multi_language_texts as $searching_text) {
				if (strtoupper($element_text) == strtoupper($searching_text)) {
					$this->exts->log('Matched element found');
					return $object_element;
				} else if (stripos(strtoupper($element_text), strtoupper($searching_text)) !== false) {
					$this->exts->log('Matched element found');
					return $object_element;
				} else if (stripos(strtoupper($element_text), 'PDF') !== false) {
					$this->exts->log('Matched element found');
					return $object_element;
				}
			}
			$multi_language_texts = join('|', $multi_language_texts);
		} else {
			if (preg_match('/' . $multi_language_texts . '/i', $element_text) === 1) {
				$this->exts->log('Matched element found');
				return $object_element;
			}
		}

		// Second, is search by text not found element, support searching by regular expression
		if (@preg_match($multi_language_texts, '') !== FALSE) {
			if (preg_match($multi_language_texts, $element_text) === 1) {
				$this->exts->log('Matched element found');
				return $object_element;
			}
		}
	}
	return null;
}


public function click_element_object($element_object)
{
	try {
		$element_object->click();
	} catch (\Exception $exception) {
		$this->exts->execute_javascript('arguments[0].click();', [$element_object]);
	}
}

public function accept_cookies()
{
	if ($this->exts->exists('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner') && $this->exts->exists('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner')) {
		$this->exts->moveToElementAndClick('form#tx_cookies_accept input.cc_btn_accept_all, #uc-btn-accept-banner');
		sleep(1);
	}
}