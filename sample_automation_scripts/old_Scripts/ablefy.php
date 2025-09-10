<?php // updated login code and migrated
// Server-Portal-ID: 17443 - Last modified: 10.02.2025 07:33:48 UTC - User: 15

public $baseUrl = 'https://myablefy.com/cabinet';
public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = '.side-menu__content-accounts, div#profile-settings-menu, div[data-testid="elo-top-bar-profile"]';
public $isNoInvoice = true;

public $download_payouts = 0;
public $only_payouts = 0;
public $credit_memos = 0;
public $only_credit_memos = 0;
public $only_incoming_invoice = 0;

private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->download_payouts = isset($this->exts->config_array["download_payouts"]) ? (int)$this->exts->config_array["download_payouts"] : $this->download_payouts;
	$this->only_payouts = isset($this->exts->config_array["only_payouts"]) ? (int)$this->exts->config_array["only_payouts"] : $this->only_payouts;
	$this->credit_memos = isset($this->exts->config_array["credit_memos"]) ? (int)$this->exts->config_array["credit_memos"] : $this->credit_memos;
	$this->only_credit_memos = isset($this->exts->config_array["only_credit_memos"]) ? (int)$this->exts->config_array["only_credit_memos"] : $this->only_credit_memos;
	$this->only_incoming_invoice = isset($this->exts->config_array["incoming_invoice"]) ? (int)$this->exts->config_array["incoming_invoice"] : $this->only_incoming_invoice;

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	$this->exts->waitTillPresent('button[id="CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll"]');
	if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
		$this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
		sleep(3);
	}
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillPresent('button[id="CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll"]');
		// for ($i=0; $i < 3 && $this->exts->getElementByText('div.elopage-error-boundary__wrong-text-title', ['Oops, etwas ist schief gelaufen. Bitte aktualisiere die Seite und versuche es wieder', 'Oops, something went wrong. Please refresh the page and try again'], null, false) != null; $i++) { 
		// 	$this->exts->refresh();
		// 	sleep(20);
		// }
        if($this->exts->exists('button[id="CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll"]')){
            $this->exts->log('Accecpt Cookies');
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(3);
            }
        }
		
		$this->checkFillLogin();
		//sleep(20);
		$this->exts->waitTillPresent($this->check_login_success_selector,25);
	}

	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		$this->processAfterLogin();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
		$this->exts->loginFailure();
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
		// sleep(0.2);
		// if ($this->getElementByText('div.Toastify__toast-body', ['Ungültige Anmeldedaten.', 'Invalid email or password'], null, false) != null) {
		// 	$this->exts->loginFailure(1);
		// }
		if($this->exts->waitTillPresent('div.Toastify__toast-body', 15)){
			$this->exts->log("Login Failure : " . $this->exts->extract('div.Toastify__toast-body'));
			if(stripos($this->exts->extract('div.Toastify__toast-body'), 'Invalid email or password') !== false
				|| stripos($this->exts->extract('div.Toastify__toast-body'), 'ltige Anmeldedaten.') !== false){
				$this->exts->loginFailure(1);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}
private function processAfterLogin(){
	if($this->exts->exists('#beamerPushModal a#pushActionRefuse')) {
		$this->exts->moveToElementAndClick('#beamerPushModal a#pushActionRefuse');
	}
	if($this->exts->exists('#CybotCookiebotDialog:not([style*="display: none"]) a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')){
		$this->exts->moveToElementAndClick('#CybotCookiebotDialog:not([style*="display: none"]) a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
		sleep(2);
	}
	
	$this->exts->moveToElementAndClick('.side-menu__content-accounts, .topbar__user_info > .topbar__button-wrapper > button');// page changed 2023, added selector for account switch button
	sleep(5);
	$this->exts->capture("2-multi-accounts-checking");
	$total_accounts = count($this->exts->getElements('.accounts-modal__widgets-container .account-widget-container'));
	if($total_accounts > 0){
		// Publisher, Seller, Payer, team_member
		for ($a=0; $a < $total_accounts; $a++) {
			if($this->exts->exists('#beamerPushModal a#pushActionRefuse')) {
				$this->exts->moveToElementAndClick('#beamerPushModal a#pushActionRefuse');
			}
			if(!$this->exts->exists('.accounts-modal__widgets-container .account-widget-container')){
				$this->exts->moveToElementAndClick('.side-menu__content-accounts, .topbar__user_info > .topbar__button-wrapper > button');
				sleep(5);
			}
			$selecting_account = $this->exts->getElements('.accounts-modal__widgets-container .account-widget-container')[$a];
			$account_disabled = $this->exts->getElement('.account-widget--disabled', $selecting_account);
			if($account_disabled == null && $selecting_account != null){
				$account_label = $this->exts->extract('div.account-widget__details', $selecting_account);
				$this->exts->log("Selecting Account: \n$account_label");
				try{
					$this->exts->log('Click account');
					$selecting_account->click();
				} catch(\Exception $exception){
					$this->exts->log('Click account by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$selecting_account]);
				}
				sleep(7);

				$this->process_multi_downloading();
				sleep(3);
			} else {
				$this->exts->log('ignore disabled account');
				continue;
			}
		}
	} else {
		$this->process_multi_downloading();
	}

	if ($this->exts->urlContains('myablefy')) {
		if($this->exts->urlContains('/payer') && $this->exts->exists('a[href*="payer/orders"]')){
			$target_url = $this->exts->extract('a[href*="payer/orders"]', null, 'href');
			$this->exts->openUrl($target_url);
			$this->download_payer_order_invoices_for_myablefy();
		}
	}

	// Final, check no invoice
	if($this->isNoInvoice){
		$this->exts->no_invoice();
	}
	$this->exts->success();
}
private function process_multi_downloading(){
	if($this->exts->exists('#CybotCookiebotDialog:not([style*="display: none"]) a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')){
		$this->exts->moveToElementAndClick('#CybotCookiebotDialog:not([style*="display: none"]) a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
		sleep(2);
	}

	if($this->only_payouts == 1){
		$this->exts->log('Only payout');
		// In one account maybe have both team_member's payouts and other payouts
		if($this->exts->exists('.side-menu__content a[href*="/team_member/payouts_balance"]')){// Member payout
			$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href="/team_member/payouts_balance"]');
			sleep(5);
			$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
			$this->download_payout();
		}
		if($this->exts->exists('.side-menu__content a[href*="/cabinet/payouts_balance"]')){// Cabinet payout
			$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href*="/cabinet/payouts_balance"]');
			sleep(5);
			$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
			$this->download_payout();
		} 

		if($this->exts->exists('.side-menu__content a[href*="/payouts_balance"]') && !$this->exts->oneExists(['a[href="/team_member/payouts_balance"]', 'a[href*="/cabinet/payouts_balance"]'])){// Any type of payout
			$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href*="/payouts_balance"]');
			sleep(3);
			$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
			$this->download_payout();
		}
	} else if($this->only_incoming_invoice == 1){
		$this->exts->log('Only incoming invoice');
		if($this->exts->exists('.side-menu__content a[href$="/billing"]')){// Billing button is in left side bar
			$target_url = $this->exts->extract('.side-menu__content a[href$="/billing"]', null, 'href');
			$this->exts->openUrl($target_url);
			// $this->exts->moveToElementAndClick('.side-menu__content a[href$="/billing"]');
			$this->download_monthly_fee_invoice();
		} else {
			// Maybe billing button is in profile menu
			$this->exts->moveToElementAndClick('#profile-settings-menu');
			sleep(2);
			
			if($this->exts->exists('.profile-settings a[href$="/billing"]')){// Billing button is in left side bar
				$target_url = $this->exts->extract('.profile-settings a[href$="/billing"]', null, 'href');
				$this->exts->openUrl($target_url);
				// $this->exts->moveToElementAndClick('.side-menu__content a[href$="/billing"]');
				$this->download_monthly_fee_invoice();
			}
		}

		$this->exts->openUrl($this->baseUrl);
		sleep(10);

		if($this->exts->exists('.side-menu__content a[href$="/invoices"]')){
			// $this->exts->moveToElementAndClick('.side-menu__content ul ul a[href$="/invoices"]');
			$target_url = $this->exts->extract('.side-menu__content a[href$="/invoices"]', null, 'href');
			$this->exts->openUrl($target_url);
			sleep(3);
			$this->download_invoices();
		}
		
		if($this->exts->urlContains('/payer') && $this->exts->exists('.side-menu__content a[href*="payer/orders"]')){
			$target_url = $this->exts->extract('.side-menu__content a[href*="payer/orders"]', null, 'href');
			$this->exts->openUrl($target_url);
			$this->download_payer_order_invoices();
		}
	} else if($this->only_credit_memos == 1){
		$this->exts->log('Only credit memos');
		if($this->exts->exists('.side-menu__content a[href$="/credit_memos"]')){
			$target_url = $this->exts->extract('.side-menu__content a[href$="/credit_memos"]', null, 'href');
			$this->exts->openUrl($target_url);
			// $this->exts->moveToElementAndClick('.side-menu__content ul ul a[href$="/credit_memos"]');
			$this->download_credit_memos();
		}
	} else {
		$this->exts->log('Download all');
		if($this->download_payouts == 1){
			$this->exts->log('Download payout');
			// In one account maybe have both team_member's payouts and other payouts
			if($this->exts->exists('.side-menu__content a[href*="/team_member/payouts_balance"]')){// Member payout
				$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href="/team_member/payouts_balance"]');
				sleep(5);
				$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
				$this->download_payout();
			}
			if($this->exts->exists('.side-menu__content a[href*="/cabinet/payouts_balance"]')){// Cabinet payout
				$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href*="/cabinet/payouts_balance"]');
				sleep(5);
				$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
				$this->download_payout();
			} 

			if($this->exts->exists('.side-menu__content a[href*="/payouts_balance"]') && !$this->exts->oneExists(['a[href="/team_member/payouts_balance"]', 'a[href*="/cabinet/payouts_balance"]'])){// Any type of payout
				$this->exts->moveToElementAndClick('.side-menu__content ul ul a[href*="/payouts_balance"]');
				sleep(3);
				$this->exts->moveToElementAndClick('.elo-page-tabs a[href$="/payouts"]');
				$this->download_payout();
			}
		}


		// Monthly Elopage incoming invoice (= fee invoice):
		if($this->exts->exists('.side-menu__content a[href$="/billing"]')){// Billing button is in left side bar
			$target_url = $this->exts->extract('.side-menu__content a[href$="/billing"]', null, 'href');
			$this->exts->openUrl($target_url);
			// $this->exts->moveToElementAndClick('.side-menu__content a[href$="/billing"]');
			$this->download_monthly_fee_invoice();
		} else {
			// Maybe billing button is in profile menu
			$this->exts->moveToElementAndClick('#profile-settings-menu');
			sleep(2);
			
			if($this->exts->exists('.profile-settings a[href$="/billing"]')){// Billing button is in left side bar
				$target_url = $this->exts->extract('.profile-settings a[href$="/billing"]', null, 'href');
				$this->exts->openUrl($target_url);
				// $this->exts->moveToElementAndClick('.side-menu__content a[href$="/billing"]');
				$this->download_monthly_fee_invoice();
				//Move back to the main page after go to billing page.
				$this->exts->moveToElementAndClick('div.side-menu__own-header i.fa-angle-left');
				sleep(5);
			}
		}

		$this->exts->openUrl($this->baseUrl);
		sleep(10);

		// Monthly Elopage account statements (= account statement PLUS):
		if($this->exts->exists('.side-menu__content a[href$="/financial_reports"]')){
			$target_url = $this->exts->extract('.side-menu__content a[href$="/financial_reports"]', null, 'href');
			$this->exts->openUrl($target_url);
			// $this->exts->moveToElementAndClick('.side-menu__content ul ul a[href$="/financial_reports"]');
			$this->download_account_statement();
		}

		if($this->credit_memos == 1 && $this->exts->exists('.side-menu__content a[href$="/credit_memos"]')){
			$target_url = $this->exts->extract('.side-menu__content a[href$="/credit_memos"]', null, 'href');
			$this->exts->openUrl($target_url);
			// $this->exts->moveToElementAndClick('.side-menu__content ul ul a[href$="/credit_memos"]');
			$this->download_credit_memos();
		}

		$this->exts->openUrl($this->baseUrl);
		sleep(10);

		if($this->exts->exists('.side-menu__content a[href$="/invoices"]')){
			// $this->exts->moveToElementAndClick('.side-menu__content ul ul a[href$="/invoices"]');
			$target_url = $this->exts->extract('.side-menu__content a[href$="/invoices"]', null, 'href');
			$this->exts->openUrl($target_url);
			sleep(3);
			$this->download_invoices();
		}

		if($this->exts->urlContains('/payer') && $this->exts->exists('.side-menu__content a[href*="payer/orders"]')){
			$target_url = $this->exts->extract('.side-menu__content a[href*="payer/orders"]', null, 'href');
			$this->exts->openUrl($target_url);
			$this->download_payer_order_invoices();
		}
	}
}

private function download_invoices() {
	$this->exts->update_process_lock();
	sleep(10);
	$this->exts->capture("4-invoices-page");
	$invoice_page_url = $this->exts->getUrl();
	$invoices = [];
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	$rows = $this->exts->getElements('table > tbody > tr');

	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('.elo-table__details a[href*="/invoices/"]:not([href*="/null"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
			$invoiceName = trim($invoiceName);
			$invoiceDate = '';
			$invoiceAmount = '';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->isNoInvoice = false;
		}
	}
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	$numberOfPageToDownload = $restrictPages == 0 ? 50 : 5;
	for ($i=0; $i < $numberOfPageToDownload && $this->exts->exists('div.orders-list a.elo-pagination__link--next[aria-label*="Next"][aria-disabled="false"]'); $i++) { 
		$this->exts->moveToElementAndClick('div.orders-list a.elo-pagination__link--next[aria-label*="Next"][aria-disabled="false"]');
		sleep(10);
		$rows = $this->exts->getElements('table > tbody > tr');

		foreach ($rows as $row) {
			$download_link = $this->exts->getElement('.elo-table__details a[href*="/invoices/"]:not([href*="/null"]', $row);
			if($download_link != null) {
				$invoiceUrl = $download_link->getAttribute("href");
				$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
				$invoiceName = trim($invoiceName);
				$invoiceDate = '';
				$invoiceAmount = '';

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
		if(!$this->exts->invoice_exists($invoice['invoiceName'])){
			$this->exts->openUrl($invoice['invoiceUrl']);
			sleep(5);

			if($this->exts->exists('a[href*="/invoices/"][href*=".pdf"]')){
				$invoice_url = $this->exts->extract('a[href*="/invoices/"][href*=".pdf"]', null, 'href');
				$this->exts->log('invoice PDF Url: '.$invoice_url);
				$invoiceFileName = $invoice['invoiceName'].'.pdf';
				$downloaded_file = $this->exts->direct_download($invoice_url, 'pdf', $invoiceFileName);
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
			} else {
				$this->exts->capture("invoice-detail-no-download-button");
			}
		} else {
			$this->exts->log('Invoice Existed');
		}
	}
}
private function download_payout() {
	$this->exts->update_process_lock();
	sleep(10);
	if($this->exts->config_array["restrictPages"] == '0'){
		$this->exts->moveToElementAndClick('.elo-pagination-container .elo-show-items .elo-select-field__control');
		sleep(1);
		$this->exts->moveToElementAndClick('.elo-select-field__option:nth-child(7)');
		sleep(15);
	}
	$this->exts->capture("4-payout-page");

	$invoices = [];
	$rows = $this->exts->getElements('table.elo-table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('a[href*="/payouts/"]:not([href*="details"])', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
			$invoiceName = trim($invoiceName);
			$invoiceDate = trim($this->exts->extract('td:nth-child(4)', $row));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(10)', $row))) . ' EUR';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->isNoInvoice = false;
		} else if($this->exts->getElement('a[href*="/payouts/"][".pdf"]', $row) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="/payouts/"][".pdf"]', $row)->getAttribute("href");
			$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
			$invoiceName = trim($invoiceName);
			$invoiceDate = trim($this->exts->extract('td:nth-child(4)', $row));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(10)', $row))) . ' EUR';

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
	$this->exts->log('Payouts found: '.count($invoices));
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y h:i','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}
private function download_account_statement() {
	$this->exts->update_process_lock();
	sleep(10);
	$this->exts->capture("4-account_statement");

	$invoices = [];
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('a[href*="/financial_reports/"][href*=".pdf"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			if (strpos($invoiceUrl, 'wallet_id') !== false && strpos($invoiceUrl, 'end_date') !== false && strpos($invoiceUrl, 'number') !== false) {
				parse_str(parse_url($invoiceUrl)["query"], $output);
				$invoiceName = $output["wallet_id"]. $output["start_date"].$output["end_date"].$output["number"];
			} else if(strpos($invoiceUrl, '.pdf?id=') !== false){
				$invoiceName = explode('&',
					array_pop(explode('.pdf?id=', $invoiceUrl))
				)[0];
			} else{
				continue;
			}
			$invoiceDate = '';
			$invoiceAmount = '';

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
	$this->exts->log('Statements found: '.count($invoices));
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}
private function download_monthly_fee_invoice() {
	$this->exts->update_process_lock();
	sleep(10);
	$this->exts->moveToElementAndClick('.billing .apps-container + * .elo-pagination-container .elo-show-items .elo-select-field__control');
	sleep(1);
	$this->exts->moveToElementAndClick('.elo-select-field__option:nth-child(6)');
	sleep(15);
	$this->exts->capture("4-monthly_fee_invoice");
	
	$rows = count($this->exts->getElements('.billing .apps-container + * table tr'));
	for ($i=0; $i < $rows; $i++) {
		$row = $this->exts->getElements('.billing .apps-container + * table tr')[$i];
		$action_button =  $this->exts->getElement('div.tooltip-menu__container', $row);
		if($action_button != null) {
			$invoiceName = $this->exts->extract('td:nth-child(1)', $row);
			$invoiceName = trim($invoiceName);
			$invoiceDate = '';
			$invoiceAmount = '';
			
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);
			
			// Download invoice if it not exisited
			if($this->exts->invoice_exists($invoiceName)){
				$this->isNoInvoice = false;
				$this->exts->log('Invoice existed '.$invoiceFileName);
				continue;
			}
			
			try{
				$this->exts->log('Click button More');
				$action_button->click();
			} catch(\Exception $exception){
				$this->exts->log('Click button More by javascript');
				$this->exts->execute_javascript("arguments[0].click()", [$action_button]);
			}
			sleep(2);
			$download_button = $this->exts->getElement('//*[contains(@class, "tooltip-menu__popover-container fade show")]//button[text()="Beleg herunterladen" or text()="Download document"]', null, 'xpath');
			if($download_button != null) {
				$this->isNoInvoice = false;
				$invoiceFileName = $invoiceName . '.pdf';
				try{
					$this->exts->log('Click download_button');
					$download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click download_button by javascript');
					$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
				}
				sleep(3);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
				
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
				}
				$this->isNoInvoice = false;
			} else {
				$this->exts->capture("monthly_invoice-no-download-option");
			}
		}
	}
}
private function download_credit_memos() {
	$this->exts->update_process_lock();
	sleep(10);
	if($this->exts->config_array["restrictPages"] == '0'){
		$this->exts->moveToElementAndClick('.elo-pagination-container .elo-show-items .elo-select-field__control');
		sleep(1);
		$this->exts->moveToElementAndClick('.elo-select-field__option:nth-child(7)');
		sleep(15);
	}
	$this->exts->capture("4-credit-memos");

	$invoices = [];
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('a[href*="/credit_memos"][href*=".pdf"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = $this->exts->extract('td:nth-child(1)', $row);
			$invoiceName = trim($invoiceName);
			$invoiceDate = '';
			$invoiceAmount = '';

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
	$this->exts->log('Credit-memos found: '.count($invoices));
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}
private function download_payer_order_invoices() {
	$this->exts->update_process_lock();
	sleep(10);
	$this->exts->moveToElementAndClick('.elo-pagination-container .elo-show-items .elo-select-field__control');
	sleep(1);
	$this->exts->moveToElementAndClick('.elo-select-field__option:nth-child(6)');
	sleep(20);
	if($this->exts->exists('.lmask')){
		// if page still loading, wait more
		sleep(50);
	}
	$this->exts->capture("4-payer-order");
	$count_products = count($this->exts->getElements('#products-table table > tbody > tr'));
	$this->exts->log($count_products);
	for ($p=0; $p < $count_products; $p++) { 
		$product_order = $this->exts->getElements('#products-table table > tbody > tr')[$p];
		if($this->exts->getElement('[id*="form-info"] .fa-gift', $product_order) != null){// ignore gift orders because no payment needed for gift.
			continue;
		}

		$order_manage_button = $this->exts->getElement('[data-testid="tooltip-custom-icon"].fa-pencil-alt', $product_order);
		// each orders contains "edit" button, click it, website will open new tab for manage order page, we find invoice in this manage page.
		if($order_manage_button != null){
			$before = $this->exts->get_all_tabs();
			try{
				$this->exts->log('Click manage order');
				$order_manage_button->click();
			} catch(\Exception $exception){
				$this->exts->log('Click manage order by javascript');
				$this->exts->execute_javascript("arguments[0].click()", [$order_manage_button]);
			}

			sleep(2);
			$this->exts->switchToIfNewTabOpened();
			// $after = $this->exts->get_all_tabs();
			// $diff = GmiBrowserTarget::targetArrayDiff($before, $after);
			// if (!empty($diff->notInLeft)) {
			// 	$this->exts->switchToTab(reset($diff->notInLeft));
			// 	$this->exts->log('Switched to new tab');
			// }

			$this->exts->waitTillPresent('.payment-manage .collapsible .collapsible__arrow:not(.active)');
			if($this->exts->exists('.payment-manage .collapsible .collapsible__arrow:not(.active)')){ // expand invoices section
				$this->exts->moveToElementAndClick('.collapsible .collapsible__arrow:not(.active)');
			} else {
				$this->exts->capture("4-abnormal-order-detail");
			}
			sleep(5);
			$this->exts->capture("4-payer-order-detail");
			// Download all invoices
			$invoice_links = $this->exts->getElements('tr a[href*="/invoices/"]');
			$this->exts->log('Invoices found in order:' . count($invoice_links));
			foreach ($invoice_links as $invoice_link) {
				$invoiceName = trim($invoice_link->getAttribute('innerText'));
				$invoiceUrl = $invoice_link->getAttribute('href');
				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceUrl: '.$invoiceUrl);
				if(!$this->exts->invoice_exists($invoiceName)){
					$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceName.'.pdf');
					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
					}
				} else {
					$this->exts->log('Invoice Existed: ' . $invoiceName);
				}
				$this->isNoInvoice = false;
			}
			
			// close new tab, back to origin tab
			$this->exts->switchToInitTab();
			$this->exts->closeAllTabsButThis();
		}
	}
}
private function download_payer_order_invoices_for_myablefy($paging_count=1) {
	$this->exts->update_process_lock();
	sleep(10);
	$this->exts->moveToElementAndClick('.elo-pagination-container .elo-show-items .elo-select-field__control');
	sleep(1);
	$this->exts->moveToElementAndClick('.elo-select-field__option:nth-child(6)');
	sleep(20);
	if($this->exts->exists('.lmask')){
		// if page still loading, wait more
		sleep(50);
	}
	$this->exts->capture("4-payer-order-myablefy");
	$orders = $this->exts->getElementsAttribute('a.orders__link', 'href');
	$newTab = $this->exts->openNewTab();
	foreach ($orders as $key => $order) {
		$this->exts->openUrl($order);
		sleep(3);
		$this->exts->waitTillPresent('tr a[href*="/invoices/"]');
		// Download all invoices
		$invoice_links = $this->exts->getElements('tr a[href*="/invoices/"]');
		$this->exts->log('Invoices found in order:' . count($invoice_links));
		foreach ($invoice_links as $invoice_link) {
			$invoiceName = trim($invoice_link->getAttribute('innerText'));
			$invoiceUrl = $invoice_link->getAttribute('href');
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceUrl: '.$invoiceUrl);
			if(!$this->exts->invoice_exists($invoiceName)){
				$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceName.'.pdf');
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::No download '.$invoiceName);
				}
			} else {
				$this->exts->log('Invoice Existed: ' . $invoiceName);
			}
			$this->isNoInvoice = false;
		}
	}
	$this->exts->closeTab($newTab);
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 50 &&
		$this->exts->getElement('a.elo-paginate-section__link--next[rel="next"][aria-disabled="false"]') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('a.elo-paginate-section__link--next[rel="next"][aria-disabled="false"]');
		sleep(5);
		$this->download_payer_order_invoices_for_myablefy($paging_count);
	}
}