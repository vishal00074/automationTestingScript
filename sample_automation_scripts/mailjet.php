<?php // updated captcha code
// Server-Portal-ID: 7884 - Last modified: 13.12.2024 13:45:30 UTC - User: 1

public $baseUrl = 'https://app.mailjet.com/';
public $invoiceUrl = 'https://app.mailjet.com/account/billing';
public $username_selector = '.signin-page input#login, input#login';
public $password_selector = '.signin-page input#password, input#password';
public $remember_me_selector = '.signin-page [for="remember"]';
public $submit_login_selector = '.signin-page [name="submit"], form#form-login button[type="submit"], button#form-btn:not(.is-disabled)';

public $check_login_failed_selector = '.signin-page .mjt-notification-type-error a, div#login-failed, div.mjt-label-error';
public $check_login_success_selector = '.lc-header-account-dropdown, div a[href*="dashboard"], button[data-testid*="account"]';

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
		$this->exts->execute_javascript('localStorage.setItem("check_disabled_account", "");');

		$this->exts->openUrl($this->baseUrl);
		sleep(3);
		if($this->exts->exists('button#onetrust-accept-btn-handler')) {
			$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
			sleep(2);
		}
		sleep(25);
		$this->checkFillLogin();
		sleep(20);
		$this->exts->waitTillAnyPresent(['#auth-page-container input#display-apikey-input, input#code'], 30);
		$this->checkFillTwoFactor();
		sleep(20);
	}
	
	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		if($this->exts->exists('button#onetrust-accept-btn-handler')) {
			$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
			sleep(2);
		}
		
		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoiceUrl);
		$this->exts->log($this->exts->getUrl());
		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$account_terminated = $this->exts->execute_javascript('if(localStorage.check_disabled_account.indexOf("account/terminated") > -1) true;');
		if($this->exts->getElement($this->check_login_failed_selector) != null) {
			$this->exts->loginFailure(1);
		} else if ($this->exts->urlContains('/signout') || $account_terminated) {
			$this->exts->account_not_ready();
		} else if(strpos(strtolower($this->exts->extract('p.mjt-text', null, 'innerText')), 'our account has been closed for an undisclosed reason') !== false) {
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
		$this->checkFillRecaptcha();
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(5);
		$this->exts->capture("2-login-page-filled");
		for($i=0; $i < 3;  $i++){
			$this->checkFillRecaptcha();
		}
		
	
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkFillRecaptcha($count=1) {
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
			// in case of autocaptcha solve
			$this->exts->moveToElementAndClick($this->submit_login_selector);
		} else if ($count < 4) {
			$count++;
			$this->checkFillRecaptcha($count);
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
		$this->exts->moveToElementAndClick($this->submit_login_selector);
	}
}

private function checkFillTwoFactor() {
	$two_factor_selector = '#auth-page-container input#display-apikey-input, input#code';
	$two_factor_message_selector = '#auth-page-container .form-register p, .component-PageLoginChallenge p, div[class*="BannerContent"], div[class*="PageAccountSigninMfa"] p';
	$two_factor_submit_selector = 'button[class*="PrimaryButton"], #auth-page-container .form-register button, button[data-testid="authentication-submit-button"]';

	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
			}
				$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
				$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
			}
			if($this->exts->two_factor_attempts == 2) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
			}

			if($this->exts->exists('button#onetrust-accept-btn-handler')) {
				$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
				sleep(2);
			}

			$two_factor_submits = $this->exts->getElements($two_factor_submit_selector);
			$two_factor_code =  trim($this->exts->fetchTwoFactorCode());
			if(!empty($two_factor_code) && trim($two_factor_code) != '') {
				$this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
				$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

				$this->exts->log("checkFillTwoFactor: Clicking submit button.");
				sleep(3);
				$this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

				$this->exts->moveToElementAndClick($two_factor_submit_selector);
				sleep(10);
				if ($this->exts->exists('div[class*="errorMessage"]')) {

					$this->exts->capture("wrong 2FA code error-" . $this->exts->two_factor_attempts);
					$this->exts->log('The code you entered is incorrect. Please try again.');
				}

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

private function processInvoices() {
	sleep(25);
	if($this->exts->exists('button#onetrust-accept-btn-handler')) {
		$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
		sleep(2);
	}
	$this->exts->capture("4-invoices-page");
	for ($paging_number=1; $paging_number < 25; $paging_number++) {
		$invoices = [];
		$rows = $this->exts->getElements('table > tbody > tr');
		foreach ($rows as $row) {
			$tags = $this->exts->getElements('td', $row);
			if(count($tags) >= 5 && $this->exts->getElement('a[href*="pdf/invoice"]', $tags[4]) != null) {
				$invoiceUrl = $this->exts->getElement('a[href*="pdf/invoice"]', $tags[4])->getAttribute("href");
				$invoiceName = trim($tags[4]->getAttribute('innerText'));
				$invoiceDate = trim($tags[1]->getAttribute('innerText'));
				$invoiceDate = trim(preg_replace("/\s+/", ' ', preg_replace("/[\.\,]/", ' ', $invoiceDate)));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
				
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			} else if(count($tags) >= 4 && $this->exts->getElement('a[href*="pdf/invoice"]', $tags[3]) != null) {
				$invoiceUrl = $this->exts->getElement('a[href*="pdf/invoice"]', $tags[3])->getAttribute("href");
				$invoiceName = trim($tags[3]->getAttribute('innerText'));
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceDate = trim(preg_replace("/\s+/", ' ', preg_replace("/[\.\,]/", ' ', $invoiceDate)));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
				
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			} else if(count($tags) >= 4 && $this->exts->getElement('a[href*="/subscription/invoice/download?invoiceId"]', $tags[3]) != null) {
				$invoiceUrl = $this->exts->getElement('a[href*="/subscription/invoice/download?invoiceId"]', $tags[3])->getAttribute("href");
				$invoiceName = trim($tags[3]->getAttribute('innerText'));
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceDate = trim(preg_replace("/\s+/", ' ', preg_replace("/[\.\,]/", ' ', $invoiceDate)));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
				
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			}
		}
		
		// Download all invoices
		$this->exts->log('Invoices found: '.count($invoices));
		foreach ($invoices as $invoice) {
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice['invoiceName']);
			$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
			
			$invoiceFileName = $invoice['invoiceName'].'.pdf';
			$parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j F Y','Y-m-d');
			if($parsed_date == ''){
				$this->exts->config_array["lang_code"] = 'fr';
				$parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'j F Y','Y-m-d');
			}
			$this->exts->log('Date parsed: '.$parsed_date);
			
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}


		if($this->exts->config_array["restrictPages"] == "0" && $this->exts->exists('[class*="PaginationNav"] [class*="PaginationNav"] button.is-selected + button:not(:last-child)')){
			$this->exts->moveToElementAndClick('[class*="PaginationNav"] [class*="PaginationNav"] button.is-selected + button:not(:last-child)');
			sleep(7);
		} else {
			break;
		}
	}
}