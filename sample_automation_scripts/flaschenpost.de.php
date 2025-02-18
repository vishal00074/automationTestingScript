<?php
// Server-Portal-ID: 52779 - Last modified: 23.01.2025 07:10:08 UTC - User: 1

// Script here
public $baseUrl = 'https://www.flaschenpost.de/';
public $loginUrl = 'https://www.flaschenpost.de/account/login/';

public $username_selector = 'ion-input input[type="email"],input#emailLogin';
public $password_selector = 'ion-input input[type="password"],input#passwordLogin';
public $submit_login_selector = '.main_content_wrapper button[type="button"],form[data-validate="login"] button[type="submit"], button>ion-ripple-effect';

public $check_login_failed_selector = 'div[class*="secondary-red"],div.alert-danger';
public $check_login_success_selector = 'a[href="/account/logout/"], div[data-testid*="Liefer"]';

public $isNoInvoice = true; 
	public $zipCode = '';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->zipCode = isset($this->exts->config_array["zip_code"]) ? $this->exts->config_array["zip_code"] : '';
	$this->zipCode = '04105'; // harcoded for testing
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->check_solve_blocked_page();
	$this->exts->capture('1-init-page');
	//click solve to login check
	if($this->exts->exists('a[href="/Account/Overview"]')){
		$this->exts->moveToElementAndClick('a[href="/Account/Overview"]');
		sleep(15);
	}
	if($this->zipCode == null || $this->zipCode == ''){
		$this->exts->log('zip_code is empty');
		$this->exts->loginFailure(1);
	}
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		//$this->clearChrome();
		$this->exts->openUrl($this->loginUrl);
		sleep(10);
		$this->check_solve_blocked_page();
		
		if ($this->exts->exists('div.main_consent_modal button.fp_button_primary')) {
			$this->exts->moveToElementAndClick('div.main_consent_modal button.fp_button_primary');
			sleep(5);
		}
		if($this->exts->exists('button.fp_footer_changeZipCode')) {
			$this->exts->moveToElementAndClick('button.fp_footer_changeZipCode');
			sleep(1);
		}
		$this->enteZipCode();
		sleep(3);
		$this->check_solve_blocked_page();
		sleep(8);
		for ($i = 0; $i < 8; $i++) {
			// Extract the error message from the page
			$this->exts->capture('site-notworking-page'.$i);
			$err_msg1 = $this->exts->extract('div#main-frame-error h1 span');
			$lowercase_err_msg = strtolower($err_msg1);
			// Define the substring to search for
			$substring = "this page isn't working";

			// Check if the error message contains the specified substring
			if (strpos($lowercase_err_msg, $substring) !== false || stripos($lowercase_err_msg, 'Diese Seite funktioniert nicht') !== false) {
				// Retry opening the URL
				$this->exts->openUrl($this->loginUrl);
				sleep(30); // Wait for the page to load
				$this->check_solve_blocked_page();
				sleep(10);
			} else {
				// If the substring is not found, break the loop
				break;
			}
		}

		$this->checkFillLogin();
		sleep(15);
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
			strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false) { 
			$this->exts->loginFailure(1);
		}
		$this->exts->capture('2-after-login-submitted');
		if ($this->exts->getElement($this->username_selector) != null) {
			$this->checkFillLogin();
			sleep(15);
		}
		$this->check_solve_blocked_page();
		if ($this->exts->getElement($this->username_selector) != null) {
			$this->checkFillLogin();
			sleep(15);
		}
		sleep(10);
	}
	//click solve to login check
	if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
		strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false) {
		$this->exts->loginFailure(1);
	}
	if($this->exts->exists('a[href*="account_overview"]')){
		$this->exts->capture("after-login-submit");
		$this->exts->moveToElementAndClick('a[href*="account_overview"]');
		sleep(15);
	}
	
	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		// Final, check no invoice

		if($this->exts->exists('div[data-testid*="Liefer"]')){
			$this->exts->moveToElementAndClick('div[data-testid*="Liefer"]');
		}
		sleep(10);
		
		$this->downloadInvoice();

		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'eingaben noch einmal') !== false ||
			strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und versuche es nochmal') !== false) { 
			$this->exts->loginFailure(1);
		} else if($this->zipCode == null || $this->zipCode == ''){
			$this->exts->log('zip_code is empty');
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	if($this->exts->getElement($this->password_selector) != null) {
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(4);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(4);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);

		if($this->exts->getElement($this->password_selector)){
			sleep(4);
			$this->exts->moveToElementAndClick($this->submit_login_selector);
		}
		if($this->exts->getElement($this->password_selector)){
			sleep(4);
			$this->exts->moveToElementAndClick($this->submit_login_selector);
		}

	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function clearChrome(){
	$this->exts->log("Clearing browser history, cookie, cache");
	$this->exts->openUrl('chrome://settings/clearBrowserData');
	sleep(10);
	$this->exts->capture("clear-page");
	for ($i=0; $i < 2; $i++) { 
		$this->exts->type_key_by_xdotool('Tab');
	}
	$this->exts->type_key_by_xdotool('Tab');
	$this->exts->type_key_by_xdotool('Return');
	$this->exts->type_key_by_xdotool('a');
	sleep(1);
	$this->exts->type_key_by_xdotool('Return');
	sleep(3);
	$this->exts->capture("clear-page");
	for ($i=0; $i < 5; $i++) { 
		$this->exts->type_key_by_xdotool('Tab');
	}
	$this->exts->type_key_by_xdotool('Return');
	sleep(15);
	$this->exts->capture("after-clear");
}

private function check_solve_blocked_page()
{
	$this->exts->capture_by_chromedevtool("blocked-page-checking");

	for ($i = 0; $i < 5; $i++) {
		if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
			$this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
			$this->exts->refresh();
			sleep(10);

			$this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
			sleep(15);

			if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
				break;
			}
		} else {
			break;
		}
	}
}


private function enteZipCode(){
	$this->exts->log('isVisible Zip code: '.$this->zipCode);
	if($this->zipCode != null){
		$this->exts->capture("2-login-zip-code");
		$this->exts->log('Enter Zip code: '.$this->zipCode);
		$this->exts->moveToElementAndType('input.fp_input, [style="z-index: 20003;"], input[inputmode="numeric"]', $this->zipCode);
		sleep(2);
		// $this->type_text_by_xdotool($this->zipCode);
		// sleep(2);
		$this->exts->capture("2-login-zip-filled");
		if($this->exts->exists('.ion-page button.fp_button.fp_button_large.fp_button_primary')){
			$this->exts->moveToElementAndClick('.ion-page button.fp_button.fp_button_large.fp_button_primary');
		} else {
			$tab_button = $this->exts->getElements('.ion-page button.fp_button.fp_button_large.fp_button_primary');
			if($tab_button != null){
				try {
					$this->exts->log('Click tab_button...');
					$tab_button->click();
				} catch (Exception $e) {
					$this->exts->log("Click tab_button by javascript ");
					$this->exts->execute_javascript('arguments[0].click()', [$tab_button]);
				} 
			}
		}
		sleep(10);
	}
}

function downloadInvoice(){
	sleep(25);
	$this->exts->log("Begin download invoice");

	$this->exts->capture('4-List-invoice');

	// Get the current year
	$currentYear = date('Y');

	// Open the ion-select dropdown
	$this->exts->execute_javascript("
		let selectElement = document.querySelector('ion-select');
		selectElement.shadowRoot.querySelector('.select-text').click();
	");

	sleep(10);
	// Select the option inside the shadow DOM using JavaScript
	$this->exts->execute_javascript("
		let radioOptions = document.querySelectorAll('ion-select-popover ion-item ion-radio');
		
		// Loop through each ion-radio element
		radioOptions.forEach(option => {
			// Get the text content of the ion-radio element
			let optionText = option.textContent.trim();

			console.log(optionText); // Log the text content to the console

			// If the text matches the current year, click the option
			if (optionText === '$currentYear') {
				option.click();
			}
		});
	");


	sleep(15);

	try{
		if ($this->exts->getElement('div[class*="ion-activatable"]') != null) {
			$receipts = $this->exts->getElements('div[class*="ion-activatable"]');
			$invoices = array();
			
			for ($i = 0; $i < count($receipts); $i++) {
				// Get the tags under the current receipt
				$row =$this->exts->getElements('div[class*="ion-activatable"]')[$i];
				$tags = $this->exts->getElements('div', $row);
				
				// Check if there are any tags
				if (count($tags) >= 1) {
					// Scroll to the current element
					$this->exts->execute_javascript("arguments[0].scrollIntoView(true);", [$tags[0]]);
		
					// Locate the span elements within the current receipt
					$spanElements = $this->exts->getElements('span', $row);
					if (!empty($spanElements)) {
						$inviceName = $spanElements[0]->getAttribute('innerText');
						preg_match('/\d+/', $inviceName, $matches);
						$orderNumber = $matches[0];
		
						// Scroll to the download button (optional if already scrolled to the container)
						$this->exts->execute_javascript("arguments[0].scrollIntoView(true);", [$tags[0]]);
		
						try {
							$this->exts->log('Click download button');
							$tags[0]->click();
						} catch (\Exception $exception) {
							$this->exts->log('Click download button by JavaScript');
							$this->exts->execute_javascript("arguments[0].click();", [$tags[0]]);
						}
		
						// Handle file download
						$invoiceFileName = $orderNumber . '.pdf';
						$downloaded_file = $this->exts->download_current($invoiceFileName, 3);
						if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
							$this->exts->new_invoice($orderNumber, "", "", $invoiceFileName);
							sleep(1);
						} else {
							$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
						}
		
						$this->isNoInvoice = false;
		
						// Optional: Check if a specific element exists and click it
						if ($this->exts->exists('div[class*="tw-ws-cursor-pointer"]')) {
							$this->exts->moveToElementAndClick('div[class*="tw-ws-cursor-pointer"]');
							sleep(8);
						}
					}
				}
			}
		}
		

	} catch(\Exception $exception){
		$this->exts->log("Exception downloading invoice ".$exception->getMessage());
	}
}

private function download_current($filename, $delay_before_print = 0, $skip_check = false){
	try {
		$file_ext = $this->exts->get_file_extension($filename);
		$this->exts->no_margin_pdf = 0;
		$filepath = '';
		// Put some delay if page rendering takes time
		// If page is not loaded by ajax, then such delay is not required
		if ($delay_before_print > 0) {
			sleep($delay_before_print);
		}
		// Trigger print
		// Set window title to print, as chrome use window title to save pdf file
		$this->exts->execute_javascript('document.title = "print"; window.print();');
		sleep(10);
		// Wait for completion of file download
		$this->exts->wait_and_check_download($file_ext);
		// find new saved file and return its path
		$filepath = $this->exts->find_saved_file($file_ext, $filename);
	} catch (\Exception $exception) {
		$this->exts->log('ERROR in download_capture.');
		$this->exts->log(print_r($exception, true));
	}
	// find new saved file and return its path
	return $filepath;
}