<?php // migrated update login and download code
// Server-Portal-ID: 27504 - Last modified: 16.07.2024 13:39:28 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://espaceclient.aprr.fr/aprr/Pages/connexion.aspx';
public $loginUrl = 'https://espaceclient.aprr.fr/aprr/Pages/connexion.aspx';
public $invoicePageUrl = 'https://www.fulli.com/customer-space/invoices';

public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = 'input[name="rememberMe"]';
public $submit_login_selector = 'input[type="submit"]';

public $check_login_failed_selector = '.erreur_blanc, .login-pf-header ~ div div.alert.alert-error, .Messages-group.-error.-closable.js--closable ';
public $check_login_success_selector = 'button.account-menu-link.UserLink, a[href*="/user/logout"]';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);		
	$this->exts->openUrl($this->baseUrl);
	sleep(10);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if(!$this->exts->exists($this->check_login_success_selector)) {
		$this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
		sleep(5);
		$this->exts->openUrl($this->loginUrl);
		sleep(5);
		$this->checkFillLogin();
		sleep(5);
	}

	$this->exts->waitTillPresent($this->check_login_success_selector);

	if($this->exts->exists($this->check_login_success_selector)) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open invoices url and download invoice
		$this->exts->openUrl($this->invoicePageUrl);
		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed url: '.$this->exts->getUrl());
		if ($this->exts->getElementByText('div.alert-error:not([style="display: none;"])', ['password', 'passwort'], null, false)) {
			$this->exts->loginFailure(1);
		}
		if ($this->exts->exists('iframe#fancybox-frame')) {
			$this->exts->switchToFrame('iframe#fancybox-frame');
		}

		$mesg = strtolower($this->exts->extract('div#divFancyMessagesContent', null, 'innerText'));
		$this->exts->log($mesg);
		if (strpos($mesg, 'wachtwoord zijn onjuist') !== false
				|| strpos($mesg, 'passe sont incorrects') !== false || strpos($mesg, 'es ist ein fehler aufgetreten, bitte versuchen sie es noch einmal') !== false || strpos($mesg, 'une erreur est survenue, merci de') !== false || strpos($mesg, 'uw gebruikersnaam en/of wachtwoord zijn onjuist') !== false) {
			$this->exts->loginFailure(1);
		} else if ($this->exts->exists($this->check_login_failed_selector)) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}



private function checkFillLogin() {
	$this->exts->log(__FUNCTION__ .'Begin Fill Login');
	$this->exts->capture(__FUNCTION__);

    $this->exts->waitTillPresent($this->password_selector);

	if($this->exts->exists($this->password_selector)) {
		sleep(2);
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
	sleep(10);

	$this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
	sleep(5);

	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('ul#history-lines li');

	$this->exts->log("Invoice count: ". count($rows));

	foreach ($rows as $row) {
		$invoiceLink = $this->exts->getElement('ul#history-lines li a[href*="invoice"]', $row);

		$invoiceUrl = '';
		if($invoiceLink){
			$invoiceUrl = 'https://www.fulli.com' . $invoiceLink->getAttribute('href');
		}

        preg_match('/(\d+)$/', $invoiceUrl, $matches);
        if (!empty($matches[1])) {
            $invoiceName =  $matches[1]; 
        } else {
            $invoiceName = time();
        }
        $invoiceDate = $this->exts->extract('div.InvoiceInfo-list-item-period-info', $row);
        $invoiceAmount = $this->exts->extract('div.InvoiceInfo-list-item-price-info', $row); ' EUR';

        array_push($invoices, array(
            'invoiceName'=>$invoiceName,
            'invoiceDate'=>$invoiceDate,
            'invoiceAmount'=>$invoiceAmount,
            'invoiceUrl'=>$invoiceUrl
        ));
        $this->isNoInvoice = false;
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
		//$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M Y','Y-m-d', 'fr');
		$parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M Y','Y-m-d','de');
		if($parsed_date == ''){
			$parsed_date = $this->exts->parse_date($invoice['invoiceDate'],'F Y','Y-m-01', 'fr');
		}
		$this->exts->log('Date parsed: '.$parsed_date);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}

	if ($this->isNoInvoice) {
		$this->exts->moveToElementAndClick('button#footer_tc_privacy_button');
		sleep(5);

		$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
		if ($restrictPages == 0) {
			for ($i = 0; $i < 50; $i++) {
				if ($this->exts->exists('button.js-more-button:not([style*="visibility: hidden"])')) {
					$this->exts->moveToElementAndClick('button.js-more-button:not([style*="visibility: hidden"])');
					sleep(10);
				} else {
					break;
				}			
			}
		}

		$this->exts->capture("4-invoices-1-page");

		$rows = $this->exts->getElements('ul#history-lines li');
		foreach ($rows as $row) {
			if($this->exts->getElement('a[href*="/invoice/download/"]', $row) != null) {
				$invoiceUrl = $this->exts->extract('a[href*="/invoice/download/"]', $row, 'href');
				$invoiceName = end(explode('/download/', $invoiceUrl));
				$invoiceDate = trim($this->exts->extract('div.InvoiceInfo-list-item-period', $row, 'innerText'));
				$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.InvoiceInfo-list-item-price', $row, 'innerText'))) . ' EUR';

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
			//$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M Y','Y-m-d', 'fr');
			$parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M Y','Y-m-d','de');
			if($parsed_date == ''){
				$parsed_date = $this->exts->parse_date($invoice['invoiceDate'],'F Y','Y-m-01', 'fr');
			}
			$this->exts->log('Date parsed: '.$parsed_date);
			
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		}
	}
}