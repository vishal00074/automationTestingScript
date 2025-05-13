<?php // migrated
// Server-Portal-ID: 27317 - Last modified: 03.01.2025 05:16:53 UTC - User: 15

// Script here
public $baseUrl = 'https://espaceclient.prixtel.com/';

public $username_selector = 'input[name="email"], input#inputEmail';
public $password_selector = 'input[name="pwd"], input#inputPassword';
public $submit_login_selector = '#wpx_loginForm input[type="submit"], #wpx_loginForm button[type="submit"], .card-signin form button[type="submit"]';

public $check_login_failed_selector = '.wpx_errors ul li';
public $check_login_success_selector = 'a.wpx_user-name[id*="_logged"], #left-navigation-menu li a[href*="/logout"], .menu-logout a.nav-link.logout-link';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->checkSolveAmzCaptcha();
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->clearChrome();
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		$this->checkSolveAmzCaptcha();
		$this->checkFillLogin();
		sleep(20);
	}
	
	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		// Open invoices url and download invoice
		// $this->exts->execute_javascript('location.href = "https://www.prixtel.com/client/#!/mes-factures";');
		// $this->processInvoices(1);
		// $this->processInvoicesNew(1);
		
		//espaceclient 
		if($this->exts->urlContains('espaceclient.prixtel')){
			$this->exts->openUrl('https://espaceclient.prixtel.com/factures/liste');
			sleep(15);
			$this->exts->moveToElementAndClick('div.choice-bill-concerned-area');
			sleep(5);
			$months = count($this->exts->getElements('app-modal-bills-list .list-group .list-group-item'));
			for ($i=0; $i < $months; $i++) { 
				$month = $this->exts->getElements('app-modal-bills-list .list-group .list-group-item')[$i];
				try{
					$this->exts->log('Click month button');
					$month->click();
				} catch(\Exception $exception){
					$this->exts->log('Click month button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$month]);
				}
				sleep(10);
				$this->processInvoicesEspaceclient();
				sleep(2);
				$this->exts->moveToElementAndClick('div.choice-bill-concerned-area');
				sleep(5);
				if(!$this->exts->exists('app-modal-bills-list .list-group .list-group-item')){
					$this->exts->openUrl('https://espaceclient.prixtel.com/factures/liste');
					sleep(15);
					$this->exts->moveToElementAndClick('div.choice-bill-concerned-area');
					sleep(5);
				}
			}
		}
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if($this->exts->getElement($this->check_login_failed_selector) != null) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function clearChrome(){
	$this->exts->log("Clearing browser history, cookie, cache");
	$this->exts->openUrl('chrome://settings/clearBrowserData');
	sleep(10);
	$this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
	sleep(1);
	$this->exts->capture("clear-page");
	$this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearBrowsingDataConfirm").click();');
	sleep(15);
	$this->exts->capture("after-clear");
}

private function checkSolveAmzCaptcha(){
	if ($this->exts->exists('button#amzn-captcha-verify-button')) {
		$this->exts->click_by_xdotool('button#amzn-captcha-verify-button');
		sleep(3);
		$captcha_wraper_selector = 'div.amzn-captcha-modal';
		for ($i=0; $i < 5 && $this->exts->exists($captcha_wraper_selector); $i++) { 
			$coordinates = $this->processClickCaptcha($captcha_wraper_selector, '', '', $json_result=true);// use $language_code and $captcha_instruction if they changed captcha content
			if($coordinates == ''){
				$coordinates = $this->processClickCaptcha($captcha_wraper_selector, '', '', $json_result=true);
			}
			if($coordinates != ''){
				foreach ($coordinates as $coordinate) {
					$this->exts->click_by_xdotool($captcha_wraper_selector, intval($coordinate['x']), intval($coordinate['y']));
				}
				sleep(1);
				$this->exts->click_by_xdotool('button#amzn-btn-verify-internal');
				sleep(5);
			}
		}
		
	}
}

private function processClickCaptcha(
	$captcha_image_selector,
	$instruction = '',
	$lang_code = '',
	$json_result = false,
	$image_dpi = 90
) {
	$this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
	$response = '';
	$image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
	$source_image = imagecreatefrompng($image_path);
	imagejpeg($source_image, $this->exts->screen_capture_location.$this->exts->process_uid.'.jpg', $image_dpi);

	$cmd = $this->exts->config_array['click_captcha_shell_script']." --PROCESS_UID::".$this->exts->process_uid." --CAPTCHA_INSTRUCTION::".urlencode($instruction)." --LANG_CODE::".urlencode($lang_code)." --JSON_RESULT::".urlencode($json_result);
	$this->exts->log('Executing command : '.$cmd);
	exec($cmd, $output, $return_var);
	$this->exts->log('Command Result : '.print_r($output, true));
	
	if (!empty($output)) {
		$output = trim($output[0]);
		if($json_result){
			if(strpos($output, '"status":1') !== false){
				$response = json_decode($output, true);
				$response = $response['request'];
			}
		} else {
			if(strpos($output, 'coordinates:') !== false){
				$response = trim(end(explode("coordinates:", $output)));
			}
		}
	}
	if($response == ''){
		$this->exts->log("Can not get result from API");
	}
	return $response;
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
		
		$this->exts->capture("2-login-page-filled");
		$this->checkFillRecaptcha();
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		$this->exts->waitTillPresent("div#alert-message", 25);
		if($this->exts->exists("div#alert-message")){
			$this->exts->log("Login Failure : " . $this->exts->extract("div#alert-message"));
			if(strpos(strtolower($this->exts->extract("div#alert-message")), 'votre mot de passe') !== false){
				$this->exts->loginFailure(1);
			}
		}
		
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

private function processInvoices($pageCount=1) {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table.histo-factures > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 5 && $this->exts->getElement('.EC-button-PDF', $tags[4]) != null) {
			$this->isNoInvoice = false;
			$download_button = $this->exts->getElement('.EC-button-PDF', $tags[4]);
			$invoiceName = trim($tags[1]->getAttribute('innerText'));
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = trim($tags[3]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			$parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
			$this->exts->log('Date parsed: '.$parsed_date);
			
			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoiceName)){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				try{
					$this->exts->log('Click download button');
					$download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
				}
				sleep(20);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				if(trim($downloaded_file) == ''){
					try{
						$this->exts->log('Click download button again');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(20);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				}
				
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}
		}
	}
	
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$pageCount < 50 &&
		$this->exts->getElement('#invoices-history-pager li:not(.disabled) a[ng-click="refresh_facts_last_page()"]') != null
	){
		$pageCount++;
		$this->exts->moveToElementAndClick('#invoices-history-pager li:not(.disabled) a[ng-click="refresh_facts_last_page()"]');
		sleep(5);
		$this->processInvoices($pageCount);
	}
}

private function processInvoicesNew($pageCount=1) {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	if($pageCount == 1) {
		//Download latest invoice
		$rows = $this->exts->getElements('facturedetail .facture-container .row');
		foreach ($rows as $row) {
			if($this->exts->getElement('.EC-button-PDF', $row) != null) {
				$this->isNoInvoice = false;
				$download_button = $this->exts->getElement('.EC-button-PDF', $row);
				if(count($this->exts->getElements('div p')) > 0) {
					$invoiceName = trim($this->exts->getElements('div p')[1]->getAttribute('innerText'));
					$this->exts->log('invoiceName: '.$invoiceName);
					
					$tempArr = explode(" ", $invoiceName);
					$invoiceName = trim(preg_replace('/[^\d\.\,]/', '', end($tempArr)));
					if(trim($invoiceName) != '' && !empty($invoiceName)) {
						$invoiceFileName = $invoiceName.'.pdf';
					} else {
						$invoiceFileName = '';
					}
				} else {
					$invoiceName = '';
					$invoiceFileName = '';
				}
				$invoiceDate = '';
				$invoiceAmount = '';
				
				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceDate: '.$invoiceDate);
				$this->exts->log('invoiceAmount: '.$invoiceAmount);
				
				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					try{
						$this->exts->log('Click download button');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(20);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
					if(trim($downloaded_file) == ''){
						try{
							$this->exts->log('Click download button again');
							$download_button->click();
						} catch(\Exception $exception){
							$this->exts->log('Click download button by javascript');
							$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
						}
						sleep(20);
						$this->exts->wait_and_check_download('pdf');
						$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
					}
					
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						if(trim($invoiceName) == '') $invoiceName = basename($downloaded_file, '.pdf');
						$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}
	}
	
	//Download from Invoice History List
	$rows = $this->exts->getElements('.bill_listing_tab');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('.content td', $row);
		if(count($tags) >= 3 && $this->exts->getElement('.EC-button-PDF', $row) != null) {
			$this->isNoInvoice = false;
			$download_button = $this->exts->getElement('.EC-button-PDF', $row);
			$invoiceName = trim($tags[1]->getAttribute('innerText'));
			$invoiceFileName = $invoiceName.'.pdf';
			
			$thContents = explode(":", $this->exts->getElement('th',$row)->getAttribute('innerText'));
			$invoiceDate = count($thContents)==2 ? trim($thContents[1]) : null;
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			$parsed_date = is_null($invoiceDate)?null:$this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
			$this->exts->log('Date parsed: '.$parsed_date);
			
			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoiceName)){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				try{
					$this->exts->log('Click download button');
					$download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
				}
				sleep(20);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				if(trim($downloaded_file) == ''){
					try{
						$this->exts->log('Click download button again');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(20);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				}
				
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}
		}
	}
	
	$pageCount++;
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$pageCount < 50 &&
		$this->exts->getElement('#invoices-history-pager li:not(.disabled) a[data-page="'.$pageCount.'"]') != null
	){
		
		$this->exts->moveToElementAndClick('#invoices-history-pager li:not(.disabled) a[data-page="'.$pageCount.'"]');
		sleep(5);
		$this->processInvoicesNew($pageCount);
	}
}

private function processInvoicesEspaceclient() {
	sleep(15);
	
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	$download_button = $this->exts->getElement('//button[contains(text(),"charger la facture") or contains(text(),"Download the invoice")]', null, 'xpath');
	if($download_button != null){
		$invoiceName = str_replace(':', '', $this->exts->extract('.bills-grid .bill-id', null, 'innerText'));
		$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.bills-grid .bill-amount', null, 'innerText'))) . ' EUR';
		$invoiceDate = trim($this->exts->extract('div.selected-bill-desc', null, 'innerText'));
		$invoiceFileName = $invoiceName. '.pdf';
		$invoiceDateParse = $this->exts->parse_date($invoiceDate, 'F Y','Y-m-01' , 'fr');
		$this->exts->log('Date parsed: '.$invoiceDateParse);
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoiceName);
		$this->exts->log('invoiceDate: '.$invoiceDate);
		$this->exts->log('invoiceAmount: '.$invoiceAmount);
		$this->isNoInvoice = false;
		try{
			$this->exts->log('Click download button');
			$download_button->click();
		} catch(\Exception $exception){
			$this->exts->log('Click download button by javascript');
			$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
		}
		sleep(5);
		$this->exts->wait_and_check_download('pdf');
		
		$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoiceName, $invoiceDateParse, $invoiceAmount, $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}