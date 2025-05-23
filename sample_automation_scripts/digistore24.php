<?php // migrated
// Server-Portal-ID: 7377 - Last modified: 10.02.2025 07:03:35 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.digistore24-app.com/reports/payouts';
public $username_selector = 'input[name="login_username"]';
public $password_selector = 'input[name="login_password"]';
public $remember_me_selector = 'input[name="stay_logged_in"]';
public $submit_login_selector = 'button[name="login_login"]';
public $check_login_failed_selector = 'form#login div.alert.alert-danger';
public $check_login_success_selector = 'li.logout-button a, a[href*="logout"]';

public $isNoInvoice = true;
public $digistore_subscriptions = '';
public $sales_invoice = '0';
public $no_customer_invoices = '0';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->digistore_subscriptions = !empty($this->exts->config_array["digistore_subscriptions"]) ? trim($this->exts->config_array["digistore_subscriptions"]) : $this->digistore_subscriptions;
	$this->sales_invoice = !empty($this->exts->config_array["sales_invoice"]) ? trim($this->exts->config_array["sales_invoice"]) : $this->sales_invoice;
	$this->no_customer_invoices = !empty($this->exts->config_array["no_customer_invoices"]) ? trim($this->exts->config_array["no_customer_invoices"]) : $this->no_customer_invoices;
	$this->exts->log('subcriptionUrls - ' . $this->digistore_subscriptions);
	$this->exts->log('Begin initPortal '.$count);
	//Load cookies
		$this->exts->loadCookiesFromFile();
	sleep(1);
	
	if(trim($this->username) == '-' || trim($this->username) == '"-"' || trim($this->username) == '.' || trim($this->username) == '"."') {
		if($this->digistore_subscriptions != ''){
			$this->exts->log('subcriptionUrls in condition - ' . $this->digistore_subscriptions);
			$subcription_urls = explode(',', $this->digistore_subscriptions);
			$this->exts->log('subcriptionUrls - ' . count($subcription_urls));
			foreach ($subcription_urls as $key => $subcription_url) {
				$subcription_url = trim($subcription_url);
				if (filter_var($subcription_url, FILTER_VALIDATE_URL) === FALSE) {
					$this->exts->log('INVALID URL - ' . $subcription_url);
					continue;
				}
				$this->exts->openUrl($subcription_url);
				$this->processSubcriptionInvoices();
			}
			
			if($this->isNoInvoice){
				$this->exts->no_invoice();
			}
			$this->exts->success();
			return;
		}
	} else {
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		$this->exts->capture('1-init-page');
		// If user hase not logged in from cookie, clear cookie, open the login url and do login
		if($this->exts->getElement($this->check_login_success_selector) == null) {
			$this->exts->log('NOT logged via cookie');
			$this->exts->clearCookies();
			$this->exts->openUrl($this->baseUrl);
			sleep(15);
			
			$this->exts->moveToElementAndClick('button[id="CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll"]');
			sleep(5);
			$this->checkFillLogin();
			sleep(7);
			if(strrpos($this->exts->extract($this->check_login_failed_selector), 'LOGCAP') !== false){
				$this->exts->openUrl($this->baseUrl);
				sleep(15);
				$this->checkFillLogin();
				sleep(7);
				if(strrpos($this->exts->extract($this->check_login_failed_selector), 'LOGCAP') !== false){
					$this->clearChrome();
					$this->exts->openUrl($this->baseUrl);
					sleep(10);
					$this->checkFillLogin();
					sleep(7);
				}
			}
			sleep(10);
			$this->checkFillTwoFactorWithPushNotify();
			$this->checkFillTwoFactor();
			$this->exts->switchToDefault();
			sleep(1);
		}
		$this->doAfterLogin();
	}
}

private function checkFillLogin() {
	if($this->exts->exists('iframe.login-iframe')){
		$this->switchToFrame('iframe.login-iframe');
	}

	if(!filter_var($this->username, FILTER_VALIDATE_EMAIL)){
		$this->exts->log('Username is not a valid email address.');
		$this->exts->loginFailure(1);
	}
	
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
		$this->checkFillRecaptcha(true);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->click_element($this->submit_login_selector);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

public function switchToFrame($query_string)
{
	$this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
	$frame = null;
	if (is_string($query_string)) {
		$frame = $this->exts->queryElement($query_string);
	}

	if ($frame != null) {
		$frame_context = $this->exts->get_frame_excutable_context($frame);
		if ($frame_context != null) {
			$this->exts->current_context = $frame_context;
			return true;
		}
	} else {
		$this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
	}

	return false;
}

private function checkFillRecaptcha($tryagain_if_expired=true, $count=1) {
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
				sleep(10);
			}
		} else {
			$count++;
			if($count <= 3 && $tryagain_if_expired){
				// If recaptcha expired, call again
				$this->checkFillRecaptcha($tryagain_if_expired, $count);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
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

private function checkFillTwoFactorWithPushNotify() {
	$two_factor_selector = 'input[name="two_factor_auth_login_code"][type="hidden"]';
	$two_factor_message_selector = 'div.method_confirm_code';
	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		
		if($this->exts->getElement($two_factor_message_selector) != null){
			$total_2fa = count($this->exts->getElements($two_factor_message_selector));
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < $total_2fa; $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . '. Please input "OK" after responded email!';
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . '. Please input "OK" after responded email!';;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}
		
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '' && strtolower($two_factor_code) == 'ok') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code. ".$two_factor_code);
			sleep(15);
			
			if(!$this->exts->exists($two_factor_message_selector) && !$this->exts->exists($two_factor_selector)){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->two_factor_attempts++;
				$this->exts->notification_uid = '';
				$this->checkFillTwoFactorWithPushNotify();
			} else {
				$this->exts->log("Two factor can not solved");
			}
		} else if ($this->exts->two_factor_attempts < 3) {
			$this->exts->two_factor_attempts++;
			$this->exts->notification_uid = '';
			$this->checkFillTwoFactorWithPushNotify();
		} else {
			$this->exts->log("Not received two factor code");
		}
	}
}

private function checkFillTwoFactor() {
	$this->exts->waitTillPresent('iframe.login-iframe', 10);

	if($this->exts->exists('iframe.login-iframe')){
		$this->switchToFrame('iframe.login-iframe');
	}
	$two_factor_selector = 'input[name="two_factor_auth_login_code"]:not([type="hidden"])';
	$two_factor_message_selector = 'div.method_confirm_code';
	$two_factor_submit_selector = 'button[name="two_factor_auth_login_login"]';

	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}

		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->moveToElementAndClick('input[name="trust_device"]');
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if($this->exts->getElement($two_factor_selector) == null){
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

private function doAfterLogin() {
	// then check user logged in or not
	if($this->exts->exists($this->check_login_success_selector)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		$this->exts->moveToElementAndClick('li.views .dropdown-toggle');// open account dropdown
		sleep(3);
		$this->exts->capture("3-accounts-checking");
		if($this->no_customer_invoices == '1'){
			$accounts = $this->exts->getElementsAttribute('li.views .dropdown-menu a[href*="active_view=affiliate"], li.views .dropdown-menu a[href*="active_view=vendor"]', 'href');
		} else {
			$accounts = $this->exts->getElementsAttribute('li.views ul.dropdown-menu li a', 'href');
		}
		$this->exts->log('ACCOUNTS found: '. count($accounts));
		if(count($accounts) > 1){
			foreach ($accounts as $account_url) {
				$this->exts->log('SWITCH account: '. $account_url);
				$this->exts->openUrl($account_url);
				sleep(15);
				$this->exts->openUrl('https://www.digistore24-app.com/reports/payouts');
				$this->processInvoices();
			}
		} else {
			$this->exts->openUrl('https://www.digistore24-app.com/reports/payouts');
			$this->processInvoices();
		}
		
		if($this->sales_invoice == "1"){
			$this->exts->openUrl('https://www.digistore24-app.com/vendor/reports/transactions');
			$this->processSaleInvoices();
		}
		
		if($this->digistore_subscriptions != ''){
			$subcription_urls = explode(',', $this->digistore_subscriptions);
			$this->exts->log('subcriptionUrls - ' . count($subcription_urls));
			foreach ($subcription_urls as $key => $subcription_url) {
				$subcription_url = trim($subcription_url);
				if (filter_var($subcription_url, FILTER_VALIDATE_URL) === FALSE) {
					$this->exts->log('INVALID URL - ' . $subcription_url);
					continue;
				}
				$this->exts->openUrl($subcription_url);
				$this->processSubcriptionInvoices();
			}
		}
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->capture("login-failed-after-2fa");
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());


		$isTwoFAError = $this->exts->execute_javascript('document.body.innerHTML.includes("The code is incorrect. Please enter the code from your authenticator app.")');
		$this->exts->log('isTwoFAError '. $isTwoFAError);
		if($this->exts->exists('iframe.login-iframe')){
			$this->switchToFrame('iframe.login-iframe');
		}
		if ($this->exts->getElementByText('form#login div.alert.alert-danger', ['not active'], null, false) != null) {
			$this->exts->account_not_ready();
		}
		$logged_in_failed_selector = $this->exts->getElementByText('form#login div.alert.alert-danger', ['kennwort','password', 'Digistore24 ID', 'digistore24-id', 'submit the form again'], null, false);
		if($logged_in_failed_selector != null) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}


private function processInvoices($paging_count=1) {
	sleep(10);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table#view_payouts_view_table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('td.id a[href*=".pdf"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = trim(preg_replace("/[^\d\,\.]/", '', $this->exts->extract('td:nth-child(4)', $row, 'text')));
			$invoiceDate = trim($this->exts->extract('td:nth-child(3)', $row, 'text'));
			$invoiceAmount = preg_replace("/[^\d\,\.]/", '', $this->exts->extract('td:nth-child(6)', $row, 'text')) . ' EUR';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
	
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 20 &&
		$this->exts->getElement('.pagination li.active + li:not(.disabled) a') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('.pagination li.active + li:not(.disabled) a');
		sleep(5);
		$this->processInvoices($paging_count);
	}
}

private function processSubcriptionInvoices() {
	sleep(10);
	if($this->exts->exists('.invoice.toggler_open, .invoice_download_headline.toggler_open')){
		$this->exts->moveToElementAndClick('.invoice.toggler_open, .invoice_download_headline.toggler_open');
		sleep(3);
	}
	$this->exts->capture("4-subcription-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('.tglr_content li');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('a[href*="/invoice/"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = trim(end(explode('.', $download_link->getText())));
			$invoiceDate = trim(end(explode('-', $row->getText())));
			$invoiceAmount = '';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}

private function processSaleInvoices($paging_count=1) {
	sleep(10);
	$this->exts->capture("4-sale-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table#view_transactions_of_merchant_view_table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('td.id a[href*="/invoice/"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = trim($this->exts->extract('td:nth-child(5)', $row, 'text'));
			$invoiceDate = trim($this->exts->extract('td:nth-child(3)', $row, 'text'));
			$invoiceAmount = preg_replace("/[^\d\,\.]/", '', $this->exts->extract('td:nth-child(9)', $row, 'text')) . ' EUR';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
	
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 20 &&
		$this->exts->getElement('.pagination li.active + li:not(.disabled) a') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('.pagination li.active + li:not(.disabled) a');
		sleep(5);
		$this->processSaleInvoices($paging_count);
	}
}