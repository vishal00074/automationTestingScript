<?php // migrated
// Server-Portal-ID: 28530 - Last modified: 22.11.2024 13:47:32 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.blacklane.com/en/';
public $loginUrl = 'https://www.blacklane.com/en/';
public $invoicePageUrl = 'https://www.blacklane.com/en/bookings/past/';

public $username_selector = 'input[name="session[email]"], input[name="username"]';
public $password_selector = 'input[name="session[password]"], input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="action"][data-action-button-primary="true"],form#new_session button[type="submit"], button[name="login"]';

public $check_login_failed_selector = '.flash-message-error, .flash-message-notice:not([style="display: none;"])';
public $check_login_success_selector = '#dropdown-logout-form, button[aria-controls="loggedInDropdownMenu"], div[class*="down_headerProfileDropdown"] button[class*="Dropdown_dropDownButton"]';

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
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		if($this->exts->exists('button#onetrust-accept-btn-handler')){
			$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
			sleep(5);
		}
		if ($this->exts->exists('a[data-test="signin-button"]')) {
			$this->exts->moveToElementAndClick('a[data-test="signin-button"]');
			sleep(15);
		}
		//$this->checkFillLoginUndetected();
		$this->checkFillLogin();
		sleep(20);
	}

	
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		sleep(15);
		if($this->exts->urlContains('bookings')){
			// download booking
			$this->exts->moveToElementAndClick('a[href*="/bookings/past"]');
			sleep(20);
			$this->processBookingInvoices();
		} else if(strpos($this->exts->extract('.error-pages h1'), '404') !== false && $this->exts->exists('a[href*="bookings"][data-qa="rides"]')){
			$this->exts->moveToElementAndClick('a[href*="bookings"][data-qa="rides"]');
			sleep(20);
			// download booking
			$this->exts->moveToElementAndClick('a[href*="/bookings/past"]');
			sleep(20);
			$this->processBookingInvoices();
		} else {
			$this->processInvoices();
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

private function checkFillLogin() {
	if($this->exts->getElement($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);

		// $this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(5);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(10);

		// if($this->remember_me_selector != '')
		// 	$this->exts->moveToElementAndClick($this->remember_me_selector);
		// sleep(2);
		$this->exts->waitTillPresent('div[class="ulp-captcha-container"]');
		for ($i = 0; $i < 3; $i++) {
			$siteKeyElement = $this->exts->getElements('div[class="ulp-captcha-container"]')[0];
			$data_siteKey = $siteKeyElement->getAttribute("data-captcha-sitekey");
			$this->exts->log("data_siteKey:  " . $data_siteKey);

			$jsonRes = $this->exts->processHumanCaptcha($data_siteKey, $this->exts->getUrl());
			sleep(2);
		}

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(5);

		if($this->exts->getElement($this->check_login_failed_selector) != null) {
			$this->exts->loginFailure(1);
		}
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function check_solve_cloudflare_page() {
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if(
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ){
        for ($waiting=0; $waiting < 10; $waiting++) {
            sleep(2);
            if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                sleep(3);
                break;
            }
        }
    }

    if($this->exts->exists($unsolved_cloudflare_input_xpath)){
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
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
		}else{
			if ($count < 4) {
				$count++;
				$this->checkFillRecaptcha($count);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}

private function processInvoices($paging_count = 1) {
	sleep(25);
	
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('div.ride-list div.row a');
	foreach ($rows as $row) {
		$invoiceUrl = $row->getAttribute("href");
		$invoiceName = trim(array_pop(explode('rides/', $invoiceUrl)));
		$invoiceDate = trim($this->exts->extract('div.datetime', $row, 'innerText'));
		$invoiceDate = str_replace(' ', '', $invoiceDate);
		$invoiceAmount = $this->exts->extract('div.price', $row, 'innerText');

		array_push($invoices, array(
			'invoiceName'=>$invoiceName,
			'invoiceDate'=>$invoiceDate,
			'invoiceAmount'=>$invoiceAmount,
			'invoiceUrl'=>$invoiceUrl
		));
	
	}

	// Download all invoices
	$this->exts->log('Invoices found: '.count($invoices));
	foreach ($invoices as $invoice) {
        $newTab = $this->exts->openNewTab();
		sleep(2);
		$this->exts->openUrl($invoice['invoiceUrl']);
		sleep(5);
		if($this->exts->getElement('button[data-href*="/invoices/"]') == null){
            $this->exts->closeTab($newTab);
			
			sleep(2);
			continue;
		}
		$invoice['invoiceUrl'] = $this->exts->getElement('button[data-href*="/invoices/"]')->getAttribute("data-href");
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Md,Y,g:iA(H:i)','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
		$this->isNoInvoice = false;
        $this->exts->closeTab($newTab);
		
		sleep(2);
	}
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
	   $paging_count < 50 &&
	   $this->exts->getElement('div.pagination span.right:not(.disabled) a') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('div.pagination span.right:not(.disabled) a');
		sleep(5);
		$this->processInvoices($paging_count);
	}
}

private function processBookingInvoices($paging_count = 1) {
	sleep(25);
	
	$this->exts->capture("4-invoices-page-Booking");
	$invoices = [];
	if($this->exts->exists('table[data-test="data-table"] > tbody > tr')){
		$rows = count($this->exts->getElements('table[data-test="data-table"] > tbody > tr'));
		for ($i=0; $i < $rows; $i++) {
			$row = $this->exts->getElements('table[data-test="data-table"] > tbody > tr')[$i];
			$tags = $this->exts->getElements('td', $row);
			if(count($tags) >= 5) {
				$invoiceDate = trim($this->exts->extract('[class*="DateItem_dateText"]', $tags[0], 'innerText'));
				$invoiceAmount = '';
				try{
					$this->exts->log('Click download button');
					$row->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$row]);
				}
				sleep(10);
				if($this->exts->exists('button[data-href*="invoices"][data-href*="pdf"]')){
					$invoiceUrl = $this->exts->getElement('button[data-href*="invoices"]')->getAttribute("data-href");
					$invoiceName = explode('.pdf',
						array_pop(explode('invoices/', $invoiceUrl))
					)[0];
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('dd.price-table__total'))) . ' EUR';
					$this->isNoInvoice = false;
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					$parsed_date = $this->exts->parse_date($invoiceDate, 'D, M d, Y','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);

					// Download invoice if it not exisited
					$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
						sleep(1);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				} else if($this->exts->exists('a[class*="DetailsItems_downloadInvoice"] button,div[class*="invoiceDownload"] a[href*="invoices/"][href*=".pdf"]')){
					$invoiceUrl = $this->exts->getElement('a[class*="DetailsItems_downloadInvoice"] button,div[class*="invoiceDownload"] a[href*="invoices/"][href*=".pdf"]')->getAttribute("href");
					$invoiceName = explode('.pdf',
						array_pop(explode('invoices/', $invoiceUrl))
					)[0];
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('[data-test="price-total"]'))) . ' EUR';
					$this->isNoInvoice = false;
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					$parsed_date = $this->exts->parse_date($invoiceDate, 'D, d F Y','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);

					// Download invoice if it not exisited
					$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
						sleep(1);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
			$this->exts->webdriver->navigate()->back();
			sleep(10);
			if ($paging_count > 1) {
				for ($j=1; $j < $paging_count; $j++) { 
					$this->exts->moveToElementAndClick('button[class*="Pagination_arrowRight"]:not([disabled])');
					sleep(10);
				}
			}
		}
	} else {
		$rows = count($this->exts->getElements('div[class*="BookingsTable_body"] div[class*="BookingsTable_rowGroup"]'));
		for ($i=0; $i < $rows; $i++) {
			$row = $this->exts->getElements('div[class*="BookingsTable_body"] div[class*="BookingsTable_rowGroup"]')[$i];
			$tags = $this->exts->getElements('div[class*="BookingsTable_cell"]', $row);
			if(count($tags) >= 7) {
				$invoiceDate = trim($this->exts->extract('[class*="BookingsTable_primary"]', $tags[1], 'innerText'));
				$invoiceAmount = '';
				try{
					$this->exts->log('Click download button');
					$row->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$row]);
				}
				sleep(10);
				if($this->exts->exists('button[data-href*="invoices"][data-href*="pdf"]')){
					$invoiceUrl = $this->exts->getElement('button[data-href*="invoices"]')->getAttribute("data-href");
					$invoiceName = explode('.pdf',
						array_pop(explode('invoices/', $invoiceUrl))
					)[0];
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('dd.price-table__total'))) . ' EUR';
					$this->isNoInvoice = false;
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					$parsed_date = $this->exts->parse_date($invoiceDate, 'D, M d, Y','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);

					// Download invoice if it not exisited
					$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
						sleep(1);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				} else if($this->exts->exists('a[data-testid="download-invoice-link"],a[class*="DetailsItems_downloadInvoice"] button,div[class*="invoiceDownload"] a[href*="invoices/"][href*=".pdf"]')){
					$invoiceUrl = $this->exts->getElement('a[data-testid="download-invoice-link"],a[class*="DetailsItems_downloadInvoice"] ,div[class*="invoiceDownload"] a[href*="invoices/"][href*=".pdf"]')->getAttribute("href");
					// $invoiceName = explode('.pdf',
					// 	array_pop(explode('invoices/', $invoiceUrl))
					// )[0];
					$invoiceName= trim(preg_replace('/\D/', '', $this->exts->extract('h2[class*="BookingHeader_title"],h2[class*="DetailsItems_title"]')));

					//$invoiceName = preg_replace('/\D/', '', $invoiceTitle);
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('[data-testid="price-total"]'))) . ' EUR';
					$invoiceDate;
					$this->isNoInvoice = false;
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceUrl: '.$invoiceUrl);
					$this->exts->log('invoiceFileName: '.$invoiceFileName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					//$parsed_date = $this->exts->parse_date($invoiceDate, 'D, d F Y','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);

					// Download invoice if it not exisited
					$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
			// $this->exts->webdriver->navigate()->back();
			sleep(10);
			if ($paging_count > 1) {
				for ($j=1; $j < $paging_count; $j++) { 
					$this->exts->moveToElementAndClick('button[class*="Pagination_arrowRight"]:not([disabled])');
					sleep(10);
				}
			}
		}
	}
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
	   $paging_count < 50 &&
	   $this->exts->getElement('button[class*="Pagination_arrowRight"]:not([disabled])') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('button[class*="Pagination_arrowRight"]:not([disabled])');
		$this->processBookingInvoices($paging_count);
	}
}


// helper functions
private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[class="ulp-captcha-container"] > div >div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[class="ulp-captcha-container"] > div >div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[class="ulp-captcha-container"] > div >div')) {
                break;
            }
        } else {
            break;
        }
    }
}

