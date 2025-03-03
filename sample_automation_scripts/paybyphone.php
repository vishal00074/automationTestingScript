<?php // migrated
// Server-Portal-ID: 1334803 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://account.paybyphone-parken.de/de/private/transactions';
public $loginUrl = 'https://account.paybyphone-parken.de/de/login';
public $transactionPageUrl = 'https://account.paybyphone-parken.de/de/private/transactions';
public $invoicePageUrl = 'https://account.paybyphone-parken.de/de/private/invoices';

public $username_selector = 'input[formcontrolname="username"]';
public $password_selector = 'input[formcontrolname="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div.alert-danger';
public $check_login_success_selector = 'i.fa-power-off';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	
	// Load cookies
	// $this->exts->loadCookiesFromFile();
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
		$this->checkFillLogin();
		sleep(20);
	}
	
	// then check user logged in or not
	// for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
	// 	$this->exts->log('Waiting for login...');
	// 	sleep(5);
	// }
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		//if TOC found, accept it
		if($this->exts->exists('toc-privacy-updated button')){
			$this->exts->moveToElementAndClick('toc-privacy-updated button');
			$this->exts->log('*** Accept TOC ***');
			sleep(10);
		}
		
		// Open transaction url and download transaction
		if($this->exts->exists('a[href*="/transactions"]')) {
			$this->exts->moveToElementAndClick('a[href*="/transactions"]');
		} else {
			$this->exts->openUrl($this->transactionPageUrl);
		}
		sleep(15);
		$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
		$this->exts->moveToElementAndClick('select#period');
		sleep(2);
		if ($restrictPages == 0) {
			$this->exts->moveToElementAndClick('select#period option[value="forever"]');
		} else{
			$this->exts->moveToElementAndClick('select#period option[value="3months"]');
		}
		sleep(2);
		$this->exts->moveToElementAndClick('button[type="submit"]');
		sleep(40);
		$this->processTransactions();
		
		//Open invoice URL and download invoices
		if($this->exts->exists('a[href*="/invoices"]')) {
			$this->exts->moveToElementAndClick('a[href*="/invoices"]');
		} else {
			$this->exts->openUrl($this->invoicePageUrl);
		}
		$this->processInvoices();
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
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
	
	$this->exts->moveToElementAndClick('p.text-center button.btn-pc-accent');
	sleep(3);
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
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		if($this->exts->waitTillVisible('div.alert.alert-danger', 25)){
			$logged_in_failed_selector = $this->exts->getElementByText('div.alert.alert-danger', ['Invalid user credentials','UngÃ¼ltige Benutzeranmeldeinformationen']);
			if($logged_in_failed_selector != null) {
				$this->exts->loginFailure(1);
			} else if(strpos(strtolower($this->exts->extract('div.alert.alert-danger')), 'account disabled') !== false){
				$this->exts->log("account disabled");
				$this->exts->account_not_ready();
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}


private function processTransactions() {
	sleep(25);
	// for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
	// 	$this->exts->log('Waiting for invoice...');
	// 	sleep(5);
	// }
	$this->exts->capture("4-transactions-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $index=>$row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 10 && $this->exts->getElement('a.btn i.mdi-download', $tags[9]) != null) {
			$invoiceSelector = $this->exts->getElement('a.btn', $tags[9]);
			$this->exts->webdriver->executeScript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$index."');", [$invoiceSelector]);
			$invoiceName = trim($tags[8]->getText());
			$invoiceDate = trim($tags[0]->getText());
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getText())) . ' EUR';
			
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
			$this->isNoInvoice = false;
			$invoiceFileName = $invoiceName.'.pdf';
			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoiceName)){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				// click and download invoice
				$this->exts->moveToElementAndClick('a#custom-pdf-download-button-'.$index);
				sleep(5);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
					sleep(1);
				} else {
					$this->exts->log('Timeout when download '.$invoiceFileName);
				}
			}
		}
	}
}

private function processInvoices() {
	sleep(25);
	// for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
	// 	$this->exts->log('Waiting for invoice...');
	// 	sleep(5);
	// }
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $index=>$row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 2 && $this->exts->getElement('a.btn', $tags[1]) != null) {
			$invoiceSelector = $this->exts->getElement('a.btn', $tags[1]);
			$this->exts->webdriver->executeScript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$index."');", [$invoiceSelector]);
			$invoiceDate = trim(explode('-', $tags[0]->getText())[0]);
			$invoiceAmount = '';
			
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
			$this->isNoInvoice = false;
			$invoiceFileName = $invoiceName.'.pdf';
			
			$this->exts->moveToElementAndClick('a#custom-pdf-download-button-'.$index);
			sleep(5);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf');
			
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$invoiceFileName = basename($downloaded_file);
				$invoiceName = explode('.pdf', $invoiceFileName)[0];
				$invoiceName = trim(explode('(', $invoiceName)[0]);
				$this->exts->log('Final invoice name: '.$invoiceName);
				
				// Create new invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
					sleep(1);
				}
			} else {
				$this->exts->log('Timeout when download '.$invoiceFileName);
			}
		} else if(count($tags) >= 10 && $this->exts->getElement('a.btn', $tags[9]) != null) {
			$invoiceSelector = $this->exts->getElement('a.btn', $tags[9]);
			$this->exts->webdriver->executeScript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$index."');", [$invoiceSelector]);
			$invoiceDate = trim(explode('-', $tags[0]->getText())[0]);
			$invoiceAmount = trim($tags[6]->getText());
			$invoiceAmount = preg_replace('/[^\d\.\,]/', '', $invoiceAmount).' EUR';
			
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
			$this->isNoInvoice = false;
			$invoiceFileName = '';
			
			$this->exts->moveToElementAndClick('a#custom-pdf-download-button-'.$index);
			sleep(5);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf');
			
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$invoiceFileName = basename($downloaded_file);
				$invoiceName = explode('.pdf', $invoiceFileName)[0];
				$invoiceName = trim(explode('(', $invoiceName)[0]);
				$this->exts->log('Final invoice name: '.$invoiceName);
				
				// Create new invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
					sleep(1);
				}
			} else {
				$this->exts->log('Timeout when download '.$invoiceFileName);
			}
		}
	}
}