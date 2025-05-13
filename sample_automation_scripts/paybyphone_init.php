public $baseUrl = 'https://m2.paybyphone.com/';
public $loginUrl = 'https://m2.paybyphone.com/login';
public $transactionPageUrl = '';
public $invoicePageUrl = '';



public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div.alert-danger';
public $check_login_success_selector = 'a[href*="logout"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->capture('1-init-page');

	if($this->exts->exists('button#onetrust-accept-btn-handler')){
		$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
		sleep(4);
	}

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->clearChrome();
		$this->exts->openUrl($this->loginUrl);
		sleep(10);
		// select country
		if ($this->exts->exists('input[name="gender1"][value="DE"]')) {
			$this->exts->moveToElementAndClick('input[name="gender1"][value="DE"]');
			sleep(4);
			$this->exts->moveToElementAndClick('button[data-testid="submit-button"]');
			sleep(5);
		}
		$this->checkFillLogin();
		sleep(20);
	}
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {

			$this->exts->triggerLoginSuccess();
		}

	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if($this->exts->getElement($this->check_login_failed_selector) != null) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
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

private function checkFillLogin() {
	
	$this->exts->moveToElementAndClick('p.text-center button.btn-pc-accent');
	sleep(3);
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		$this->exts->type_key_by_xdotool('Home');
		sleep(1);
		$this->exts->type_key_by_xdotool('Delete');
		sleep(1);
		$this->exts->type_key_by_xdotool('Delete');
		sleep(1);
		
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(2);
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}
