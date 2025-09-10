<?php
// Server-Portal-ID: 6039 - Last modified: 16.08.2024 06:16:36 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://www.udemy.com/dashboard/purchase-history/";
public $username_selector = 'form[data-purpose="code-generation-form"] input[name="email"], input[name="email"]';
public $password_selector = 'input[name="password"]';
public $submit_button_selector = 'button[class*="auth-udemy--submit-button"], button[class*="auth-submit-button"]';
public $login_tryout = 0;
public $restrictPages = 3;
public $only_invoice = 0;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	$this->only_invoice = isset($this->exts->config_array["only_invoice"]) ? (int)@$this->exts->config_array["only_invoice"] : 0;

	$this->exts->loadCookiesFromFile();
	//bypass browser rejected
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->check_solve_clodflare_blocked_page();
	sleep(10);
	if($this->checkLogin()) {
		sleep(10);
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->invoicePage();
	} else {
		$this->exts->clearCookies();
		$this->exts->openUrl($this->baseUrl);
		sleep(10);

		if (!$this->exts->exists($this->username_selector) && !$this->exts->exists('div.cf-turnstile-wrapper')) {
			$this->exts->openUrl($this->baseUrl);
			sleep(10);
		}

		if($this->exts->exists('form[data-purpose="code-generation-form"] button[class*="passwordless"]') && !$this->exts->exists($this->password_selector)){
			$this->exts->click_by_xdotool('form[data-purpose="code-generation-form"] button[class*="passwordless"]');
			sleep(10);
		}
		
		if($this->exts->exists('div#px-captcha')){
			$this->checkSolveLoginChallenges();
			sleep(10);
		}
		if($this->exts->exists('div#px-captcha')){
			$this->checkSolveLoginChallenges();
			sleep(10);
		}
		$this->check_solve_clodflare_blocked_page();
		sleep(10);

		if($this->exts->exists('a[data-purpose="header-login"]')){
			$this->exts->log('Click on Login Button');
			$this->exts->click_by_xdotool('a[data-purpose="header-login"]');
		}
		$this->exts->log("Go to Fill from");
		$this->fillForm(0);
		sleep(5);
		$this->exts->capture_by_chromedevtool("after-fillForm");
		sleep(2);
		if($this->exts->exists('div#px-captcha')){
			$this->checkSolveLoginChallenges();
		}
		
		if (!$this->checkLogin() && $this->exts->exists($this->password_selector)) {
			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			
			$this->checkFillRecaptcha();
			
			$this->exts->moveToElementAndClick($this->submit_button_selector);
			sleep(5);
			
			$err_msg = $this->exts->extract('div.alert.alert-danger.js-error-alert');
			if ($err_msg != "" && $err_msg != null) {
				$this->exts->log($err_msg);
				$this->exts->loginFailure(1);
			}
		}
		$this->checkFillRecaptcha();
		$this->checkFillRecaptcha();
		sleep(2);

		$this->checkFillTwoFactor();
		
		if($this->exts->exists('#onetrust-accept-btn-handler')) {
			$this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
			sleep(2);
		}
		
		if($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			
			$this->invoicePage();
		} else if($this->exts->exists('form[class*="signin-form"] div[class*="error-alert"]')){
			$this->exts->loginFailure(1);
		} else {
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure();
		}
	}
}

public function fillForm($count){
	$this->exts->log('fillForm');
	$this->exts->capture_by_chromedevtool("2-login-page");
	// if($this->exts->exists('div[data-module-id="eu-cookie-message"] button')){
	// 	$this->exts->click_by_xdotool('div[data-module-id="eu-cookie-message"] button');
	// 	sleep(5);
	// }

	// try {
		sleep(10);
		if($this->exts->exists($this->username_selector)) {
			$this->exts->log('username_selector existed');
			sleep(1);
			
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(5);
			// $this->exts->type_key_by_xdotool("Delete");
			// sleep(5);
			// $this->exts->type_text_by_xdotool($this->username);
			// sleep(1);
			// if (!$this->isValidEmail($this->username)) {
			// 	$this->exts->log('>>>>>>>>>>>>>>>>>>>Invalid email........');
			// 	$this->exts->loginFailure(1);
			// }
		}
		if($this->exts->exists('form[data-purpose="code-generation-form"] button[class*="passwordless"]') && !$this->exts->exists($this->password_selector)){
			$this->exts->click_by_xdotool('form[data-purpose="code-generation-form"] button[class*="passwordless"]');
			sleep(10);
		}

		if ($this->exts->exists($this->password_selector)) {
			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			sleep(1);
			$this->exts->type_key_by_xdotool("Delete");
			$this->exts->type_text_by_xdotool($this->password);
			sleep(1);
			$this->exts->capture_by_chromedevtool("2-login-page-filled");
			
			$this->exts->click_by_xdotool($this->submit_button_selector);
			sleep(15);
			
			if ($this->exts->getElementByText('div[class*="error"]', ['password', 'passwort'], null, false)) {
				$this->exts->loginFailure(1);
			}
		}

		if($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]") ) {
			$this->checkFillRecaptcha(0);
			$this->fillForm($count+1);
		}
		if($count < 4 && $this->exts->getElement('div[data-purpose="safely-set-inner-html:auth:error"]') != null) {
			$this->checkFillRecaptcha(0);
			$this->fillForm($count+1);
		}
		sleep(15);
	// } catch(\Exception $exception){
	// 	$this->exts->log("Exception filling loginform ".$exception->getMessage());
	// }
}

function isValidEmail($email){
return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if($this->exts->exists('a[href*="/logout/"], a[href*="/user/edit-profile/"][id="header.profile"]')) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}
	} catch(Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}

function checkFillRecaptcha() {
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
				$this->exts->executeSafeScript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
			}
			sleep(2);
			$this->exts->capture('recaptcha-filled');
			
			// Step 2, check if callback function need executed
			$gcallbackFunction = $this->exts->executeSafeScript('
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
				$this->exts->executeSafeScript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
				sleep(10);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
	}

	private function check_solve_clodflare_blocked_page() {
	$this->exts->capture_by_chromedevtool("blocked-page-checking");
	if($this->exts->exists('div.cf-turnstile-wrapper')) {
		$this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
		$attempts = 5; 
		$delay = 30; 
		
		for ($i = 0; $i < $attempts; $i++) {			
			$this->exts->click_by_xdotool('div.cf-turnstile-wrapper', 30, 28, true);
			sleep($delay);
			if (!$this->exts->exists('div.cf-turnstile-wrapper')) {
				break; 
			}
		}
	}
	}

	function checkRelogin() {
	if($this->exts->exists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->exts->exists('textarea[name="g-recaptcha-response"]')) {
		$this->exts->openUrl($this->baseUrl);
		sleep(5);
		$this->checkFillRecaptcha(0);
		sleep(5);
		$this->exts->moveToElementAndClick('button[data-purpose="header-login"]');
		sleep(5);
		$this->fillForm(0);
		if($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Relogin successful!!!!");
			//$this->invoicePage();
		} else {
			$this->exts->log(">>>>>>>>>>>>>>>Relogin failed!!!!");
			$this->exts->loginFailure();
		}
	}
}

private function checkFillTwoFactor() {
$two_factor_selector = 'form[data-purpose="one-time-password-submit-form"] input#form-group--1, form[data-purpose="otp-verification-form"] input';
$two_factor_message_selector = 'div[class*="app--otp-container"] p[class*="app--intro-text"], div[class*="auth-layout--text-align-center"]';
$two_factor_submit_selector = 'form[data-purpose="one-time-password-submit-form"] button[class*="one-time-password-form--submit-button"], form[data-purpose="otp-verification-form"] button[type="submit"]';

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
		$this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
		
		$this->exts->log("checkFillTwoFactor: Clicking submit button.");
		sleep(3);
		$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

		$this->exts->moveToElementAndClick($two_factor_submit_selector);
		sleep(15);

		if($this->exts->getElement($two_factor_selector) == null){
			$this->exts->log("Two factor solved");
		} else if ($this->exts->two_factor_attempts < 3) {
			$this->exts->notification_uid = "";
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

private function checkSolveLoginChallenges($addtime = 0){
$this->exts->capture_by_chromedevtool("2-login-challenges-checking");
if($this->exts->exists('#px-captcha')){
	$this->exts->type_key_by_xdotool("ctrl+l");
	$this->exts->type_key_by_xdotool("Return");
	sleep(7);
	if($this->exts->exists('#px-captcha')){
		$this->exts->execute_javascript('document.querySelector("#px-captcha").style.width = "fit-content";');
		sleep(1);
		$this->click_hold_by_xdotool('div#px-captcha', 9);
		sleep(15);
		if($this->exts->exists('#px-captcha')){
			$this->exts->execute_javascript('document.querySelector("#px-captcha").style.width = "fit-content";');
			sleep(1);
			$this->click_hold_by_xdotool('div#px-captcha', 12);
			sleep(15);
			if($this->exts->exists('#px-captcha')){
				$this->exts->execute_javascript('document.querySelector("#px-captcha").style.width = "fit-content";');
				sleep(1);
				$this->click_hold_by_xdotool('div#px-captcha', 15);
			}
		}
		
		sleep(10);
	}
}
}
private function click_hold_by_xdotool($selector='', $hold_seconds=5){
$selector = str_replace('"', '\\"', $selector);
$element_coo = $this->exts->execute_javascript('
	window.lastMousePosition = null;
	window.addEventListener("mousemove", function(e){
		window.lastMousePosition = e.clientX +"|" + e.clientY;
	});
	var coo = document.querySelector("'.$selector.'").getBoundingClientRect();
	Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
')['result']['value'];
sleep(3);
$this->exts->log('X/Y: ' . $element_coo);
$element_coo = explode('|', $element_coo);
$node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
$this->exts->log(' Move: ');
exec("sudo docker exec ".$node_name." bash -c 'xdotool mousemove 350 450'");
$current_cursor = $this->exts->execute_javascript('window.lastMousePosition')['result']['value'];
$this->exts->log('current_cursor: ' . $current_cursor);

$current_cursor = explode('|', $current_cursor);
$offset_x = (int)$element_coo[0] - (int)$current_cursor[0];
$offset_y = (int)$element_coo[1] - (int)$current_cursor[1];
exec("sudo docker exec ".$node_name." bash -c 'xdotool mousemove_relative ".$offset_x." ".$offset_y." mousedown 1 sleep ".$hold_seconds." mouseup 1;'");
}
function invoicePage() {
$this->exts->log("Invoice page");

if($this->exts->exists('#onetrust-accept-btn-handler')) {
	$this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
	sleep(2);
}

$editUser = $this->exts->getElement('a[href*="/user/edit-profile/"][id="header.profile"]');
if($editUser!= null){
	$this->exts->openUrlMouse()->mouseMove($editUser->getCoordinates());
	
}
sleep(1);
$this->exts->moveToElementAndClick('a[href*="/purchase-history/"]');
sleep(5);
$this->checkFillRecaptcha();
$this->checkFillRecaptcha();
$this->checkFillRecaptcha();
if(!$this->exts->urlContains('/purchase-history')){
	$this->exts->openUrl('https://www.udemy.com/dashboard/purchase-history/');
	$this->checkFillRecaptcha();
	$this->checkFillRecaptcha();
	$this->checkFillRecaptcha();
	sleep(10);
}

if($this->exts->exists('#onetrust-accept-btn-handler')) {
	$this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
	sleep(2);
}
$this->downloadInvoice();

if ($this->totalFiles == 0) {
	$this->exts->log("No invoice !!! ");
	$this->exts->no_invoice();
}
$this->exts->success();
}

/**
 *method to download incoice
*/
public $totalFiles = 0;
function downloadInvoice($count=1, $pageCount=1){
$this->exts->log("Begin download invoice");

if($this->exts->exists('#onetrust-accept-btn-handler')) {
	$this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
	sleep(2);
}

$this->exts->capture('4-List-invoice-page-'.$pageCount);
if ($this->exts->getElement('ul.shopping-list:first-of-type, [class*="purchase-history"] table tbody tr') != null) {
	if ($this->exts->getElement('ul.shopping-list:first-of-type') != null) {
		$receipts = $this->exts->getElements('ul.shopping-list:first-of-type > li');
		$invoices = array();
		foreach ($receipts as $i => $receipt) {
			if ($this->exts->getElement('a[href*="receipt/"]', $receipt) != null) {
				$receiptDate = trim($this->exts->extract('div[class*="detail__created"]', $receipt));
				$receiptUrl = $this->exts->getElement('a[href*="receipt/"]', $receipt)->getAttribute('href');
				$receiptName = reset(explode('/', trim(end(explode('receipt/', $receiptUrl)))));
				$receiptAmount = trim($this->exts->extract('div[class*="detail__price"]', $receipt));
				$receiptFileName = $receiptName.'.pdf';
				$receiptSelector = 'ul.shopping-list:first-of-type > li:nth-child('.($i + 1).') a[href*="receipt/"]';
				
				$this->exts->log("_____________________".($i + 1)."___________________________________________");
				$this->exts->log("Invoice Date: ".$receiptDate);
				$this->exts->log("Invoice Name: ".$receiptName);
				$this->exts->log("Invoice Amount: ".$receiptAmount);
				$this->exts->log("Invoice Url: ".$receiptUrl);
				$this->exts->log("Invoice FileName: ".$receiptFileName);
				$this->exts->log("________________________________________________________________");
				
				$invoice = array(
					'receiptDate'     => $receiptDate,
					'receiptName'     => $receiptName,
					'receiptAmount'   => $receiptAmount,
					'receiptUrl'      => $receiptUrl,
					'receiptSelector' => $receiptSelector,
					'receiptFileName' => $receiptFileName
				);
				array_push($invoices, $invoice);
			}
		}
	} else if ($this->exts->getElement('[class*="purchase-history"] table tbody tr') != null) {
		$receipts = $this->exts->getElements('[class*="purchase-history"] table tbody tr');
		$invoices = array();
		foreach ($receipts as $i => $receipt) {
			if ($this->exts->getElement('a[href*="receipt/"]', $receipt) != null || $this->exts->getElement('a[href*="/invoice-transaction/"]', $receipt) != null) {
				$tags = $this->exts->getElements('td', $receipt);
				if(count($tags) >= 4) {
					if($this->exts->getElement('a[href*="/invoice-transaction/"]', $receipt) != null) {
						$receiptDate = trim($tags[1]->getText());
						$receiptUrl = $this->exts->getElement('a[href*="/invoice-transaction/"]', $receipt)->getAttribute('href');
						$tempArr = explode('/invoice-transaction/', $receiptUrl);
						$tempArr = explode('/', trim(end($tempArr)));
						$receiptName = reset($tempArr);
						$receiptAmount = trim($tags[2]->getText());
						$receiptFileName = $receiptName.'.pdf';
						$receiptSelector = '[class*="purchase-history"] table tbody tr:nth-child('.($i + 1).') a[href*="/invoice-transaction/"]';
					} else {
						if ($this->only_invoice === 1)
							continue;
						$receiptDate = trim($tags[1]->getText());
						$receiptUrl = $this->exts->getElement('a[href*="receipt/"]', $receipt)->getAttribute('href');
						$tempArr = explode('receipt/', $receiptUrl);
						$tempArr = explode('/', trim(end($tempArr)));
						$receiptName = reset($tempArr);
						$receiptAmount = trim($tags[2]->getText());
						$receiptFileName = $receiptName.'.pdf';
						$receiptSelector = '[class*="purchase-history"] table tbody tr:nth-child('.($i + 1).') a[href*="receipt/"]';

						if ($this->exts->getElement('a[href*="transactions/"]', $receipt) != null) {
							$receiptUrl = $this->exts->getElement('a[href*="transactions/"]', $receipt)->getAttribute('href');
							$receiptSelector = '[class*="purchase-history"] table tbody tr:nth-child('.($i + 1).') a[href*="transactions/"]';
						}
					}
					
					
					$this->exts->log("_____________________".($i + 1)."___________________________________________");
					$this->exts->log("Invoice Date: ".$receiptDate);
					$this->exts->log("Invoice Name: ".$receiptName);
					$this->exts->log("Invoice Amount: ".$receiptAmount);
					$this->exts->log("Invoice Url: ".$receiptUrl);
					$this->exts->log("Invoice FileName: ".$receiptFileName);
					$this->exts->log("________________________________________________________________");
					
					$invoice = array(
						'receiptDate'     => $receiptDate,
						'receiptName'     => $receiptName,
						'receiptAmount'   => $receiptAmount,
						'receiptUrl'      => $receiptUrl,
						'receiptSelector' => $receiptSelector,
						'receiptFileName' => $receiptFileName
					);
					array_push($invoices, $invoice);
				}
			}
		}
	}
	$this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));
	
	$this->totalFiles = count($invoices);
	$count = 1;
	$this->exts->type_key_by_xdotool("ctrl+t");
	sleep(1);
	$browser_windows = $this->exts->openUrlWindowHandles();
	$this->exts->webdriver->switchTo()->window(end($browser_windows));

	

	foreach ($invoices as $invoice) {
		$this->exts->openUrl($invoice['receiptUrl']);
		sleep(25);
		$this->check_solve_clodflare_blocked_page();
		sleep(10);
		if ($this->exts->exists('div.receipt-wrap, div.invoice-box, div.receipt-page')) {
			$downloaded_file = $this->exts->download_current($invoice['receiptFileName']);
			$this->exts->log("Download file: " . $downloaded_file);
			
			if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
				$this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'] , $invoice['receiptAmount'], $downloaded_file);
				sleep(1);
				$count++;
			}
		}
	}
	$this->exts->close_new_window();
	// next page
	if((int)@$this->restrictPages == 0 && $pageCount < 10 && $this->totalFiles > 0) {
		if ($this->exts->getElement('a[class*="pagination--next"][aria-disabled="false"]') != null) {
			$pageCount++;
			$this->exts->moveToElementAndClick('a[class*="pagination--next"][aria-disabled="false"]');
			sleep(5);
			$this->checkFillRecaptcha();
			$this->checkFillRecaptcha();
			$this->checkFillRecaptcha();
			$this->downloadInvoice(1, $pageCount);
		}
	}
}
}