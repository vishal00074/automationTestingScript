<?php // migrated updated download code
// Server-Portal-ID: 1361511 - Last modified: 09.09.2024 14:41:31 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk';
public $loginUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk/auth';
public $invoicePageUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk/ben_ges_dok_ueb';

public $username_selector = 'input#labelFor_name_ID';
public $password_selector = 'input#labelFor_password_ID';
public $remember_me_selector = '';
public $submit_login_selector = 'input.input-submit';

public $check_login_failed_selector = 'div.errorinfo';
public $check_login_success_selector = 'a[href*="logout"]';

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
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		$this->checkFillLogin();
		sleep(20);
	}

	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		//There are 2 different type of invoice pages for each type of user, we need to handle each of them.
		if ($this->exts->exists('a[href*="le_curr"]')) {
			$month_selectors = $this->exts->getElementsAttribute('li li a[href*="le_curr"]', 'href');
			foreach ($month_selectors as $key => $month_selector) {
				$this->exts->moveToElementAndClick('li li a[href="' . $month_selector .'"]');
				$this->processInvoicesByMonth();
			}
			
		}else{
			// Open invoices url and download invoice
			$this->exts->openUrl($this->invoicePageUrl);
			$this->processInvoices();
		}
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(stripos($this->exts->executeSafeScript("return document.querySelector('".$this->check_login_failed_selector."').innerText;"), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} elseif ($this->exts->urlContains('auth?auth_error=3')) {
			$this->exts->account_not_ready();
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

		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rowCount = count($this->exts->getElements('form#doMsgForm table tbody tr'));
	for ($i=0; $i < $rowCount; $i++) { 
		$row = $this->exts->getElements('form#doMsgForm table tbody tr')[$i];
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 6 && $this->exts->getElement('a', $tags[1]) != null) {
			$invoiceSelector = $this->exts->getElement('a', $tags[1]);
			$this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$i."');", [$invoiceSelector]);

			$invoiceName = trim($tags[3]->getText());
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = trim($tags[2]->getText());
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getText())) . ' EUR';

			$this->isNoInvoice = false;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);

			$this->exts->moveToElementAndClick("a#custom-pdf-download-button-".$i);
			sleep(5);
			$this->exts->moveToElementAndClick('a[onclick*="/gk/download/pdf/Rechnung"]');
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
					sleep(1);
				}
			} else {
				$this->exts->log('Timeout when download '.$invoiceFileName);
			}
			$this->exts->openUrl($this->invoicePageUrl);
			sleep(5);
		}
	}
}

private function processInvoicesByMonth() {
	sleep(25);

	$rowCount = count($this->exts->getElements('form#doMsgForm table tbody tr'));
	for ($i=0; $i < $rowCount; $i++) { 
		$row = $this->exts->getElements('form#doMsgForm table tbody tr')[$i];
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 6 && $this->exts->getElement('a[onclick*="/gk/download/pdf/Rechnung"]', $tags[7]) != null) {
			$invoiceSelector = $this->exts->getElement('a[onclick*="/gk/download/pdf/Rechnung"]', $tags[7]);
			$this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-".$i."');", [$invoiceSelector]);
			$invoiceName = trim($tags[3]->getText());
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = trim($tags[2]->getText());
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getText())) . ' EUR';

			$this->isNoInvoice = false;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);

			$this->exts->moveToElementAndClick("a#custom-pdf-download-button-".$i);
			sleep(1);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
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