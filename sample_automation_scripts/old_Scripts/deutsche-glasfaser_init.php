public $baseUrl = 'https://kundenportal.deutsche-glasfaser.de/kundenportal';
public $loginUrl = 'https://id.deutsche-glasfaser.de/accounts/login/';
public $invoicePageUrl = 'https://kundenportal.deutsche-glasfaser.de/kundenportal/#/home/rechnung/rechnungen';

public $username_selector = 'input[type="email"], input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '#id_remember';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'form[action="/login/"] div[role="alertdialog"]';
public $check_login_success_selector = 'a[href="/authentication/logout"], .logout-wrapper, a[href="/logout/"], div.sc-contract-selection-item-text';

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

	$this->close_cookie_alert();

	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);

		$this->close_cookie_alert();
		$this->checkFillLogin();
        sleep(15);

		
        if($this->exts->getElement($this->check_login_success_selector) == null) {
			if($this->exts->exists('dg-button[data-sentry-element="DgButton"]')){
				$this->exts->log('Click on Login Button');
				$this->exts->moveToElementAndClick('dg-button[data-sentry-element="DgButton"]');
			}
			sleep(10);
        }

		$this->close_cookie_alert();
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
		
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'dieses feld wird ben') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'gib eine') !== false && strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'mail adresse an') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('[id*=mat-error]', null, 'innerText')), 'die angegebenen zugangsdaten stimmen nicht') !== false || strpos(strtolower($this->exts->extract('[id*=mat-error]', null, 'innerText')), 'access data do not match') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('[id*=mat-mdc-error]', null, 'innerText')), 'geben sie eine gültige') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos($this->exts->extract('div[id*="toast-danger"] div.font-normal font ', null, 'innerText'), 'Die E-Mail-Adresse und/oder das Passwort ist falsch.') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos($this->exts->extract('div[id*="toast-danger"] div.font-normal', null, 'innerText'), 'Die E-Mail-Adresse und/oder das Passwort ist falsch.') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos(strtolower($this->exts->extract('div[id*="toast-danger"] div.font-normal', null, 'innerText')), 'The email address and/or password is incorrect.') !== false) {
			$this->exts->loginFailure(1);
		}
		 elseif ($this->exts->exists('div.no-contracts')) {
			$this->exts->account_not_ready();
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
	if(!filter_var($this->username, FILTER_VALIDATE_EMAIL)){
		$this->exts->log('Username is not a valid email address.');
		$this->exts->loginFailure(1);
	}
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);
		
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(2);
		
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

function close_cookie_alert() {
	$str = "var div = document.querySelector('div#usercentrics-root'); if (div != null) {  div.style.display = \"none\"; }";
	$this->exts->execute_javascript($str);
	sleep(2);
}