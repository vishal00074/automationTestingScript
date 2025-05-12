<?php // migrated and updated login code
// Server-Portal-ID: 87305 - Last modified: 14.10.2024 14:03:30 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.vyond.com/';
public $loginUrl = 'https://app.vyond.com';
public $homePageUrl = "https://www.vyond.com/";
public $username_selector = 'input[type="email"]';
public $password_selector = 'input[type="password"]';
public $submit_button_selector = 'button[type="submit"]';
public $login_tryout = 0;
public $restrictPages = 3;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);

	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	
	$this->exts->openUrl($this->baseUrl);
	sleep(2);
	$this->exts->capture("Home-page-without-cookie");
	
	$isCookieLoginSuccess = false;
	if($this->exts->loadCookiesFromFile()) {
		$this->exts->openUrl($this->homePageUrl);
		sleep(15);

		$this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"],div.cookie-dialog-bottom button[class="cookie-button"]');
		sleep(5);


		if($this->checkLogin()) {
			$isCookieLoginSuccess = true;
		} else {
			$this->exts->clearCookies();
			$this->exts->openUrl($this->loginUrl);
			sleep(10);
		}
	}

	$this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"], div.cookie-dialog-bottom button[class="cookie-button"]');
	sleep(5);

	if(!$isCookieLoginSuccess) {
		$this->exts->capture("after-login-clicked");
		
		$this->fillForm(0);
		sleep(10);
		$this->checkFillTwoFactor();
		if ($this->exts->getElement('//div[contains(text(), "Unable to verify email or password")]', null, 'xpath') != null && $this->exts->getElement($this->username_selector) != null) {
			$this->exts->log("Wrong credentails!!");
			$this->exts->loginFailure(1);
		}
		


		if($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->invoicePage();
		} else {
			if($this->exts->getElement('//div[contains(text(), "Unable to verify email or password")]', null, 'xpath') != null && $this->exts->getElement($this->username_selector) != null){
				$this->exts->capture("LoginFailed");
				$this->exts->loginFailure(1);
			} else {
				$this->exts->capture("LoginFailed");
				$this->exts->loginFailure();
			}
		}
	} else {
		sleep(10);
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->invoicePage();
	}
}

function fillForm($count){
	$this->exts->log("Begin fillForm ".$count);
	try {
		sleep(5);
		if( $this->exts->getElement($this->username_selector) != null) {
			sleep(2);
			$this->login_tryout = (int)$this->login_tryout + 1;
			$this->exts->capture("1-pre-login");

			if ( $this->exts->getElement($this->username_selector) != null) {
				$this->exts->log("Enter Username");
				$this->exts->getElement($this->username_selector)->clear();
				$this->exts->moveToElementAndType($this->username_selector, $this->username);
				sleep(2);
			}

			$this->exts->moveToElementAndClick($this->submit_button_selector);
			sleep(5);

            $this->exts->waitTillPresent($this->password_selector);
			
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
		
			
			$this->exts->moveToElementAndClick($this->submit_button_selector);
	
			sleep(10);

			$login_anyway = $this->exts->getElementByText('button[type="button"] span', ['Login anyway','Trotzdem einloggen', 'Log in anyway']);
			if($login_anyway != null){
				$login_anyway->click();
				sleep(10);
			}
		}
		
		sleep(10);
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
	}
}

private function checkFillTwoFactor() {
	$two_factor_selector = '//label[contains(text(),"Enter Code")]/following-sibling::div/input';
	$two_factor_message_selector = '//h1[contains(text(),"Two-Factor Authentication")]/following-sibling::p';
	$two_factor_submit_selector = 'form button[type="submit"]';

	if($this->exts->getElement($two_factor_selector, null, 'xpath') != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText()."\n";
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
			$this->exts->getElement($two_factor_selector, null, 'xpath')->sendKeys($two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if($this->exts->getElement($two_factor_selector, null, 'xpath') == null){
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
	} else {
		$two_factor_selector = '//span[contains(text(),"Enter Code")]/../following-sibling::div/input';
		$two_factor_message_selector = '//span[contains(text(),"Two-Factor Authentication")]/../following-sibling::p';
		$two_factor_submit_selector = 'form button[type="submit"]';

		if($this->exts->getElement($two_factor_selector, null, 'xpath') != null && $this->exts->two_factor_attempts < 3){
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");

			if($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null){
				$this->exts->two_factor_notif_msg_en = "";
				for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
					$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText()."\n";
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
				$this->exts->getElement($two_factor_selector, null, 'xpath')->sendKeys($two_factor_code);
				
				$this->exts->log("checkFillTwoFactor: Clicking submit button.");
				sleep(3);
				$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

				$this->exts->moveToElementAndClick($two_factor_submit_selector);
				sleep(15);

				if($this->exts->getElement($two_factor_selector, null, 'xpath') == null){
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
}


/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		if($this->exts->getElement('div.user-button button, .dropdown-menu-user a[href*="/logoff"]') != null && $this->exts->getElement($this->username_selector) == null) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}
	} catch(Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}

function invoicePage() {
	$this->exts->log("Invoice page");
	
	if($this->exts->exists('div[class*="ModalDialog"]:not(.expiry-dialog) .close-button')){
		$this->exts->moveToElementAndClick('div[class*="ModalDialog"]:not(.expiry-dialog) .close-button');
		sleep(5);
	}
	if($this->exts->exists('.expiry-dialog .close-button')){
		$this->exts->moveToElementAndClick('.expiry-dialog .close-button');
		sleep(5);
	}
	$this->exts->moveToElementAndClick('button[aria-label="Account settings"],div.user-button a, a.dropdown-toggle[href*="/account"]');
	sleep(5);
	if($this->exts->exists('a[href*="com/account"]')){
		$this->exts->moveToElementAndClick('a[href*="com/account"]');
		sleep(15);

		$this->exts->moveToElementAndClick('a[href*="/account/billing"]');
		sleep(15);

		$this->downloadInvoice();
	} else {
		$this->exts->moveToElementAndClick('a[href*="/subscription"]');
		sleep(15);
		if($this->exts->exists('a[href*="/subscription/billinghistory"]')){
			$this->exts->moveToElementAndClick('a[href*="/subscription/billinghistory"]');
			sleep(15);
			$this->downloadInvoiceSubscription();
		} else {
			$this->exts->moveToElementAndClick('a[href*="/subscription/invoice"],a[href*="invoice"][target="subscription-suite"]');
			sleep(15);
			$handles = $this->exts->webdriver->getWindowHandles();
			if(count($handles) > 1){
				$this->exts->webdriver->switchTo()->window(end($handles));
				sleep(2);
			}
			$this->downloadInvoice();
		}
		
	}
	

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
function downloadInvoice(){
	$this->exts->log("Begin downlaod invoice 1");

	sleep(15);
	$this->exts->capture("4-invoices-page-1");
	try{
		$invoices = array();
		if($this->exts->getElement("div.table-responsive table tbody tr") != null) {
			$receipts = $this->exts->getElements("div.table-responsive table tbody tr");
			$this->exts->log(count($receipts));
			$count = 0;
			foreach ($receipts as $receipt) {
				$this->exts->log("each record");

				$this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"],div.cookie-dialog-bottom button[class="cookie-button"]');
				sleep(5);
				if ($this->exts->getElements("a[href*=\"/invoice/\"]", $receipt) != null) {
					$receiptDate = $this->exts->getElements("td:nth-child(1)", $receipt)[0]->getText(); 
					$this->exts->log("Invoice date: " . $receiptDate);
					$receiptUrl = $this->exts->getElements("a[href*=\"/invoice/\"]", $receipt)[0]->getAttribute('href'); 
					$receiptName = trim(explode("/", end(explode("/invoice/", $receiptUrl)))[0]);
					$receiptFileName = $receiptName . '.pdf';
					$this->exts->log("Invoice name: " . $receiptName);
					$this->exts->log("Invoice Filename: " . $receiptFileName);
					$this->exts->log("Inovice URL: " . $receiptUrl);
					$parsed_date = $this->exts->parse_date($receiptDate,'j M. Y','Y-m-d');
					$this->exts->log("Invoice Parsed date: " . $parsed_date);
					$receiptAmount = $receiptAmount = $this->exts->getElements("td.amount", $receipt)[0]->getText();
					$receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' USD';
					$this->exts->log("Inovice amount: " . $receiptAmount);
					$invoice = array(
						'receiptName' => $receiptName,
						'parsed_date' => $parsed_date,
						'receiptAmount' => $receiptAmount,
						'receiptFileName' => $receiptFileName,
						'receiptUrl' => $receiptUrl,
					);

					array_push($invoices, $invoice);
					
				}
			}			

			$this->exts->log("Number of invoices: " . count($invoices));
			foreach ($invoices as $invoice) {
				$this->exts->openUrl($invoice['receiptUrl']);
				sleep(10);

				if ($this->exts->getElement("table:nth-child(4) tr:first-child td:last-child") != null) {
					$downloaded_file = $this->exts->download_current($invoice['receiptFileName']);
					$this->exts->log("downloaded file");
					if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
						$this->exts->log("create file");
						$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'] , $invoice['receiptAmount'], $downloaded_file);
					}
				}

				$this->totalFiles += 1;
			}
		} else if($this->exts->exists('suite-invoice-table table > tbody > tr')){
			$invoices = [];
			$rows = count($this->exts->getElements('suite-invoice-table table > tbody > tr'));
			for ($i=0; $i < $rows; $i++) {
				$this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"],div.cookie-dialog-bottom button[class="cookie-button"]');
				sleep(5);
				$row = $this->exts->getElements('#recieptList table > tbody > tr')[$i];
				$tags = $this->exts->getElements('td', $row);
				if(count($tags) >= 4 && $this->exts->getElement('suite-button[text="btn_download"]', $tags[3]) != null) {
					$this->isNoInvoice = false;
					$download_button = $this->exts->getElement('suite-button[text="btn_download"]', $tags[3]);
					$invoiceName = trim($tags[1]->getAttribute('innerText'));
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceDate = trim($tags[0]->getAttribute('innerText'));
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoiceName);
					$this->exts->log('invoiceDate: '.$invoiceDate);
					$this->exts->log('invoiceAmount: '.$invoiceAmount);
					$parsed_date = $this->exts->parse_date($invoiceDate, 'M d Y','Y-m-d');
					$this->exts->log('Date parsed: '.$parsed_date);
					$this->totalFiles += 1;
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
						sleep(5);
						$this->exts->wait_and_check_download('pdf');
						$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

						if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
							$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
						} else {
							$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
						}
					}
				}
			}
		}
	} catch(\Exception $exception){
		$this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
	}
}

private function downloadInvoiceSubscription() {
	sleep(25);
	
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = count($this->exts->getElements('//a[contains(@href,"/invoice/")]/../..', null, 'xpath'));
	for ($i = 1; $i <= $rows; $i++) {
		$row = $this->exts->getElements('(//a[contains(@href,"/invoice/")]/../..)['.$i.']', null, 'xpath');
		$tags = $this->exts->getElements('(//a[contains(@href,"/invoice/")]/../..)['.$i.']/div', null, 'xpath');
		if(count($tags) >= 4) {
			$invoiceUrl = $this->exts->getElement('(//a[contains(@href,"/invoice/")]/../..)['.$i.']/div/a[contains(@href,"/invoice/")]', null, 'xpath')->getAttribute('href');
			$invoiceName = explode('?',
				array_pop(explode('invoice/', $invoiceUrl))
			)[0];
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->totalFiles += 1;
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		$this->exts->openUrl($invoice['invoiceUrl']);
		sleep(8);
		
		$downloaded_file = $this->exts->download_current($invoiceFileName, 5);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}