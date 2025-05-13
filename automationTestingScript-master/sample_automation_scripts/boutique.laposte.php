<?php //migrated and updated login code
// Server-Portal-ID: 106894 - Last modified: 27.09.2024 14:08:47 UTC - User: 1

public $baseUrl = 'https://www.laposte.fr/tableau-de-bord';
public $loginUrl = 'https://boutique.laposte.fr/authentification';
public $invoicePageUrl = 'https://boutique.laposte.fr/mescommandes';

public $username_selector = 'form#formConnect input#j_username, #login-form #username';
public $password_selector = 'form#formConnect input#formPass, #login-form #password';
public $remember_me_selector = '';
public $submit_login_selector = 'form#formConnect input#authentificationEnvoyer, #login-form #submit';

public $check_login_failed_selector = '#login-form .message.error';
public $check_login_success_selector = 'a[href="/deconnexionPopin"], a[data-switch-href="/deconnexion"], a[href*="/logout"], div.header-account-connected, button#auto-btn-user-link, a[href*="/commande-detail/telecharger-facture/"]';

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
		$this->exts->openUrl($this->baseUrl);
		sleep(15);

		for ($i =0; $i < 3; $i++) {
			$msg = strtolower($this->exts->extract('div#main-frame-error p[jsselect="summary"]', null, 'innerText'));
			$msg1 = trim(strtolower($this->exts->extract('body', null, 'innerText')));
			if (strpos($msg, 'took too long to respond') !== false || ($msg1 == '404 - not found')) {
				$this->exts->refresh();
				sleep(15);
				$this->exts->capture('after-refresh-cant-be-reach-' . $i);
			} else {
				break;
			}
		}

		$this->checkFillLogin();
		sleep(20);
	}
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		sleep(10);

		if($this->exts->exists('input#otpCode')){
			$this->checkFillTwoFactor();
			sleep(10);
		}

		
		$this->exts->moveToElementAndClick('#footer_tc_privacy_button');
		// $dateTo = date('d/m/Y');
		// $dateFrom = date('d/m/Y',strtotime('-1 years'));
		// $this->exts->moveToElementAndType('input#datepickerdebut', $dateFrom);
		// sleep(1);
		// $this->exts->moveToElementAndType('input#datepickerfin', $dateTo);
		// sleep(1);
		// $this->exts->moveToElementAndClick('.btn-send-minor input[type="submit"]');
		//$this->exts->executeSafeScript('document.getElementById("formSearchCommandesByDates").submit();');
		$this->processInvoices();

		if ($this->exts->exists('input#otpCode')) {
			$this->exts->no_permission();
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

		$this->exts->waitTillPresent('input#li-antibot-token');
		if ($this->exts->exists('input#li-antibot-token')) {
			$this->exts->execute_javascript("document.querySelector('input#li-antibot-token').value = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJyZXF1ZXN0SWQiOiI4M2IxOTMxM2IwOWU0YzQzYWQxYzkwOWRlZGIzYTA3MCIsImludmlzaWJsZUNhcHRjaGFJZCI6IjMyYTI2NGNjLThkZmYtNDNmNS1iMTQ2LTVjYmMzN2MwN2ExMCIsImFudGlib3RJZCI6ImQyNzE1NjUwMjk0OTQ4MjJhNDQ1ZWQxYTY1NjhkZWUzIiwiYW50aWJvdE1ldGhvZCI6IkNBUFRDSEEiLCJleHAiOjE3Mzc1MzEwMjgsImlhdCI6MTczNzUzMDcyOCwiY2FwdGNoYUlkIjoiYzk5Y2ZjMWEzMDZlNDg4YjllZTM1ODBlY2U5YmQyYTkifQ.QpzZ52-c6fDmKGfhgAOHMxGMrXmRk_ofpIZ3xkPjqiQ';");
		}

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(15);


		if ($this->exts->urlContains('faviconLaposte.ico')){
			$this->exts->openUrl($this->invoicePageUrl);
		} 
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}



private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otpCode';
    $two_factor_message_selector = 'div[id="otp-error-message"]';
    $two_factor_submit_selector = 'input#submit:not([disabled])';

    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
            $this->exts->waitTillPresent($two_factor_selector);
            if ($this->exts->querySelector($two_factor_selector) == null) {
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



private function processInvoices($paging_count=1) {
	sleep(20);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('div#commands-container ul.command');
	foreach ($rows as $row) {
		if($this->exts->getElement('a[href*="commande-detail/"]', $row) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="commande-detail/"]', $row)->getAttribute("href");
			$invoiceName = end(explode('/', $invoiceUrl));
			$invoiceDate = trim(end(explode('du', trim($this->exts->getElement('.date-commande', $row)->getText()))));
			$invoiceAmount = end(explode(':', trim($this->exts->getElement('.command-price', $row)->getText())));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		$this->exts->openUrl($invoice['invoiceUrl']);
		sleep(15);

		$invoiceUrl = $this->exts->extract('a[href*="telecharger-facture"]', null, 'href');
		$this->exts->log('invoiceUrl: '.$invoiceUrl);

		$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoiceUrl, $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}

	// the page change UI, handling for new UI
	$this->exts->capture("4-new-invoices-page");
	$invoices = [];

	$rows_len = count($this->exts->getElements('li[id*="-product-"]'));
	for ($i = 0; $i < $rows_len; $i++) {
		$row = $this->exts->getElements('li[id*="-product-"]')[$i];
		try{
			$this->exts->log('Click download button');
			$row->click();
		} catch(\Exception $exception){
			$this->exts->log('Click download button by javascript');
			$this->exts->executeSafeScript("arguments[0].click()", [$row]);
		}
		sleep(8);

		$this->exts->moveToElementAndClick('button#footer_tc_privacy_button_2');
		sleep(3);

		if ($this->exts->exists('li.command-numero a') && $this->exts->exists('a[href*="/commande-detail/telecharger-facture/"]')) {
			$this->isNoInvoice = false;

			$invoiceName = end(explode('/commande-detail/', $this->exts->getUrl()));
			$invoiceDate = trim(end(explode(' du ', $this->exts->extract('h1.nec-header-title', null, 'innerText'))));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.command-price.command-quantity', null, 'innerText'))) . ' EUR';
			$invoiceUrl = $this->exts->extract('a[href*="/commande-detail/telecharger-facture/"]', null, 'href');

			$this->exts->log('--------------------------');
			$this->exts->log('invoiceUrl: '.$invoiceUrl);
			$this->exts->log('invoiceName: '.$invoiceName);
			$this->exts->log('invoiceDate: '.$invoiceDate);
			$this->exts->log('invoiceAmount: '.$invoiceAmount);

			$invoiceFileName = $invoiceName.'.pdf';
			$invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
			$this->exts->log('Date parsed: '.$invoiceDate);

			$downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}

		$this->exts->executeSafeScript('history.back();');
		sleep(14);
	}

	// nextpage
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 &&
		$paging_count < 50 &&
		$this->exts->getElement('a.pagi-previous') != null
	){
		$paging_count++;
		$this->exts->moveToElementAndClick('a.pagi-previous');
		sleep(5);
		$this->processInvoices($paging_count);
	}
}