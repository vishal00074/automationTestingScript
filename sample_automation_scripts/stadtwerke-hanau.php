<?php // migrated
// Server-Portal-ID: 104671 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

//q-card q-dialog-plugin error
//class="q-btn q-btn-item non-selectable no-outline q-btn--flat q-btn--rectangle text-primary q-btn--actionable q-focusable q-hoverable"

public $baseUrl = 'https://csc.stadtwerke-hanau.de/';
public $loginUrl = 'https://csc.stadtwerke-hanau.de/';
public $invoicePageUrl = 'https://csc.stadtwerke-hanau.de/rechnungen';

public $username_selector = 'form.plugin_login input[name="username"]';
public $password_selector = 'form.plugin_login input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form.plugin_login input[name="login"]';

public $check_login_failed_selector = '#msgbox.error li';
public $check_login_success_selector = '#menu a[href="abmelden"], #menu a[href="abmeldung"]';

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
		$this->checkFillLogin();
		sleep(30);
		// press enter 
		$this->exts->type_key_by_xdotool('Return');
		sleep(1);

	}

	// then check user logged in or not

	$this->exts->waitTillPresent($this->check_login_success_selector);
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		sleep(5);
		$this->exts->moveToElementAndClick('.bill input#open_all');
		sleep(2);
		// check multi account
		if($this->exts->getElement('.bill select[name="consumption"] option') != null){
			$accounts = $this->exts->getElements('.bill select[name="consumption"] option');
			array_walk($accounts, function(&$element){
				$element = $element->getAttribute('value');
			});
			$this->exts->log('Account found: '.count($accounts));
			// Loop through alls account by account number
			foreach ($accounts as $accountId) {
				sleep(3);
				$this->exts->log('PROCESS account '.$accountId);
				// $this->exts->selectDropdownByValue($this->exts->getElement('.bill select[name="consumption"]'), $accountId);
				sleep(5);

				$this->exts->execute_javascript('let selectBox = document.querySelector(".bill select[name="consumption"]");
				selectBox.value = '.$accountId.';
				selectBox.dispatchEvent(new Event("change"));');

				$this->processInvoices();
			}
		} else {
			$this->processInvoices();
		}

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
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		if ($this->exts->getElement('div._overlay') != null) {
			$this->exts->execute_javascript('
				document.querySelector("div._overlay").style.display = "none";
			');
		}
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
		sleep(10);

		$this->exts->type_key_by_xdotool('Space');
		sleep(5);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function Befor_processInvoices() {

	$this->exts->moveToElementAndClick('select[name*="consumption"]');
	sleep(2);
	$accounts = count($this->exts->getElements('select[name*="consumption"] option'));
	$this->exts->log('ACCOUNTS found: ' . $accounts);
	sleep(2);
	if($accounts > 1){
		for ($a=0; $a < $accounts; $a++) {
			$this->exts->log('SWITCH account'.$a);
			$account_button = $this->exts->getElements('select[name*="consumption"] option')[$a];
			try{
				$account_button->click();
			} catch(\Exception $exception){
				$this->exts->execute_javascript("arguments[0].click()", [$account_button]);
			}
			sleep(5);
			$this->processInvoices();
		}
	} else {

		$this->processInvoices();
	}
}	

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$this->exts->moveToElementAndClick('select[name*="consumption"]');
	sleep(2);
	$rows = $this->exts->getElements('.table_body_wrapper div.list:not([style*="display: none"])');
		foreach ($rows as $row) {
			$tags = $this->exts->getElements('.label_bill_number, .label_bill_date_table, .label_bill_value_table', $row);
			if(count($tags) >= 3 && $this->exts->getElement('a.download', $row) != null) {
				$invoiceSelector = 'a.download[title="'.$this->exts->getElement('a.download', $row)->getAttribute('title').'"]';
				$invoiceName = trim($tags[0]->getText());
				$invoiceDate = trim($tags[1]->getText());
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceSelector'=>$invoiceSelector
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
		$this->exts->log('invoiceSelector: '.$invoice['invoiceSelector']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		if($this->exts->invoice_exists($invoiceName)){
			$this->exts->log('Invoice existed '.$invoiceFileName);
		} else {
			$this->exts->moveToElementAndClick($invoice['invoiceSelector']);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::Timeout when download '.$invoiceFileName);
			}
		}
	}
}