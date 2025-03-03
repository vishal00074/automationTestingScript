public $baseUrl = 'https://www.laposte.fr/tableau-de-bord';
public $loginUrl = 'https://boutique.laposte.fr/authentification';
public $invoicePageUrl = 'https://boutique.laposte.fr/mescommandes';

public $username_selector = 'form#formConnect input#j_username, #login-form #username';
public $password_selector = 'form#formConnect input#formPass, #login-form #password';
public $remember_me_selector = '';
public $submit_login_selector = 'form#formConnect input#authentificationEnvoyer, #login-form #submit';

public $check_login_failed_selector = '#login-form .message.error';
public $check_login_success_selector = 'a[href="/deconnexionPopin"], a[data-switch-href="/deconnexion"], a[href*="/logout"], div.header-account-connected, button#auto-btn-user-link, a[href*="/commande-detail/telecharger-facture/"]';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);		
	$this->exts->openUrl($this->baseUrl);
	sleep(1);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->baseUrl);
		sleep(15);

		for ($i =0; $i < 3; $i++) {
			$msg = strtolower($this->exts->extract('div#main-frame-error p[jsselect="summary"]', null, 'innerText'));
			$msg1 = trim(strtolower($this->exts->extract('body', null, 'innerText')));
			if (strpos($msg, 'took too long to respond') !== false || ($msg1 == '404 - not found')) {
				$this->exts->refresh();
				sleep(15);
				$this->exts->capture('after-refresh-cant-be-reach-' . $i);
			} else {
				break;
			}
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

private function checkFillLogin() {
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
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

		$this->exts->waitTillPresent('input#li-antibot-token');
		if ($this->exts->exists('input#li-antibot-token')) {
			$this->exts->execute_javascript("document.querySelector('input#li-antibot-token').value = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXF1ZXN0SWQiOiI4M2IxOTMxM2IwOWU0YzQzYWQxYzkwOWRlZGIzYTA3MCIsImludmlzaWJsZUNhcHRjaGFJZCI6IjMyYTI2NGNjLThkZmYtNDNmNS1iMTQ2LTVjYmMzN2MwN2ExMCIsImFudGlib3RJZCI6ImQyNzE1NjUwMjk0OTQ4MjJhNDQ1ZWQxYTY1NjhkZWUzIiwiYW50aWJvdE1ldGhvZCI6IkNBUFRDSEEiLCJleHAiOjE3Mzc1MzEwMjgsImlhdCI6MTczNzUzMDcyOCwiY2FwdGNoYUlkIjoiYzk5Y2ZjMWEzMDZlNDg4YjllZTM1ODBlY2U5YmQyYTkifQ.QpzZ52-c6fDmKGfhgAOHMxGMrXmRk_ofpIZ3xkPjqiQ';");
		}

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(15);


		if ($this->exts->urlContains('faviconLaposte.ico')){
			$this->exts->openUrl($this->invoicePageUrl);
		} 
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}