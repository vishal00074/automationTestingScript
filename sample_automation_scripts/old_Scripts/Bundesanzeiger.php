<?php
// Server-Portal-ID: 5040 - Last modified: 21.11.2024 14:02:00 UTC - User: 1

public $baseUrl = "https://www.unternehmensregister.de/ureg/";
public $loginUrl = "https://www.unternehmensregister.de/ureg/";
// public $loginUrl = "https://publikations-plattform.de/sp/wexsservlet?page.navid=to_login_page&global_data.designmode=eb&dest=wexsservlet&global_data.language=de#b";
public $username_selector = 'form#loginForm input[name="loginForm:username"]';
public $password_selector = 'form#loginForm input[name="loginForm:password"]';
public $submit_button_selector = 'form#loginForm input[name="loginForm:btnLogin"]';
public $login_tryout = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
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
		$this->exts->openUrl($this->baseUrl);
		sleep(15);
		
		if($this->checkLogin()) {
			$isCookieLoginSuccess = true;
		} else {
			$this->exts->clearCookies();
			$this->exts->openUrl($this->loginUrl);
		}
	} else {
		$this->exts->openUrl($this->loginUrl);
	}

	if ($this->exts->exists('button#cc_all')) {
		$this->exts->moveToElementAndClick('button#cc_all');
		sleep(3);
	}
	
	if(!$isCookieLoginSuccess) {
		sleep(10);
		$this->fillForm(0);
		sleep(2);
		
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

function fillForm($count = 1){
	$this->exts->log("Begin fillForm ".$count);
	try {
		if($this->exts->exists('button#cc_all')){
			$this->exts->moveToElementAndClick('button#cc_all');
			sleep(5);
		}
		if ($this->exts->exists('button#login__content') && $this->exts->exists('button#login__content[aria-expanded="false"]')) {
			$this->exts->moveToElementAndClick('button#login__content');
			sleep(3);
		}
		sleep(1);
		if( $this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
			sleep(1);
			$this->login_tryout = (int)$this->login_tryout + 1;
			$this->exts->capture("1-pre-login");
			
			$this->exts->log("Enter Username");
			$this->exts->moveToElementAndType($this->username_selector, $this->username);
			
			$this->exts->log("Enter Password");
			$this->exts->moveToElementAndType($this->password_selector, $this->password);
			
			$this->exts->moveToElementAndClick($this->submit_button_selector);
			sleep(5);
			$err_msg = $this->exts->extract('.login__form .alert.alert-form-error');
			if ($err_msg != "" && $err_msg != null && strpos(strtolower($err_msg), 'passwor') !== false) {
				$this->exts->log($err_msg);
				$this->exts->loginFailure(1);
			}
		}
		
		sleep(10);
	} catch(\Exception $exception){
		$this->exts->log("Exception filling loginform ".$exception->getMessage());
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
		if($this->exts->exists('a.logout, a[href*="logout"]')) {
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
	
	if ($this->exts->exists('button#login__content') && $this->exts->exists('button#login__content[aria-expanded="false"]')) {
		$this->exts->moveToElementAndClick('button#login__content');
		sleep(3);
	}
	
	$this->exts->openUrl($this->exts->extract('div.login__links > ul > li:last-child a', null, 'href'));
	sleep(5);
	$this->exts->refresh();
	sleep(10);
	$this->downloadInvoice();
	
	// Final, check no invoice
	if($this->isNoInvoice){
		$this->exts->no_invoice();
	}
	$this->exts->success();
}

/**
	*method to download incoice
	*/
public function downloadInvoice($count=1, $pageCount=1){
	$this->exts->log("Begin download invoice");
	
	$this->exts->capture("4-invoices-page");
	
	$rows = $this->exts->getElements('div.result_container > div.row');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('div.col-md-3', $row);
		if(count($tags) >= 4 && $this->exts->getElement('a[href*=".pdf?session"]', $tags[3]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*=".pdf?session"]', $tags[3])->getAttribute("href");
			$invoiceName = trim($tags[1]->getAttribute('innerText'));
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceAmount = '';
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			$this->exts->log('invoiceUrl: '.$invoiceUrl);
			
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoiceDate);
			
			$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
			$this->isNoInvoice = false;
		} else if(count($tags) >= 4 && $this->exts->getElement('a[href*="-invoiceLink"]', $tags[3]) != null) {
			$invoiceDownloadBtn = $this->exts->getElement('a[href*="-invoiceLink"]', $tags[3]);
			$invoiceName = trim($tags[1]->getAttribute('innerText'));
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$invoiceAmount = '';
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			
			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoiceDate);
			
			try {
				$invoiceDownloadBtn->click();
			}  catch (\Exception $exception) {
				$this->exts->execute_javascript('arguments[0].click();', [$invoiceDownloadBtn]);
			}
			sleep(10);
			
			$this->exts->wait_and_check_download('pdf');
			
			$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
			$this->isNoInvoice = false;
		}
	}
}