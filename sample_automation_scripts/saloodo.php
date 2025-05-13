<?php // migrated
// Server-Portal-ID: 25781 - Last modified: 02.04.2024 13:46:55 UTC - User: 1

public $baseUrl = "https://my.saloodo.com/login";
public $loginUrl = "https://my.saloodo.com/login";
// public $homePageUrl = "https://my.saloodo.com/dashboard";
public $homePageUrl = 'https://shipper.saloodo.com/orders';
public $username_selector = 'input#email, form[data-testid="loginForm"] input[type="email"]';
public $password_selector = 'input#password, form[data-testid="loginForm"] input[type="password"]';
public $submit_button_selector = 'button[type="submit"], form[data-testid="loginForm"] [data-testid="submitButton"] button[type="submit"]';
public $login_tryout = 0;
public $no_invoice = true;

private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->exts->openUrl($this->baseUrl);
	sleep(12);
	$this->exts->capture("Home-page-without-cookie");
	
	$isCookieLoginSuccess = false;
	if($this->exts->loadCookiesFromFile()) {
		$this->exts->openUrl($this->homePageUrl);
		sleep(15);
		if($this->checkLogin()) {
			$isCookieLoginSuccess = true;
		} else {
			$this->exts->clearCookies();
			$this->exts->openUrl($this->loginUrl);
			sleep(15);
		}
	}

	$this->exts->moveToElementAndClick('button#accept-recommended-btn-handler');
	sleep(5);
	
	if(!$isCookieLoginSuccess) {
		$this->exts->capture("after-login-clicked");
		
		$this->fillForm(0);
		sleep(10);
		if($this->checkLogin()) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$this->exts->capture("LoginSuccess");
			$this->invoicePage();
		} else {
			$this->exts->capture("LoginFailed");
			$this->exts->loginFailure();
		}
	} else {
		sleep(10);
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->invoicePage();
	}
}

function fillForm($count){
	$this->acceptCookies();
	$this->exts->log("Begin fillForm ".$count);
	try {
		sleep(5);
		if( $this->exts->getElement($this->username_selector) != null) {
			sleep(2);
			$this->login_tryout = (int)$this->login_tryout + 1;
			$this->exts->capture("1-pre-login");
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			sleep(2);
			
			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			sleep(5);
			
			$this->exts->moveToElementAndClick($this->submit_button_selector);
			
			sleep(10);
		}
		
		sleep(10);
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
	}
}

function checkLogin() {
	$this->exts->log("Begin checkLogin ");
	$isLoggedIn = false;
	try {
		$this->exts->moveToElementAndClick('a[data-testid="navigationItemOrders"] ~ div button[data-open="close"]');
		sleep(4);
		if($this->exts->getElement("a[href=\"/logout\"], button[name*='profile'], a[href*='/profile']") != null) {
			$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
			$isLoggedIn = true;
			
		}
	} catch(Exception $exception){
		$this->exts->log("Exception checking loggedin ".$exception);
	}
	return $isLoggedIn;
}

function invoicePage() {
	
	$this->exts->openUrl($this->homePageUrl);
	$this->exts->log("invoice Page");
	
	$this->acceptCookies();
	
	if ($this->exts->getElement(".intercom-post-close") != null) {
		$this->exts->moveToElementAndClick(".intercom-post-close");
		sleep(3);
	}
	
	
	// if ($this->exts->getElement("#__next button") != null) {
	//     $this->exts->moveToElementAndClick("#__next button");
	//     sleep(3);
	// }


	// click Später fragen
	if ($this->exts->getElement('div#__next [dir="ltr"] button.ApaFt') != null) {
		$this->exts->moveToElementAndClick('div#__next [dir="ltr"] button.ApaFt');
		sleep(5);
	}

	
	if ($this->exts->getElement("div.new-modal div.modal--close") != null) {
		$this->exts->moveToElementAndClick("div.new-modal div.modal--close");
		sleep(3);
	}
	$this->exts->moveToElementAndClick('div[value="active-orders"]');
	sleep(5);
	// click Archivierte Aufträge
	$this->exts->moveToElementAndClick('div#react-select-2-option-2');
	sleep(20);
	if ($this->exts->getElement("div.filter-content div.filters:nth-child(5) button:nth-child(3)") != null) {
		$this->exts->moveToElementAndClick("div.filter-content div.filters:nth-child(5) button:nth-child(3)");
		sleep(15);
	}
	
	
	
	$this->downloadInvoiceNew();
	
	if($this->no_invoice) {
		$this->exts->no_invoice();
	}
	$this->exts->success();
}

public $j = 0;
public $k = 0;
//https://ebilling.dhl.com/customer/document/258402713/page/pdf/
function downloadInvoice(){
	$this->exts->log("Begin downlaod invoice 1");
	
	sleep(15);
	
	// div.shipmentObjects div.shipmentObject div.file-icon a[href*="/invoice"]
	
	try{
		if($this->exts->getElement("div.shipmentObjects div.shipmentObject") != null) {
			$invoices = array();
			$receipts = $this->exts->etElements("div.shipmentObject");
			$this->exts->log(count($receipts));
			$count = 0;
			foreach ($receipts as $receipt) {
				$this->exts->log("each record");
				$this->exts->log($this->j);
				if ($this->j < count($receipts)) {
					$receiptDate = $this->exts->extract('div.deal-time-status-container span.date', $receipt); 
                  $receiptDate = trim(explode("-", $receiptDate)[0]);
					$this->exts->log($receiptDate);
					$receiptUrl = "div.shipmentObjects div.shipmentObject:nth-child(" . ($this->j + 1) . ") div.shipment-card-actions button";
					$receiptName = $this->exts->extract('div.shipment-card-cargo span.elmid', $receipt);
					$receiptName = trim(end(explode(":", $receiptName)));
					$receiptFileName = $receiptName . '.pdf';
					$this->exts->log($receiptName);
					$this->exts->log($receiptFileName);
					$this->exts->log($receiptUrl);
					$parsed_date = $this->exts->parse_date($receiptDate,'M. d, Y','Y-m-d');
					$this->exts->log($parsed_date);
					$receiptAmount = "";
					$this->exts->log($receiptAmount);
					$invoice = array(
						'receiptName' => $receiptName,
						'parsed_date' => $parsed_date,
						'receiptAmount' => $receiptAmount,
						'receiptFileName' => $receiptFileName,
						'receiptUrl' => $receiptUrl,
					);
					
					array_push($invoices, $invoice);
					$this->j += 1;
				}
				
			}
			
			$this->exts->log($this->j);
			foreach ($invoices as $invoice) {
				if ($this->exts->getElement($invoice['receiptUrl']) != null) {
					$this->exts->moveToElementAndClick($invoice['receiptUrl']);
					sleep(5);
				}
				
				$downloaded_file = $this->exts->click_and_download("div.shipmentObjects div.shipmentObject div.file-icon a[href*=\"/invoice\"]", 'pdf', $invoice['receiptFileName']);
				// $downloaded_file = $this->exts->download_current($receiptFileName);
				$this->exts->log("downloaded file");
				sleep(5);
				if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
					$this->exts->log("create file");
					$this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'] , $invoice['receiptAmount'], $downloaded_file);
					sleep(5);
				}
				
				if ($this->exts->getElement($invoice['receiptUrl']) != null) {
					$this->exts->moveToElementAndClick($invoice['receiptUrl']);
					sleep(5);
				}
			}
			
		} else {
			$this->exts->log("No invoice !!! ");
			$this->exts->no_invoice();
		}
		
	} catch(\Exception $exception){
		$this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
	}
}

private function downloadInvoiceNew($pageCount=1) {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	if($this->exts->exists('a[href$="invoice"]')){
		$rows = $this->exts->getElements('a[href$="invoice"]');
		foreach ($rows as $row) {
			
			$selector = "./../../../..";
			$actualRow = $row->findElement(WebDriverBy::xpath($selector));
			$firstDiv = $this->exts->getElement('div',$actualRow);
			$firstDivText = $firstDiv->getAttribute('innerText');
			$invoiceDate = trim(explode(",", explode("\n", $firstDivText)[1])[0]);
			
			$download_button = $row;
			$invoiceUrl = $download_button->getAttribute('href');
			$invoiceName = trim(explode("/", $invoiceUrl)[4]);
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceAmount = null;
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			$parsed_date = is_null($invoiceDate)?null:$this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
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
					$this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
				}
				sleep(20);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				$this->no_invoice = false;
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
					$this->isNoInvoice = false;
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			}
		}
	} else if($this->exts->exists('[data-testid="orderCardContainer"]')){
		$rows = $this->exts->getElements('[data-testid="orderCardContainer"]');
		foreach ($rows as $row) {
			if($this->exts->getElement('[data-testid="invoiceDownloadLink"]', $row) != null && $this->exts->getElement('a[href*=shipment]', $row) != null) {
				$invoiceUrl = $this->exts->extract('a[href*=shipment]', $row, 'href');
				$invoiceName = end(explode('=', $invoiceUrl));
				$invoiceFileName = $invoiceName.'.pdf';
				$download_button = $this->exts->getElement('[data-testid="invoiceDownloadLink"]', $row);
				$this->no_invoice = false;

				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceFileName: '.$invoiceFileName);

				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					try{
						$this->exts->log('Click download button');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
					}
					sleep(25);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}
	}
}

private function acceptCookies(){
	$cookie_button_selector = '#onetrust-accept-btn-handler';
	if($this->exts->exists($cookie_button_selector)){
		$this->exts->moveToElementAndClick($cookie_button_selector);
		sleep(5);
	}
}