<?php // migrated and updated login code download code
// Server-Portal-ID: 109840 - Last modified: 22.01.2024 13:28:58 UTC - User: 1

public $baseUrl = 'https://www2.telenet.be/';
public $loginUrl = 'https://login.prd.telenet.be/openid/login'; 
public $invoicePageUrl = 'https://www2.telenet.be/content/www-telenet-be/nl/klantenservice/raadpleeg-je-aanrekening';

public $username_selector = 'input#input29, input#j_username';
public $password_selector = 'input#input69, input.password-with-toggle';
public $remember_me_selector = '';
public $submit_login_selector = 'input[type="submit"]';

public $check_login_failed_selector = '.input--error';
public $check_login_success_selector = 'div[class*="manage-profile-details"], .link--logout, .login-profile, app-menu-links a.button';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
//https://www2.telenet.be/residential/nl/mytelenet/profiel
 // a[class*="login-v2-headin"] > i li > a[href*="residential/nl/mytelenet/profiel"]
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);	
	$this->exts->openUrl($this->baseUrl);
	sleep(1);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(15);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->checkLoggedIn()) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->openUrl($this->baseUrl);
		sleep(15);
		if($this->exts->exists('div.banner-actions-container > button#onetrust-accept-btn-handler')){
			$this->exts->moveToElementAndClick('div.banner-actions-container > button#onetrust-accept-btn-handler');
			sleep(5);
		}
		$login_dropdown_selector = 'div.nav-section__action-bar div.header-login a.button--login-header, tg-telenet-login a.login-v2-heading';
		if($this->exts->exists($login_dropdown_selector)){
			$this->exts->click_by_xdotool($login_dropdown_selector);
			sleep(15);
		}
		

		if ($this->exts->exists($login_dropdown_selector) && !$this->exts->exists($this->username_selector)) {
			if($this->exts->exists('button#onetrust-accept-btn-handler')){
				$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
				sleep(5);
			}

			$this->exts->execute_javascript("document.querySelectorAll(arguments[0])[0].click();",[$login_dropdown_selector]);
			sleep(1);
			$this->exts->execute_javascript("document.querySelector('a.button--login-v2-header').click()");
			
			sleep(15);
			$this->exts->capture('2-login-page-javascript-click');
		}

		if ($this->exts->exists($login_dropdown_selector) && !$this->exts->exists($this->username_selector)) {
			if($this->exts->exists('button#onetrust-accept-btn-handler')){
				$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
				sleep(5);
			}
			$this->exts->click_by_xdotool($login_dropdown_selector);
			sleep(1);
			$this->exts->click_by_xdotool('a.button--login-v2-header');
			sleep(15);
		}

		$this->checkFillLogin();
		sleep(10);
	}
	
	if($this->checkLoggedIn()) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		$this->processMultiAccount();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false 
			|| strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'wachtwoord zijn verkeerd') !== false) {
			$this->exts->loginFailure(1);
		} else if($this->exts->exists('[message-group-name="profile-signup"] [data-ng-click="loginMigrationCtrl.goToNextStep()"]')){
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	$this->exts->waitTillPresent($this->username_selector);
	if($this->exts->getElement($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);
		$this->exts->log("Click on Keep me signed in");
		
		$this->exts->moveToElementAndClick('input[id="input37"]');
		sleep(5);

		$this->exts->moveToElementAndClick($this->submit_login_selector);
		

		$this->exts->waitTillPresent('div[data-se="okta_password"] > a[class="button select-factor link-button"]', 10);
		if ($this->exts->exists('div[data-se="okta_password"] > a[class="button select-factor link-button"]')) {
			$this->exts->moveToElementAndClick('div[data-se="okta_password"] > a[class="button select-factor link-button"]');
			sleep(5);
		}


		$this->exts->waitTillPresent($this->password_selector);
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(2);


		$this->exts->capture("2-login-page-filled");
		$this->checkFillRecaptcha();
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(5);
		
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
						if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
						if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
						} else { deep++;
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
				sleep(10);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}

private function checkLoggedIn() {

	$this->exts->openUrl('https://www2.telenet.be/residential/nl/mytelenet/profiel');
	$isLoggedIn = false;
	$this->exts->waitTillPresent($this->check_login_success_selector, 20);
	if($this->exts->exists($this->check_login_success_selector)){
		$isLoggedIn = true;
	}
	return $isLoggedIn;
}





private function processMultiAccount(){
	sleep(15);
	$this->exts->moveToElementAndClick('div.MultipleAccounts.ng-scope');
	sleep(5);

	$account_len = count($this->exts->getElements('.MultipleAccounts span.selectOption'));
	$this->exts->log('Accounts Found: '.$account_len);
	$accounts = [];
	if ($account_len > 0) {
		for ($i = 0; $i < $account_len; $i++) {
			
			for ($j = 0; $j < $account_len; $j++) {
				$account_name = $this->exts->getElements('.MultipleAccounts span.selectOption')[$j]->getAttribute('innerText');
				if(in_array($account_name, $accounts) == false){

					$acc_row = $this->exts->getElements('.MultipleAccounts span.selectOption')[$j];
					try{
						$this->exts->log('Click Accounts button ' . $acc_row->getAttribute('innerText'));
						$acc_row->click();
					} catch(\Exception $exception){
						$this->exts->log('Click Accounts button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$acc_row]);
					}
					$this->processInvoices();
					sleep(2);
					$this->exts->moveToElementAndClick('div.MultipleAccounts.ng-scope');
					array_push($accounts, $account_name);
					break;
				}
			}
			
		}
	} else {
		$this->processInvoices();
	}
}
private function processInvoices() {
	sleep(25);
	
	$this->exts->capture("4-invoices-page");
	// date //  data-testid="billing-overview-invoice-date"
	//data-testid="billing-overview-amount-value"
	// wink-button[data-testid="cost-breakdown-download-invoice"]
	//h3[data-testid="cost-breakdown-title"]
	//div[data - testid = "billing-overview-paid-invoice"] > div[data - testid = "billing-overview-amount-value]:nth-child(1)
	//data-testid="billing-overview-paid-invoice-year"
	$rows = $this->exts->getElements('div[data-testid="billing-overview-paid-invoice"]');
	// Download all invoices
	$this->exts->log('Invoices found: ' . count($rows));
	foreach ($rows as $row) {
		$action_button = $this->exts->getElement('div[class="display--flex justify-content--between"]', $row);
		$this->exts->waitTillPresent('div[data-testid="billing-overview-paid-invoice"]', 25);

		$invoiceAmount = $this->exts->extract('div[data-testid="billing-overview-amount-value]', $row);

		$invoiceDate =  $this->exts->extract('span[data-testid="billing-overview-invoice-date]', $row);

		$invoiceListing = $this->exts->getUrl();

		$action_button->click();

		$this->exts->waitTillPresent('h3[data-testid="cost-breakdown-title"]');

		$invoiceUrl = '';
		$invoiceName = $this->exts->extract('h3[data-testid="cost-breakdown-title"]');

		$this->isNoInvoice = false;

		$this->exts->log('invoiceName: ' . $invoiceName);
		$this->exts->log('invoiceDate: ' . $invoiceDate);
		$this->exts->log('invoiceAmount: ' . $invoiceAmount);
		$this->exts->log('invoiceUrl: ' . $invoiceUrl);

		$downloadBtn = 'wink-button[data-testid="cost-breakdown-download-invoice"]';

		$downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceName);
		if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
			$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
		}
		$this->exts->openUrl($invoiceListing);
		sleep(5);
	}


		
}