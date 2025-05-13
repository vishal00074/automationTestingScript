<?php // migrated and updated login code
// Server-Portal-ID: 946430 - Last modified: 11.11.2024 13:07:01 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://pro.free.fr/account/#/';
public $loginUrl = 'https://pro.free.fr/espace-client/connexion/#/';
public $invoicePageUrl = 'https://pro.free.fr/account/#/billing';

public $username_selector = 'div.login_form input[placeholder*="e-mail"]';
public $password_selector = 'div.login_form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'div.login_form button[type="submit"]';

public $check_login_failed_selector = 'article.notification.is-danger';
public $check_login_success_selector = '.account-link button.is-logout';

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

    // accecpt cookies
    if($this->exts->exists('div.cookiesMgmt button.is-primary')){

        $this->exts->log('Click accept cookie...');
        
        $this->exts->moveToElementAndClick('div.cookiesMgmt button.is-primary');
        sleep(5);
    }

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(10);
		$this->checkFillLogin();
        $this->exts->waitTillPresent('div.cookiesMgmt button.is-primary', 10);
        // accecpt cookies
        if ($this->exts->exists('div.cookiesMgmt button.is-primary')) {

            $this->exts->log('Click accept cookie...');

            $this->exts->moveToElementAndClick('div.cookiesMgmt button.is-primary');
            sleep(5);
        }

		$this->exts->type_key_by_xdotool('Tab');
		sleep(1);
		$this->exts->type_key_by_xdotool('Return');
		sleep(5);
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
	}

	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
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
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'ous avez saisi un identifiant ou un mot de passe invalide') !== false) {
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

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 6 && $this->exts->getElement('a[href*="/invoice"]:not([href*="csv"]):not([href*="mobile"])', $tags[5]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="/invoice"]:not([href*="csv"]):not([href*="mobile"])', $tags[5])->getAttribute("href");
			$invoiceName = trim($tags[0]->getAttribute('innerText'));
			$invoiceDate = trim($tags[1]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/n/Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}