<?php
// Server-Portal-ID: 400 - Last modified: 28.03.2024 06:57:29 UTC - User: 1

/*Define constants used in script*/
	public $baseUrl = 'https://www.united-domains.de';
	public $loginUrl = 'https://www.united-domains.de/login';
	public $invoicePageUrl = 'https://www.united-domains.de/portfolio/a/user/invoices';

	public $username_selector = 'form#order-login-form input[name="email"]';
	public $password_selector = 'form#order-login-form input[name="pwd"]';
	public $remember_me_selector = '';
	public $submit_login_selector = 'form#order-login-form button#submit[type="submit"]';

	public $check_login_failed_selector = '#login-page-wrapper .flash-error-msg li';
	public $check_login_success_selector = 'li.logout-element a[href*="/logout"]';

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
		sleep(1);
		
		if($this->exts->exists('#corona-vat-rate-banner a.close-reveal-modal')) {
			$this->exts->moveToElementAndClick('#corona-vat-rate-banner a.close-reveal-modal');
			sleep(1);
		}
		// Load cookies
		$this->exts->loadCookiesFromFile();
		sleep(1);
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		
		$this->exts->moveToElementAndClick('div.cookie-layer-dialog button.confirm-all');
		sleep(15);
		
		if($this->exts->exists('#corona-vat-rate-banner a.close-reveal-modal')) {
			$this->exts->moveToElementAndClick('#corona-vat-rate-banner a.close-reveal-modal');
			sleep(1);
		}
		
		$this->exts->capture('1-init-page');
		
		// If user hase not logged in from cookie, clear cookie, open the login url and do login
		if($this->exts->getElement($this->check_login_success_selector) == null) {
			$this->exts->log('NOT logged via cookie');
			$this->exts->openUrl($this->loginUrl);
			sleep(15);
			
			$this->exts->moveToElementAndClick('div.cookie-layer-dialog button.confirm-all');
			sleep(15);
			
			if($this->exts->exists('#corona-vat-rate-banner a.close-reveal-modal')) {
				$this->exts->moveToElementAndClick('#corona-vat-rate-banner a.close-reveal-modal');
				sleep(1);
			}
			
			$this->checkFillLogin();
			sleep(20);
		}
		if($this->exts->exists('div form[name*="totpForm"], form#PinForm input[name="pin"]')) {
			$this->exts->log(">>>>>>>>>>>>>>>checkFillTwoFactor!");
			$this->checkFillTwoFactor();
		}
		
		if($this->exts->getElement($this->check_login_success_selector) != null) {
			sleep(3);
			$this->exts->log(__FUNCTION__.'::User logged in');
			$this->exts->capture("3-login-success");
			
			// Open invoices url and download invoice
			$this->exts->openUrl($this->invoicePageUrl);
			if($this->exts->exists('#corona-vat-rate-banner a.close-reveal-modal')) {
				$this->exts->moveToElementAndClick('#corona-vat-rate-banner a.close-reveal-modal');
				sleep(1);
			}
			$this->processInvoices();
			
			// Final, check no invoice
			if($this->isNoInvoice){
				$this->exts->no_invoice();
			}
			$this->exts->success();
		} else {
			$this->exts->log(__FUNCTION__.'::Use login failed');
			if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
				$this->exts->loginFailure(1);
			} else {
				$this->exts->loginFailure();
			}
		}
	}
	private function checkFillTwoFactor() {
		$two_factor_selector = 'form input[name*="totp"], form#PinForm input[name="pin"]';
		$two_factor_message_selector = '#login-page-wrapper .row:not([id]) p, div.callout > p, [name="totpForm"] > p';
		$two_factor_submit_selector = 'form button#submit, button[name="login-with-pin"], [name="totpForm"] button[name="submit"]';
		
		if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
			$this->exts->log("Two factor page found.");
			$this->exts->capture("2.1-two-factor");
			
			if($this->exts->getElement($two_factor_message_selector) != null){
				$this->exts->two_factor_notif_msg_en = "";
				for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
					$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
				}
				$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
				
			}
			if($this->exts->two_factor_attempts == 2) {
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
				$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
			}
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
			$this->exts->notification_uid = '';
			
			if(stripos($this->exts->two_factor_notif_msg_en, 'Authentifizierungs-App') !== false || stripos($this->exts->two_factor_notif_msg_en, 'Authenticator') !== false){
				$this->exts->reuseMfaSecret();
			}
			
			$two_factor_code = trim($this->exts->fetchTwoFactorCode());
			if(!empty($two_factor_code) && trim($two_factor_code) != '') {
				$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
				$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
				$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
				sleep(2);

				$this->exts->moveToElementAndClick($two_factor_submit_selector);
				sleep(9);
				
				if($this->exts->getElement($two_factor_selector) == null){
					$this->exts->log("Two factor solved");
				} else if ($this->exts->two_factor_attempts < 3) {
					$this->exts->two_factor_attempts++;
					$this->checkFillTwoFactor();
				} else {
					$this->exts->log("Two factor can not solved");
				}
				
			} else {
				$this->exts->log("Not received two factor code");
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
	private function getInnerTextByJS($element){
		return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
	}

	private function processInvoices() {
		sleep(25);
		if((int)$this->restrictPages == 0) {
			if($this->exts->getElement('invoice-overview select option[value=""]:last-child') != null){
				$this->exts->moveToElementAndClick('invoice-overview select');
				sleep(1);
				$this->exts->moveToElementAndClick('invoice-overview select option[value=""]:last-child');
				sleep(3);
			}
		} else {
			if($this->exts->getElement('invoice-overview select option:first-child') != null){
				$this->exts->moveToElementAndClick('invoice-overview select');
				sleep(1);
				$this->exts->moveToElementAndClick('invoice-overview select option:first-child');
				sleep(3);
			}
		}
		sleep(15);
		$this->exts->capture("4-invoices-page");
		$invoices = [];
		
		$paths = explode('/', $this->exts->getUrl());
		$currentDomainUrl = $paths[0].'//'.$paths[2];
		if($this->exts->exists('table > tbody > tr a[href*="/rechnung"]')) {
			$rows = $this->exts->getElements('table > tbody > tr');
			foreach ($rows as $row) {
				$tags = $this->exts->getElements('td', $row);
				if(count($tags) >= 3 && $this->exts->getElement('a[href*="/rechnung"]', $tags[0]) != null) {
					$invoiceUrl = $this->exts->getElement('a[href*="/rechnung"]', $tags[0])->getAttribute("href");
					$invoiceName = trim($tags[0]->getText());
					$invoiceDate = trim($tags[1]->getText());
					$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';
					
					array_push($invoices, array(
						'invoiceName'=>$invoiceName,
						'invoiceDate'=>$invoiceDate,
						'invoiceAmount'=>$invoiceAmount,
						'invoiceUrl'=>$invoiceUrl
					));
					$this->isNoInvoice = false;
				}
			}
		} else if($this->exts->exists('.portfolioTable .portfolioTable-Body a[href*="/rechnung"]')){
			$rows = $this->exts->getElements('.portfolioTable .portfolioTable-Body .portfolioTable-Record-wrapper');
			if(count($rows) > 0) {
				foreach ($rows as $row) {
					$tags = $this->exts->getElements('.portfolioTable-Record div', $row);
					if(count($tags) >= 7 && $this->exts->getElement('a[href*="/rechnung"]', $tags[1]) != null) {
						$invoiceUrl = $this->exts->getElement('a[href*="/rechnung"]', $tags[1])->getAttribute("href");
						$invoiceName = trim($tags[1]->getText());
						$invoiceDate = trim($tags[2]->getText());
						$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' EUR';
						
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
		} else if($this->exts->exists('#invoice-overview .responsive-table .body .record a[href*="/rechnung"]')){
			$rows = $this->exts->getElements('#invoice-overview .responsive-table .body .record');
			if(count($rows) > 0) {
				foreach ($rows as $row) {
					$download_link = $this->exts->getElement('a[href*="/rechnung"]', $row);
					if($download_link != null) {
						$invoiceUrl = $download_link->getAttribute("href");
						if(strpos($invoiceUrl, $currentDomainUrl) === false){
							$invoiceUrl = $currentDomainUrl . $invoiceUrl;
						}
						$invoiceName = trim($download_link->getText());
						$invoiceDate = trim($this->getInnerTextByJS($this->exts->getElement('.record-field-date p', $row)));
						$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->getElements('.money', $row)[0]->getText())) . ' EUR';
						
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
			$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
			
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::Timeout when download '.$invoiceFileName);
			}
		}
	}