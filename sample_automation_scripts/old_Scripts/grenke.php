<?php
// Server-Portal-ID: 34810 - Last modified: 19.02.2024 14:13:05 UTC - User: 1

public $baseUrl = 'https://www.grenke.de/';
public $loginUrl = 'https://login.grenke.net/index.php?id=411&L=2';

public $username_selector = 'input#user, div.formRow input[name="user"]';
public $password_selector = 'input#pass, div.formRow input[name="pass"]';
public $submit_login_selector = 'div.submitRowLogin input[type="submit"]';

public $check_login_failed_selector = 'div.error';
public $check_login_success_selector = 'a[href*="logintype=logout"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->disableUBlockExtension();
	$this->exts->openUrl($this->baseUrl);
	sleep(1);
	if ($this->exts->getElement('button#ppms_cm_agree-to-all, button#onetrust-accept-btn-handler') != null){
		$this->exts->moveToElementAndClick('button#ppms_cm_agree-to-all, button#onetrust-accept-btn-handler');
	}
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->clearChrome();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		if ($this->exts->getElement('button#ppms_cm_agree-to-all, button#onetrust-accept-btn-handler, #cookiebanner .cookiebanner-button span, a.c-cookie__button[data-value="allow"]') != null){
			$this->exts->moveToElementAndClick('button#ppms_cm_agree-to-all, button#onetrust-accept-btn-handler, #cookiebanner .cookiebanner-button span, a.c-cookie__button[data-value="allow"]');
			sleep(1);
		}
		$this->checkFillLogin();
		sleep(20);
	}
	
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		if (!$this->exts->exists('a.level1[href*="les-facture"], a.level1[href*="rechnungen"], a.level1[href*="invoice"]')) {
			$this->exts->moveToElementAndClick('a[href="/de_de/vertraege"');
			sleep(5);
		}
		
		// Open invoices url and download invoice
		$this->exts->moveToElementAndClick('a.level1[href*="les-facture"], a.level1[href*="rechnungen"], a.level1[href*="invoice"]');
		sleep(5);
		$this->exts->moveToElementAndClick('a.level2[href*="telecharger-les-facture"], a.level2[href*="rechnungen-herunterladen"], a.level2[href*="download-invoice"]');
		sleep(10);
		if ($this->exts->getElement('table > thead > tr > th input.checkbox') != null){
			$this->exts->moveToElementAndClick('table > thead > tr > th input.checkbox');
			sleep(2);
		}
		if ($this->exts->getElement('div.submitRow input.submit') != null){
			$this->exts->moveToElementAndClick('div.submitRow input.submit');
		}
		$this->processInvoices();
		
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
private function disableUBlockExtension(){
	$this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm');
	sleep(1);
		$this->exts->evaluate("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
			document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
		}");
	sleep(2);
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
		$this->exts->click_by_xdotool($this->password_selector);
		$this->exts->capture("2-login-page-filled");
		// $this->exts->moveToElementAndClick($this->submit_login_selector);
		$this->exts->type_key_by_xdotool("Return");
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function processInvoices($pageCount = 1) {
	sleep(10);

	$this->exts->capture("4-invoices-page");
	$invoices = [];
	$current_url_invoice = $this->exts->getUrl();
	
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 5 && $this->exts->getElement('a[href*="InvoiceId"]', $tags[4]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="InvoiceId"]', $tags[4])->getAttribute("href");
			$invoiceName = str_replace('/', '-', trim($tags[1]->getText()));
			$invoiceDate = trim($tags[3]->getText());
			$invoiceAmount = '';
			
			if(!empty($invoiceName)){
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceDate'=>$invoiceDate,
					'invoiceAmount'=>$invoiceAmount,
					'invoiceUrl'=>$invoiceUrl
				));
				$this->isNoInvoice = false;
			}
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$invoice_href = end(explode('/', $invoice['invoiceUrl']));
		// if(!$this->exts->exists('a[href*="'.$invoice_href.'"]')){
		// 	$this->exts->openUrl($current_url_invoice);
		// 	sleep(15);
		// }
		$this->exts->moveToElementAndClick('a[href*="'.$invoice_href.'"]');
		sleep(5);
		$download_buttons = $this->exts->getElements('div:not([style*="display: none"]) ul li a[href="#"]');
		$download_button = $this->exts->getElementByText('div:not([style*="display: none"]) ul li a[href="#"]', ['invoice for download', 'rechnung zum Ausdrucken', 'facture à télécharger'], null, false);
		if($download_button != null){
			try {
				$this->exts->log("Click button");
				$download_button->click();
				sleep(5);
			} catch (Exception $e) {
				$this->exts->log("Click buttonby by javascript ");
				$this->exts->execute_javascript('arguments[0].click()', [$download_button]);
				sleep(5);
			}
		}
		$this->exts->wait_and_check_download('pdf');
		$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
	
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 && $pageCount < 50 && $this->exts->getElement('li.pagernext a') != null){
		$this->exts->log('------------Click next page--------------');
		$pageCount++;
		$this->exts->moveToElementAndClick('li.pagernext a');
		sleep(5);
		$this->processInvoices($pageCount);
	}
}
