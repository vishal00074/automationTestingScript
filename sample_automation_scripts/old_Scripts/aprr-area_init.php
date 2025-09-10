public $baseUrl = 'https://espaceclient.aprr.fr/aprr/Pages/connexion.aspx';
public $loginUrl = 'https://espaceclient.aprr.fr/aprr/Pages/connexion.aspx';
public $invoicePageUrl = 'https://www.fulli.com/customer-space/invoices';

public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = 'input[name="rememberMe"]';
public $submit_login_selector = 'input[type="submit"]';

public $check_login_failed_selector = '.erreur_blanc, .login-pf-header ~ div div.alert.alert-error, .Messages-group.-error.-closable.js--closable ';
public $check_login_success_selector = 'button.account-menu-link.UserLink, a[href*="/user/logout"]';

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
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->exts->exists($this->check_login_success_selector)) {
		$this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
		sleep(5);
		$this->exts->openUrl($this->loginUrl);
		sleep(5);
		$this->checkFillLogin();
		sleep(5);
	}

	$this->exts->waitTillPresent($this->check_login_success_selector);

	if($this->exts->exists($this->check_login_success_selector)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed url: '.$this->exts->getUrl());
		if ($this->exts->getElementByText('div.alert-error:not([style="display: none;"])', ['password', 'passwort'], null, false)) {
			$this->exts->loginFailure(1);
		}
		if ($this->exts->exists('iframe#fancybox-frame')) {
			$this->exts->switchToFrame('iframe#fancybox-frame');
		}

		$mesg = strtolower($this->exts->extract('div#divFancyMessagesContent', null, 'innerText'));
		$this->exts->log($mesg);
		if (strpos($mesg, 'wachtwoord zijn onjuist') !== false
				|| strpos($mesg, 'passe sont incorrects') !== false || strpos($mesg, 'es ist ein fehler aufgetreten, bitte versuchen sie es noch einmal') !== false || strpos($mesg, 'une erreur est survenue, merci de') !== false || strpos($mesg, 'uw gebruikersnaam en/of wachtwoord zijn onjuist') !== false) {
			$this->exts->loginFailure(1);
		} else if ($this->exts->exists($this->check_login_failed_selector)) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}



private function checkFillLogin() {
	$this->exts->log(__FUNCTION__ .'Begin Fill Login');
	$this->exts->capture(__FUNCTION__);

    $this->exts->waitTillPresent($this->password_selector);

	if($this->exts->exists($this->password_selector)) {
		sleep(2);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}