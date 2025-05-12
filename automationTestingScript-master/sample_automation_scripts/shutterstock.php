<?php
public $baseUrl = 'https://www.shutterstock.com/account/purchases';
public $invoicePageUrl = 'https://www.shutterstock.com/account/purchases';

public $username_selector = 'span[data-type="analytics"] input[name="username"], input[data-test-id="email-input"], input[name="username"]';
public $password_selector = 'span[data-type="analytics"] input[type="password"], input[data-test-id="password-input"] ,[type="password"]';
public $submit_login_selector = 'div[class*="LoginForm"] button[type="submit"], button[data-test-id="login-form-submit-button"], button[class*="MuiButtonBase-root"][data-test-id="login-form-submit-button"]';
public $check_login_success_selector = '[data-automation="AccountNavMenu_div"], a[href*="/logout"], li a[href*="/account/profile"], div[data-automation="user_Avatar"]';

public $restrictPages = 3;
public $isNoInvoice = true;
public $invoice_language = 'de';
public $hasMultipleWorkspace = false;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	sleep(1);
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	$this->invoice_language = isset($this->exts->config_array["invoice_language"]) ? $this->exts->config_array["invoice_language"] : 'de';
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
    $this->exts->waitTillPresent($this->check_login_success_selector, 10);
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->clearChrome();
		$this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillPresent('a[data-automation="loginButton"]', 10);
		if($this->exts->exists('a[data-automation="loginButton"]')){
			$this->exts->moveToElementAndClick('a[data-automation="loginButton"]');
		}

        $this->exts->waitTillPresent('iframe#login-iframe', 20);
		if($this->exts->exists('iframe#login-iframe')){
			$this->switchToFrame('iframe#login-iframe');
			sleep(5);
			$this->checkFillLogin();	
		}
		
        $this->exts->waitTillPresent('form[data-test-id="otp-form"] input', 5);
		$this->checkFillTwoFactor();
		
        sleep(5);
		if ($this->exts->allExists(['span[dataicon="user"]', 'button[data-test-id="workspace-continue"]'])) {
			$this->hasMultipleWorkspace = true;
			$this->exts->moveToElementAndClick('span[dataicon="user"]');
			sleep(1);
			$this->exts->moveToElementAndClick('button[data-test-id="workspace-continue"]');
			sleep(10);
		}
		$this->exts->switchToDefault();
		
	}

	if($this->exts->exists('.MuiGrid-item button.MuiIconButton-root')){
		$this->exts->moveToElementAndClick('.MuiGrid-item button.MuiIconButton-root');
		sleep(5);
	}
	
	if($this->exts->exists('[data-automation="SiteHeader_ProfileButton"]')){
		$this->exts->capture("before-click-ProfileButton");
		$this->exts->moveToElementAndClick('[data-automation="SiteHeader_ProfileButton"]');
		sleep(5);
	}
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		if($this->exts->exists('button[id*="-accept-btn-handler"]')) {
			$this->exts->moveToElementAndClick('button[id*="-accept-btn-handler"]');
			sleep(1);
		}
		
		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		sleep(20);
		if(($this->exts->urlContains('premier.shutterstock') || $this->exts->urlContains('enterprise.shutterstock')) && $this->exts->exists('a[href*="/account/invoices"]')){
			$this->exts->moveToElementAndClick('a[href*="/account/invoices"]');
			sleep(20);
			$this->exts->moveToElementAndClick('a[href="/account/invoices/paid?"]');
			sleep(20);
			$this->processInvoicesPremier();
		} else {
			$this->processInvoices();
		}

		if ($this->hasMultipleWorkspace) {
			$this->exts->moveToElementAndClick('div[data-automation="user_Avatar"]');
			sleep(5);
			$this->exts->moveToElementAndClick('div[data-automation="ProfileDrawer_workspaceDisplayName"]');
			sleep(20);
			$handles = $this->exts->webdriver->getWindowHandles();
			$this->exts->webdriver->switchTo()->window(end($handles));
			if(($this->exts->urlContains('premier.shutterstock') || $this->exts->urlContains('enterprise.shutterstock')) && $this->exts->exists('a[href*="/account/invoices"]')){
				$this->exts->moveToElementAndClick('a[href*="/account/invoices"]');
				sleep(20);
				$this->exts->moveToElementAndClick('a[href="/account/invoices/paid?"]');
				sleep(20);
				$this->processInvoicesPremier();
			} else {
				$this->processInvoices();
			}
		}
		
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$this->switchToFrame('iframe#login-iframe');
		if(stripos($this->exts->extract('div.MuiAlert-message'), 'incorrect username/password') !== false) {
			$this->exts->loginFailure(1);
		} else if($this->exts->allExists(['button[data-test-id="reset-password-submit"]', 'div[class*="ForgotCredentialsForm_resetAlerts"]'])){
			$this->exts->account_not_ready();
		} else if(stripos($this->exts->extract('div.MuiAlert-message'), ' your contributor account') !== false){
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function checkFillLogin() {
	if($this->exts->getElement($this->username_selector) != null) {

		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);
		
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(3);
		$this->checkFillRecaptcha();

		$this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
		    $this->exts->click_by_xdotool($this->submit_login_selector);
        }
		
        $this->exts->waitTillPresent('iframe[src*="/recaptcha/api2/anchor?"]', 10);
		if ($this->exts->exists('iframe[src*="/recaptcha/api2/anchor?"]')) {

			// $this->exts->log("Enter Password");
			// $this->exts->moveToElementAndType($this->password_selector, $this->password);
			sleep(3);
			$this->checkFillRecaptcha();

			$this->exts->capture("2-login-page-filled");
			$this->exts->click_by_xdotool($this->submit_login_selector);
		}
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
		$this->checkFillLoginUndetected();
	}
}


private function checkFillLoginUndetected()
{
	$this->exts->log('Fill form by using Tab');

    for ($i = 0; $i < 11; $i++) {
        $this->exts->type_key_by_xdotool("Tab");
        sleep(1);
    }
    $this->exts->log("Enter Username");
    $this->exts->type_text_by_xdotool($this->username);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(2);
    $this->exts->log("Enter Password");
    $this->exts->type_text_by_xdotool($this->password);
    sleep(4);
	$this->exts->type_key_by_xdotool("Tab");
	sleep(1);
	$this->exts->type_key_by_xdotool("Tab");
	sleep(1);
	$this->exts->type_key_by_xdotool("Tab");
    sleep(4);
    $this->exts->type_key_by_xdotool("Return");
    sleep(5);

}




private function checkFillRecaptcha($count = 0) {
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
				sleep(1);
			}
		} else {
			// try again if recaptcha expired
			if($count < 3){
				$count++;
				$this->checkFillRecaptcha($count);
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
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillTwoFactor() {
	$two_factor_selector = 'form[data-test-id="otp-form"] input';
	$two_factor_message_selector = 'form[data-test-id="otp-form"] div[class*="OtpHelp"]';
	$two_factor_submit_selector = 'form[data-test-id="otp-form"] button[data-test-id="otp-form-submit-button"]';

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

private function processInvoices() {
    $this->exts->waitTillPresent('button[id*="-accept-btn-handler"]', 20);
	if($this->exts->exists('button[id*="-accept-btn-handler"]')) {
		$this->exts->moveToElementAndClick('button[id*="-accept-btn-handler"]');
		sleep(1);
	}
	
	if($this->exts->exists('footer [aria-controls="SiteFooter_LanguageSelect-desktop-menu"]')) {
		$langs = $this->exts->getElementsAttribute('footer [aria-controls="SiteFooter_LanguageSelect-desktop-menu"] div[role="menu"] a', 'value');
		if (!in_array($this->invoice_language, $langs)) {
			$this->invoice_language = 'de';
		}
		$this->exts->moveToElementAndClick('footer [aria-controls="SiteFooter_LanguageSelect-desktop-menu"]');
		sleep(1);
		
		$this->exts->moveToElementAndClick('footer [aria-controls="SiteFooter_LanguageSelect-desktop-menu"] div[role="menu"] a[value="' . $this->invoice_language . '"]');
		sleep(15);
	} else {
		$langs = $this->exts->getElementsAttribute('div[data-automation="Header_languageSelect"] div[role="menu"] a', 'value');
		if (!in_array($this->invoice_language, $langs)) {
			$this->invoice_language = 'de';
		}
		$this->exts->moveToElementAndClick('div[data-automation="Header_languageSelect"] div[role="menu"] a[value="' . $this->invoice_language . '"]');
		sleep(15);
	}
	
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 4 && $this->exts->getElement('a[href*="invoice"]', $tags[3]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="invoice"]', $tags[3])->getAttribute("href");
			if (strpos($invoiceUrl, '/invoice/') != false) {
				$invoiceName = array_pop(explode('invoice/', $invoiceUrl));
			}else{
				$invoiceName = explode('&',
					array_pop(explode('order_id=', $invoiceUrl))
				)[0];
			}
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$currency = ' USD';
			if (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%C2%A3') != false) {
				$currency = ' GPB';
			}
			if (strpos(urlencode(trim($tags[2]->getAttribute('innerText'))), '%E2%82%AC') != false) {
				$currency = ' EUR';
			}
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . $currency;
			
			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->isNoInvoice = false;
			
			if((int)$this->restrictPages > 0 && count($invoices) > 12) break;
		}
	}
	
	// Download all invoices
	$this->exts->log('Invoices found: '.count($invoices));
	$this->exts->open_new_window();
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
		
		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. M. Y','Y-m-d', 'de');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		
		$this->exts->openUrl($invoice['invoiceUrl']);
		sleep(2);
		
		if($this->exts->exists('button[id*="-accept-btn-handler"]')) {
			$this->exts->moveToElementAndClick('button[id*="-accept-btn-handler"]');
			sleep(1);
		}

		$downloaded_file = $this->exts->download_current($invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}			
	}
	
	// close new tab too avoid too much tabs
	// $handles = $this->exts->webdriver->getWindowHandles();
	// if(count($handles) > 1){
	// 	$this->exts->webdriver->switchTo()->window(end($handles));
	// 	$this->exts->webdriver->close();
	// 	$handles = $this->exts->webdriver->getWindowHandles();
	// 	if(count($handles) > 1){
	// 		$this->exts->webdriver->switchTo()->window(end($handles));
	// 		$this->exts->webdriver->close();
	// 		$handles = $this->exts->webdriver->getWindowHandles();
	// 	}
	// 	$this->exts->webdriver->switchTo()->window($handles[0]);
	// }
}
private function processInvoicesPremier() {
	sleep(25);
	$this->exts->capture("4-invoices-premier-page");
	$invoices = [];

	$rows = $this->exts->getElements('div.invoice-content-table-container table > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 7 && $this->exts->getElement('a[href*="pdf"]', $tags[3]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="pdf"]', $tags[3])->getAttribute("href");
			$invoiceName = trim($tags[3]->getAttribute('innerText'));
			$invoiceDate = trim($tags[2]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

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