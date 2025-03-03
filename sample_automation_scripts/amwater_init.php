public $baseUrl = 'http://www.amwater.com';
public $loginUrl = 'http://www.amwater.com';
public $invoicePageUrl = 'https://myaccount.amwater.com/accountSummary';

public $username_selector = '.auth-content-inner form input[name="identifier"]';
public $password_selector = '.auth-content-inner form input[name="credentials.passcode"]';
public $remember_me_selector = '';
public $submit_next_selector = '.auth-content-inner form input[type="submit"]';

public $submit_login_selector = '.auth-content-inner form input[type="submit"]';

public $check_login_failed_selector = 'div.messageError';
public $check_login_success_selector = '[data-target="#payment-menus"], div.userBtn'; 

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);		
	$this->exts->openUrl($this->baseUrl);
	sleep(5);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->exts->exists($this->check_login_success_selector)) {
		$this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
		$this->exts->moveToElementAndClick('button[id="login-button"]');

        sleep(5);
        $this->exts->moveToElementAndClick('button[id="submitLoginButton"]');
        sleep(10);
		$this->exts->log('form load');	

		$this->exts->capture("loadform-1");
	
		sleep(5);
		
		if($this->exts->waitTillPresent($this->username_selector)){
			$this->checkFillLogin();
			
			if($this->exts->exists('.loader .anticon-loading')){
				sleep(10);
			}
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);

		}
		

	}

	// then check user logged in or not
	if($this->exts->exists($this->check_login_success_selector)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in '.$this->exts->getUrl());
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {

			$this->exts->triggerLoginSuccess();
		}

	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed url: '.$this->exts->getUrl());
		if($this->exts->exists($this->check_login_failed_selector)) {
			$this->exts->log($this->exts->extract($this->check_login_failed_selector));
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function checkFillLogin() {

	if($this->exts->exists($this->username_selector)) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
		$this->exts->moveToElementAndClick($this->submit_next_selector);
		sleep(5);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);

		sleep(1);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}