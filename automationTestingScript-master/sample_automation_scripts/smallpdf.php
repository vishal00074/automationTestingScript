<?php
// Server-Portal-ID: 48361 - Last modified: 15.07.2024 14:27:40 UTC - User: 1

/*Define constants used in script*/
	public $baseUrl = 'https://smallpdf.com/profile';
	public $invoicePageUrl = 'https://smallpdf.com/profile#s=invoices';

	public $username_selector = 'form[name="login"] input[type="email"]';
	public $password_selector = 'form[name="login"] input[type="password"]';
	public $submit_login_selector = 'form[name="login"] button[type="submit"]';

	public $check_login_failed_selector = 'form[name="login"]';
	public $check_login_success_selector = 'a[href*="invoices"]';

	public $isNoInvoice = true;
	/**
	 * Entry Method thats called for a portal
	 * @param Integer $count Number of times portal is retried.
	 */
	private function initPortal($count) {
		$this->exts->log('Begin initPortal '.$count);
		$this->exts->openUrl($this->baseUrl);
		// sleep(1);
		
		// Load cookies
		// $this->exts->loadCookiesFromFile();
		// sleep(1);
		// $this->exts->openUrl($this->baseUrl);
		sleep(10);
		$this->exts->capture('1-init-page');
		
		// If user hase not logged in from cookie, clear cookie, open the login url and do login
		if($this->exts->getElement($this->check_login_success_selector) == null) {
			$this->exts->log('NOT logged via cookie');
			// $this->exts->clearCookies();
			$this->exts->openUrl($this->baseUrl);
			sleep(10);
			if($this->exts->getElement($this->password_selector) != null || stripos(strtolower($this->exts->extract('button[type="submit"]')), 'log in') !== false) {
				$this->exts->moveToElementAndClick('button[type="submit"]');
				sleep(5);
			}
			$this->checkFillLogin();
			sleep(10);
		}
		
		if($this->exts->getElement($this->check_login_success_selector) != null) {
			sleep(3);
			$this->exts->log(__FUNCTION__.'::User logged in');
			$this->exts->capture("3-login-success");
			
			// Open invoices url and download invoice
			$this->exts->openUrl($this->invoicePageUrl);
			sleep(15);
			$this->processInvoices();
			
			// Final, check no invoice
			if($this->isNoInvoice){
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			$this->exts->log(__FUNCTION__.'::Use login failed');
			$this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
			$error = $this->exts->extract($this->check_login_failed_selector);
			$this->exts->log('$error  :'.$error);
			if(stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'must be formatted as an email address') !== false ||
				stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'wrong email or password') !== false
			) {
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
			
			$this->exts->capture("2-login-page-filled");
			$this->exts->moveToElementAndClick($this->submit_login_selector);
			
		} else {
			$this->exts->log(__FUNCTION__.'::Login page not found');
			$this->exts->capture("2-login-page-not-found");
		}
	}

	private function processInvoices() {
		
		$this->exts->capture("4-invoices-page");
		$invoices = [];
		if($this->exts->exists('table > tbody > tr a[href*="/invoice')){
			$rows = $this->exts->getElements('table > tbody > tr');
			foreach ($rows as $row) {
				$tags = $this->exts->getElements('td', $row);
				if(count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice', $tags[4]) != null) {
					$invoiceUrl = $this->exts->getElement('a[href*="/invoice', $tags[4])->getAttribute("href");
					$invoiceName = explode('&',
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
				$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y','Y-m-d');
				$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
				
				$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
					sleep(1);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}
		} else {
			$invoices = [];
			$rows = count($this->exts->getElements('table > tbody > tr'));
			for ($i=0; $i < $rows; $i++) {
				$row = $this->exts->getElements('table > tbody > tr')[$i];
				$tags = $this->exts->getElements('td', $row);
				if(count($tags) >= 5 && stripos($this->exts->extract('button', $tags[4], 'innerText'), 'Download') !== false) {
					$this->isNoInvoice = false;
					$download_button = $this->exts->getElement('button', $tags[4]);
					$invoiceName = trim($tags[0]->getAttribute('innerText'));
					$invoiceFileName = $invoiceName.'.pdf';
					$invoiceDate = trim($tags[0]->getAttribute('innerText'));
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
					
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
						sleep(5);
						$this->exts->wait_and_check_download('pdf');
						$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
						
						if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
							$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
						} else {
							$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
						}
					}
				}
			}
		}
	}