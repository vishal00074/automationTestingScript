<?php // updated password failed selector added base url
// Server-Portal-ID: 10968 - Last modified: 12.02.2025 06:04:52 UTC - User: 1


public $baseUrl = 'https://www.kleinanzeigen.de/';
public $loginUrl = 'https://auth.kleinanzeigen.de/login/';
public $invoicePageUrl = 'https://www.ebay-kleinanzeigen.de/m-rechnungen.html';

public $username_selector = '#login-form input[name="email"]';
public $password_selector = '#login-form input[name="password"]';
public $submit_login_selector = '#login-form [type="submit"]';

public $check_login_failed_selector = 'div:not([id*="cookie"]).outcomebox-warning.l-container-row';
public $isNoInvoice = true; 
/**
	* Entry Method thats called for a portal
	* @param Integer $count Number of times portal is retried.
	*/
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);

	$this->exts->openUrl($this->baseUrl);
    sleep(5);
	$this->exts->capture('1-init-page');
	
	$this->exts->loadCookiesFromFile();


	$this->exts->waitTillPresent('#consentBanner #gdpr-banner-accept', 10);
	if ($this->exts->exists('#consentBanner #gdpr-banner-accept')) {
		$this->exts->click_by_xdotool('#consentBanner #gdpr-banner-accept');
	}

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->isLoggedin()) {
		$this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		$this->exts->waitTillPresent('#consentBanner #gdpr-banner-accept', 10);
		if ($this->exts->exists('#consentBanner #gdpr-banner-accept')) {
			$this->exts->click_by_xdotool('#consentBanner #gdpr-banner-accept');
		}
		
		$this->checkFillLogin();
		sleep(15);
		if ($this->exts->exists($this->username_selector)) {
			$this->checkFillLogin();
			sleep(5);
		}

		$this->exts->waitTillPresent('img[src*="new-device-login"]', 10);
		if($this->exts->exists('img[src*="new-device-login"]')){
			$this->exts->two_factor_notif_msg_en = 'Ebay has sent you an email Please confirm the login attempt in the email.'. "\n>>>Enter \"OK\" after confirmation.";
			$this->exts->two_factor_notif_msg_de = 'Haben Ebay dir eine E-Mail geschickt. Bitte bestatige uns den Loginversuch in der E-Mail' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
			
			$this->exts->notification_uid = "";
			$two_factor_code = trim($this->exts->fetchTwoFactorCode());
			if(!empty($two_factor_code) && trim($two_factor_code) != '') {
				$this->exts->moveToElementAndClick('a[href*="/m-einloggen.html"]');
				$this->checkFillLogin();
			} else {
				$this->exts->log("Not received two factor code");
			}
		}
	}


	// then check user logged in or not
	if($this->isLoggedin()) {
	
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		$this->exts->waitTillPresent('#consentBanner #gdpr-banner-accept', 10);
		if ($this->exts->exists('#consentBanner #gdpr-banner-accept')) {
			$this->exts->click_by_xdotool('#consentBanner #gdpr-banner-accept');
		}

		$this->exts->openUrl($this->invoicePageUrl);
		$this->processInvoices();

		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}

		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');

		if($this->exts->exists('//span[text()="Das eingegebene Passwort ist leider nicht korrekt. Bitte überprüfe es."]') || $this->exts->exists('span[id=":form-field-message-:r0:"]')) {
			$this->exts->loginFailure(1);
		} 
		
		if(strpos($this->exts->extract($this->check_login_failed_selector), 'deine E-Mail-Adresse oder dein Passwort falsch eingegeben') !== false) {
			$this->exts->loginFailure(1);
		} else if(stripos($this->exts->extract('#login-form span.text-error'), 'Das eingegebene Passwort ist leider nicht korrekt') !== false) {
			$this->exts->loginFailure(1);
		} else if (stripos($this->exts->extract('#login-form span.text-error'), 'Unfortunately, the password you entered is incorrect. Please check it.') !== false) {
            $this->exts->loginFailure(1);
        } else if(stripos($this->exts->extract('#login-data .outcomebox-warning p'), 'E-Mail Adresse ist noch nicht registriert') !== false) {
			$this->exts->account_not_ready();
		} else if(stripos($this->exts->extract('#login-data .outcomebox--body p'), 'ein neues Passwort zu erstelle') !== false) {
			$this->exts->account_not_ready();
		} else if(filter_var($this->username, FILTER_VALIDATE_EMAIL) == false) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {

	$this->exts->waitTillPresent($this->username_selector, 10);

	
	if($this->exts->exists($this->username_selector)) {
		$this->exts->capture('login-page-found');
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(2);

		$this->exts->capture("2-login-page-filled");

		if ($this->exts->exists($this->submit_login_selector)) {
			$this->exts->moveToElementAndClick($this->submit_login_selector);
			sleep(5);
		}
		$this->exts->capture('login-page-submit');
		
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
				$this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
			}
			sleep(2);
			$this->exts->capture('recaptcha-filled');

			// Step 2, check if callback function need executed
			$gcallbackFunction = $this->exts->execute_javascript('
				if(document.querySelector("[data-callback]") != null){
					return document.querySelector("[data-callback]").getAttribute("data-callback");
				}

				var result = ""; var found = false;
				function recurse (cur, prop, deep) {
					if(deep > 5 || found){ return;}console.log(prop);
					try {
						if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
						} else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
							for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
						}
					} catch(ex) { console.log("ERROR in function: " + ex); return; }
				}

				recurse(___grecaptcha_cfg.clients[0], "", 0);
				return found ? "___grecaptcha_cfg.clients[0]." + result : null;
			');
			$this->exts->log('Callback function: '.$gcallbackFunction);
			if($gcallbackFunction != null){
				$this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
				sleep(3);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}

private function isLoggedin()
{
	$this->exts->waitTillPresent('#user-logout',10);
	return $this->exts->exists('#user-logout');
}

private function processInvoices() {
	$this->exts->waitTillPresent('.InvoicesList tr', 30);
	$this->exts->capture("4-invoices-page");

	$rows = $this->exts->count_elements('.InvoicesList tr');
	for ($i=0; $i < $rows; $i++) { 
		$row = $this->exts->getElements('.InvoicesList tr')[$i];
		$invoice_button = $row->querySelector('a.downloadlink');
		if($invoice_button != null){
			$this->isNoInvoice = false;
			$invoice_name = $invoice_button->getAttribute('innerText');
			$invoice_date = $this->exts->extract('td:nth-child(5)', $row);
			$invoice_amount = $this->exts->extract('td:nth-child(3)', $row);
			$invoice_amount = preg_replace('/[^\d\.\,]/', '', $invoice_amount) . ' EUR';
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice_name);
			$this->exts->log('invoiceDate: '.$invoice_date);
			$this->exts->log('invoiceAmount: '.$invoice_amount);

			if($this->exts->invoice_exists($invoice_name)){
				$this->exts->log('Invoice Existed: '.$invoice_name);
			} else {
				$this->exts->click_element($invoice_button);
				sleep(5);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoice_name. '.pdf');
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoice_name, $invoice_date, $invoice_amount, $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoice_name);
				}
			}
		}
	}
}