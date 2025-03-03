<?php // migrated and updated login code
// Server-Portal-ID: 37728 - Last modified: 06.02.2024 15:54:23 UTC - User: 1

public $baseUrl = 'http://www.amwater.com';
public $loginUrl = 'http://www.amwater.com';
public $invoicePageUrl = 'https://myaccount.amwater.com/accountSummary';

public $username_selector = '.auth-content-inner form input[name="identifier"]';
public $password_selector = '.auth-content-inner form input[name="credentials.passcode"]';
public $remember_me_selector = '';
public $submit_next_selector = '.auth-content-inner form input[type="submit"]';

public $submit_login_selector = '.auth-content-inner form input[type="submit"]';

public $check_login_failed_selector = 'div.messageError';
public $check_login_success_selector = '[data-target="#payment-menus"], div.userBtn'; 

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);		
	$this->exts->openUrl($this->baseUrl);
	sleep(5);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->exts->exists($this->check_login_success_selector)) {
		$this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
		$this->exts->moveToElementAndClick('button[id="login-button"]');

        sleep(5);
        $this->exts->moveToElementAndClick('button[id="submitLoginButton"]');
        sleep(10);
		$this->exts->log('form load');	

		$this->exts->capture("loadform-1");
	
		sleep(5);
		
		if($this->exts->waitTillPresent($this->username_selector)){
			$this->checkFillLogin();
			
			if($this->exts->exists('.loader .anticon-loading')){
				sleep(10);
			}
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);
			$this->exts->waitTillPresent($this->check_login_success_selector);

		}
		

	}

	// then check user logged in or not
	if($this->exts->exists($this->check_login_success_selector)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in '.$this->exts->getUrl());
		$this->exts->capture("3-login-success");
		
		// Open invoices url and download invoice
		if ($this->exts->exists('div.select-account-modal button')) {
			$this->exts->moveToElementAndClick('div.select-account-modal button');
			sleep(3);
		}
		if ($this->exts->exists('div.premise-table div.premise-row button')){
			$this->exts->capture('multiple-accounts');
			$this->exts->log('::Multiple accounts url '.$this->exts->getUrl());
			
			$paging_count = 0;
			for ($paging_count=0; $paging_count < 10; $paging_count++) {
				$accounts_count = count($this->exts->getElements('div.premise-table div.premise-row'));
				for ($account_idx=0; $account_idx < $accounts_count; $account_idx++) { 
					$account_row = $this->exts->getElements('div.premise-table div.premise-row')[$account_idx];
					if ($account_row == null) continue;
					$account_detail_button = $this->exts->getElementByText('button', ['ACCOUNT DETAILS'], $account_row, false);
					if ($account_detail_button == null) continue;
					$this->exts->log(trim(strtolower($account_detail_button->getAttribute('innerText'))));
					$this->exts->click_element($account_detail_button);
					sleep(5);
					if(!$this->exts->exists('.amw-bill-payment-card a.amw-account-summary-card-button')){
						sleep(5);
					}
					$this->exts->moveToElementAndClick('.amw-bill-payment-card a.amw-account-summary-card-button');
					$this->processInvoices();
					sleep(3);
					$this->exts->moveToElementAndClick('button.change-account-btn');
					sleep(5);
					if(!$this->exts->exists('div.premise-table div.premise-row')){
						sleep(5);
					}
				}
				if ($this->exts->exists('div.account-footer li.ant-pagination-next:not([aria-disabled="true"])')) {
					$this->exts->moveToElementAndClick('div.account-footer li.ant-pagination-next:not([aria-disabled="true"])');
					sleep(5);
				} else {
					break;
				}
			}				
		} else if ($this->exts->exists('div.myAccounts_IndividualCardId .accountDetailsBtn')){
			$this->exts->capture('multiple-accounts');
			$this->exts->log('::Multiple accounts url '.$this->exts->getUrl());
			if ($this->exts->exists('button[aria-label*="Dismiss"]')) {
				$this->exts->moveToElementAndClick('button[aria-label*="Dismiss"]');
				sleep(3);
			}
			$paging_count = 0;
			for ($paging_count=0; $paging_count < 10; $paging_count++) {
				$accounts_count = count($this->exts->getElements('div.myAccounts_IndividualCardId .webix_dataview_item button.accountDetailsBtn'));
				for ($account_idx=0; $account_idx < $accounts_count; $account_idx++) { 
					$account_detail_button = $this->exts->getElements('div.myAccounts_IndividualCardId .webix_dataview_item button.accountDetailsBtn')[$account_idx];
					$this->exts->click_element($account_detail_button);
					sleep(15);
					if ($this->exts->exists('.browserPopupCss .text_msg[onclick*="enhancedPortalCommonUtils.closeBrowserPopUp"]')) {
						$this->exts->moveToElementAndClick('.browserPopupCss .text_msg[onclick*="enhancedPortalCommonUtils.closeBrowserPopUp"]');
						sleep(3);
					}
					if ($this->exts->exists('[role="dialog"] .enh_Close_Icon')) {
						$this->exts->moveToElementAndClick('[role="dialog"] .enh_Close_Icon');
						sleep(3);
					}
					if ($this->exts->exists('[role="dialog"] [view_id="saveExistingEmail"]')) {
						$this->exts->moveToElementAndClick('[role="dialog"] [view_id="saveExistingEmail"]');
						sleep(10);
					}

					if(!$this->exts->exists('[view_id="viewbillinghistorybutton"] button')){
						sleep(10);
					}
					if ($this->exts->exists('button[aria-label*="Dismiss"]')) {
						$this->exts->moveToElementAndClick('button[aria-label*="Dismiss"]');
						sleep(3);
					}
					sleep(10);
					$this->exts->moveToElementAndClick('[view_id="viewbillinghistorybutton"] button');
					$this->processInvoicesV2();
					sleep(3);
					$this->exts->moveToElementAndClick('div[view_id="myAccountsCuecard"]');
					sleep(5);
					if(!$this->exts->exists('div.myAccounts_IndividualCardId .webix_dataview_item')){
						sleep(5);
					}
				}
				if ($this->exts->exists('div.account-footer li.ant-pagination-next:not([aria-disabled="true"])')) {
					$this->exts->moveToElementAndClick('div.account-footer li.ant-pagination-next:not([aria-disabled="true"])');
					sleep(5);
				} else {
					break;
				}
			}				
		} else {
			if ($this->exts->exists('.browserPopupCss .text_msg[onclick*="enhancedPortalCommonUtils.closeBrowserPopUp"]')) {
				$this->exts->moveToElementAndClick('.browserPopupCss .text_msg[onclick*="enhancedPortalCommonUtils.closeBrowserPopUp"]');
				sleep(3);
			}
			if ($this->exts->exists('[role="dialog"] .enh_Close_Icon')) {
				$this->exts->moveToElementAndClick('[role="dialog"] .enh_Close_Icon');
				sleep(3);
			}
			if ($this->exts->exists('[role="dialog"] [view_id="saveExistingEmail"]')) {
				$this->exts->moveToElementAndClick('[role="dialog"] [view_id="saveExistingEmail"]');
				sleep(10);
			}
			if ($this->exts->exists('button[aria-label*="Dismiss"]')) {
				$this->exts->moveToElementAndClick('button[aria-label*="Dismiss"]');
				sleep(3);
			}
			$this->exts->moveToElementAndClick('[view_id="viewbillinghistorybutton"] button');
			$this->processInvoicesV2();
			// $this->exts->moveToElementAndClick('.amw-bill-payment-card a.amw-account-summary-card-button');
			// $this->processInvoices();
		}
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed url: '.$this->exts->getUrl());
		if($this->exts->exists($this->check_login_failed_selector)) {
			$this->exts->log($this->exts->extract($this->check_login_failed_selector));
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function checkFillLogin() {

	if($this->exts->exists($this->username_selector)) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
		$this->exts->moveToElementAndClick($this->submit_next_selector);
		sleep(5);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);

		sleep(1);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

	

private function processInvoices($paging_count=1) {
	sleep(10);
	// for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('table > tbody > tr a[href*="/downloadinvoice') == null; $wait_count++) {
	// 	$this->exts->log('Waiting for invoice...');
	// 	sleep(5);
	// }
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $index=>$row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 5 && $this->exts->getElement('label.view-bill-cursor', $tags[1]) != null) {
			$invoiceSelector = $this->exts->getElement('label.view-bill-cursor', $tags[1]);
			$this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-button-".$index."');", [$invoiceSelector]);

			$account_number = trim(strtolower($this->exts->extract('div.account-no', null, 'innerText')));
			$this->exts->log('Account number origin: '. $account_number);
			$account_number = trim(end(explode("no: ", $account_number)));
			$this->exts->log('Account number: '. $account_number);
			$invoiceDate = trim($tags[0]->getAttribute('innerText'));
			$this->exts->log('Date before parsed: '.$invoiceDate);
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'm/d/Y','Y-m-d');
			if ($invoiceDate != null && $invoiceDate != '') $invoiceName = $invoiceDate;
			$invoiceName = $account_number.$invoiceDate;
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

			$this->isNoInvoice = false;
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);

			$invoiceFileName = $invoiceName.'.pdf';
			// Check if already exists
			if ($this->exts->document_exists($invoiceFileName)) {
				$this->exts->execute_javascript("arguments[0].removeAttribute('id');", [$invoiceSelector]);
				continue;
			}

			// click view invoice to show download invoice button
			$this->exts->moveToElementAndClick('#custom-pdf-button-'.$index);
			sleep(10);
			
			if ($this->exts->exists('div.react-pdf__Document button.dwldbtn')) {
				$downloaded_file = $this->exts->click_and_download('div.react-pdf__Document button.dwldbtn', 'pdf', $invoiceFileName, 'CSS', 10);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
				} else {
					$this->exts->log('Timeout when download '.$invoiceFileName);
				}
			}
			
			if ($this->exts->exists('div.ant-modal-content button.ant-modal-close')) {
				$this->exts->moveToElementAndClick('div.ant-modal-content button.ant-modal-close');
				sleep(1);
			}
			$this->exts->execute_javascript("arguments[0].removeAttribute('id');", [$invoiceSelector]);
		}
	}

	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 10 &&
		$this->exts->exists('li.ant-pagination-next:not([aria-disabled="true"])')
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('li.ant-pagination-next:not([aria-disabled="true"])');
		sleep(5);
		$this->processInvoices($paging_count);
	}
}
private function processInvoicesV2($paging_count=1) {
	sleep(10);
	
	$this->exts->capture("4-invoices-page-v2");
	$invoices = [];

	// $rows = $this->exts->getElements('div[view_id*="billingAndPaymentsHistoryTable"] .webix_ss_body [column="1"]');
	$rows = count($this->exts->getElements('div[view_id*="billingAndPaymentsHistoryTable"] .webix_ss_body [column="1"] [role="gridcell"]'));
	for ($i=0; $i < $rows; $i++) {
		$row = $this->exts->getElements('div[view_id*="billingAndPaymentsHistoryTable"] .webix_ss_body [column="1"] [role="gridcell"]')[$i];
		$download_button = $this->exts->getElement('[onclick*="pdfView"]', $row);
		if($download_button != null) {
			$this->isNoInvoice = false;
			$this->exts->log('--------------------------');

			try {
				$this->exts->log('Click download button');
				$download_button->click();
			} catch(\Exception $exception){
				$this->exts->log('Click download button by javascript');
				$this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
			}
			sleep(7);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf');

			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$invoiceName = basename($downloaded_file, '.pdf');
				$pdf_content = file_get_contents($downloaded_file);
				if(stripos($pdf_content, "%PDF") !== false) {
					$this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.":: Not Valid PDF - ".$downloaded_file);
				}
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}
	}
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 10 &&
		$this->exts->exists('button[webix_p_id="next"]')
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('button[webix_p_id="next"]');
		sleep(5);
		$this->processInvoicesV2($paging_count);
	}
}