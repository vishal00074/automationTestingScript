<?php // migrated and updated login code
// Server-Portal-ID: 24303 - Last modified: 12.11.2024 14:18:48 UTC - User: 1

public $baseUrl = 'https://www.shop-apotheke.com/account/index.htm';
public $loginUrl = 'https://www.shop-apotheke.com/nx/login/';
public $invoicePageUrl = 'https://www.shop-apotheke.com/account/orders.htm';

public $username_selector = 'input[id="login-modal-email"], form.login-form input#login-email, form[name=login-form] input[type=email]';
public $password_selector = 'input[id="login-modal-password"], form.login-form input#login-password, form[name=login-form] input[type=password]';
public $remember_me_selector = '';
public $submit_login_btn = 'button[data-qa-id="login-form-loginForm-submit-button"], form.login-form button#btn-login, form[name=login-form] #login-submit-btn:not([disabled])';

public $checkLoginFailedSelector = '#messages-error span, .m-Notification--error';
public $checkLoggedinSelector = 'a#mn-logout-link, button[data-qa-id="form-Menubar.Logout"], a[data-qa-id="account-button"]';

private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->exts->openUrl($this->loginUrl);

    $this->exts->waitTillPresent('button[class="dialog-title__close-button"]', 30);

    if($this->exts->exists('button[class="dialog-title__close-button"]')){
        $this->exts->click_by_xdotool('button[class="dialog-title__close-button"]');
    }
   
	$this->exts->capture("Home-page-without-cookie");
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	// after load cookies and open base url, check if user logged in
	if($this->exts->exists('a[href*="support.google"]')){
		$this->clearChrome();
		$this->exts->openUrl($this->loginUrl);
		sleep(10);
	}
	// Wait for selector that make sure user logged in
	
	if($this->checkLoggedIn()) {
		// If user has logged in via cookies, call waitForLogin
		$this->exts->log('Logged in from initPortal');
		$this->exts->capture('0-init-portal-loggedin');
	} else {
		// If user hase not logged in, open the login url and wait for login form
		$this->exts->log('NOT logged in from initPortal');
		$this->exts->capture('0-init-portal-not-loggedin');

		$this->exts->openUrl($this->loginUrl);
		sleep(10);
		$this->exts->execute_javascript('
			var shadow = document.querySelector("#usercentrics-root").shadowRoot;
			var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
			if(button){
				button.click();
			}
		');
		sleep(3);
		$this->fillForm(0);
		sleep(10);
	}

	if($this->checkLoggedIn()) {
		$this->exts->log('User logged in.');
		$this->exts->capture("3-logged-in-success");

		// Open invoices url
		$this->exts->openUrl($this->invoicePageUrl);
		$this->processInvoices();

		$this->exts->openUrl('https://www.shop-apotheke.com/nx/account/orders/');
		$this->processInvoices1();

		if ($this->allTotalFiles == 0) {
			$this->exts->log("No invoice !!! ");
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
		if(strpos(strtolower($this->exts->extract($this->checkLoginFailedSelector)), 'passwort sind falsch') !== false){
			$this->exts->loginFailure(1);
		} else if ($this->exts->getElement('[form="password-forgotten-form"][value="'.$this->username.'"]')) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function processCaptcha()
{
    $timeout = 200; 
    $interval = 5; 
    $startTime = time();
    $this->exts->log("Captcha Processing");

    while (time() - $startTime < $timeout) {
        if ($this->exts->exists('input[name="frc-captcha-solution"][value=".UNFINISHED"]')) {
            
            $this->exts->log("Process Captcha" . time());
        }else{
             $this->exts->log("Captcha decoded");
             break;
        }
         sleep($interval);
    }
}

private function fillForm($count = 0) {
	if($this->exts->exists($this->username_selector)){
		$this->exts->capture("2-pre-login");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);

        if($this->exts->exists('input[name="frc-captcha-solution"][value=".UNFINISHED"]')){
           $this->processCaptcha();
        }
		// if($this->exts->exists('form[name=login-form] #login-submit-btn[disabled]') && $count < 3){
		// 	$count++;
		// 	$this->fillForm();
		// }
		$this->exts->capture("2-filled-login");
		$this->exts->moveToElementAndClick($this->submit_login_btn);
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkLoggedIn() {
	$isLoggedIn = false;
    $this->exts->waitTillPresent('div[id="account-overview-user-basic-info"] > a[href="#myDataCard"]', 10);
    $this->exts->log('Begin Check LOGGIN');
	if($this->exts->exists('div[id="account-overview-user-basic-info"] > a[href="#myDataCard"]')){
        $isLoggedIn = true;
        $this->exts->log('-----is Logged in :'.$isLoggedIn);
	}
	return $isLoggedIn;
}

/**
 * Clearing browser history, cookie, cache 
 * 
 */
private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    $this->exts->type_key_by_xdotool('Return');
        sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function processInvoices() {
	if($this->exts->waitFor(
		function () use ($driver) {
			return count($this->exts->getElements('.order-container .order-header-row a[href*="/order-details.htm"]')) > 0;
		}
	, 30)) {
		$this->exts->log('Invoices found');
		$this->exts->capture("4-page-opened");
		$invoices = [];

		$rows = $this->exts->getElements('.order-container .order-header-row');
		foreach ($rows as $row) {
			$tags = $row->getElements('div.header-date , div.header-sum, div.header-order-number');
			if(count($tags) < 3){
				continue;
			}
			$as = $tags[2]->getElements('a[href*="/order-details.htm"]');
			if(count($as) == 0){
				continue;
			}

			$invoiceUrl = $as[0]->getAttribute("href");
			$invoiceName = explode('&',
				array_pop(explode('orderCode=', $invoiceUrl))
			)[0];
			$invoiceDate = trim(preg_replace('/datum/i', '',$tags[0]->getText()));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getText())) . ' EUR';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
		}

		// Download all invoices
		$this->exts->log('Invoices: '.count($invoices));
		$count = 1;
		$this->allTotalFiles = $totalFiles = count($invoices);

		foreach ($invoices as $invoice) {
			$invoiceFileName = $invoice['invoiceName'].'.pdf';

			$this->exts->log('date before parse: '.$invoice['invoiceDate']);

			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
			$this->exts->log('invoiceName: '.$invoice['invoiceName']);
			$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoice['invoiceName'])){
				$this->exts->log('Invoice existed '.$invoiceFileName);
			} else {
				$this->exts->log('Dowloading invoice '.$count.'/'.$totalFiles);
				$this->exts->openUrl($invoice['invoiceUrl']);
				sleep(3);

				$downloaded_file = '';
				if($this->exts->getElement('a[href*="/downloadDocument.go?docType=invoice"]') != null) {
					// if download pdf button is existed, click and download
					$downloaded_file = $this->exts->click_and_download('a[href*="/downloadDocument.go?docType=invoice"]', 'pdf', $invoiceFileName, 'CSS', 2);
				} else {
					$this->exts->execute_javascript("document.body.innerHTML = document.querySelector('.page-content .row-content .col-content').outerHTML;");
					$downloaded_file = $this->exts->download_current($invoiceFileName, 3);
				}

				sleep(2);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file))
				{
					$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
					sleep(1);
					$count++;
				} else {
					$this->exts->log('Timeout when download '.$invoiceFileName);
				}
				
			}
		}

	} else {
		$this->exts->log('Timeout processInvoices');
		$this->processInvoices1();
	}
}

public $allTotalFiles = 0;
function processInvoices1(){
	$this->exts->log("Begin download invoice");

	$this->exts->capture('4-List-invoice');
	$invoices = [];
	try{
		if ($this->exts->getElement('div.last-orders-item') != null) {
			$receipts = $this->exts->getElements('div.last-orders-item');
			$invoices = array();
			foreach ($receipts as $receipt) {
				if ($this->exts->getElement('a[href*="/downloadDocument."]', $receipt) != null) {
					$receiptDate = $this->exts->extract('div.date', $receipt);
					$receiptUrl = $this->exts->extract('a[href*="/downloadDocument."]', $receipt, 'href');
					$receiptName = $this->exts->extract('div.ordernr', $receipt);
					$receiptFileName = $receiptName . '.pdf';
					$parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y','Y-m-d');
					$receiptAmount = $this->exts->extract('div.sum', $receipt);
					$receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

					$this->exts->log("Invoice Date: " . $receiptDate);
					$this->exts->log("Invoice URL: " . $receiptUrl);
					$this->exts->log("Invoice Name: " . $receiptName);
					$this->exts->log("Invoice FileName: " . $receiptFileName);
					$this->exts->log("Invoice parsed_date: " . $parsed_date);
					$this->exts->log("Invoice Amount: " . $receiptAmount);
					$invoice = array(
						'receiptName' => $receiptName,
						'receiptUrl' => $receiptUrl,
						'parsed_date' => $parsed_date,
						'receiptAmount' => $receiptAmount,
						'receiptFileName' => $receiptFileName
					);
					array_push($invoices, $invoice);
				}
			}	

			$this->exts->log("Invoice found: " . count($invoices));

			foreach ($invoices as $invoice) {
				$this->allTotalFiles += 1;
				$downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
				if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
					$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'] , $invoice['receiptAmount'], $downloaded_file);
					
				}
			}
		} else if($this->exts->exists('[data-clientside-hook="OrdersList"] a[href*=order]')){
			$rows = $this->exts->getElements('[data-clientside-hook="OrdersList"] a[href*=order]');
			foreach ($rows as $row) {
				$invoiceUrl = $row->getAttribute('href');
				$invoiceName = end(explode('-', trim(preg_replace('/[\/\,]/',' ', $invoiceUrl))));
				
				array_push($invoices, array(
					'invoiceName'=>$invoiceName,
					'invoiceUrl'=>$invoiceUrl
				));
			}

			foreach ($invoices as $invoice) {
				$this->exts->openUrl($invoice['invoiceUrl']);
				sleep(3);
				$invoiceFileName = $invoice['invoiceName'].'.pdf';
				$invoiceDate = $this->exts->extract('h2[class*=OrderDetailsContent__GeneralInfo__title]', null, 'innerText');
				$invoiceDate = end(explode(' ', $invoiceDate));
				$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
				$invoiceLink = $this->exts->extract('a[data-qa-id="document-invoice-link"], #documents a[href*=orders]', null, 'href');

				if(!empty($invoiceLink)){
					$this->exts->log('--------------------------');
					$this->exts->log('invoiceName: '.$invoice['invoiceName']);
					// $this->exts->log('invoiceDate: '.$invoiceDate);
					// $this->exts->log('invoiceUrl: '.$invoiceLink);
					
					$downloaded_file = $this->exts->direct_download($invoiceLink, 'pdf', $invoiceFileName);
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoice['invoiceName'], '', '', $invoiceFileName);
						sleep(1);
						$this->allTotalFiles++;
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}

	} catch(\Exception $exception){
		$this->exts->log("Exception downloading invoice ".$exception->getMessage());
	}
}