<?php
/*Define constants used in script*/
public $baseUrl = 'https://www.michaelmanagement.com/';
public $loginUrl = 'https://www.michaelmanagement.com/login.php';
public $invoicePageUrl = 'https://www.michaelmanagement.com/edit-account.php?tabname=transactions';

public $username_selector = 'input[name="Email"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = 'input[id="rememberme"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div#loginErrorsPanel p';
public $check_login_success_selector = 'div#sidenav a[href="/logoff.php?c=1"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->loadCookiesFromFile();

    sleep(10);
    // Accecpt cookies
    if($this->exts->exists('button[id="cookieconsent_btn"]')){
        $this->exts->moveToElementAndClick('button[id="cookieconsent_btn"]');
        sleep(7);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        // Accecpt cookies
        if($this->exts->exists('button[id="cookieconsent_btn"]')){
            $this->exts->moveToElementAndClick('button[id="cookieconsent_btn"]');
            sleep(7);
        }
        $this->fillForm(0);

    }

    $this->exts->openUrl('https://www.michaelmanagement.com/edit-account.php');
    sleep(10);
    if($this->exts->exists('div[class="modal-header alert-danger"]')){
        $this->exts->moveToElementAndClick('div[class="modal-header alert-danger"] button');
        $this->exts->openUrl('https://www.michaelmanagement.com/edit-account.php');
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($this->invoicePageUrl);

        $this->downloadInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Login failed. Please check your user name and/or password.') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**
* Method to Check where user is logged in or not
* return boolean true/false
*/
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

private function downloadInvoices($count = 0) {
	$this->exts->log(__FUNCTION__);

    $this->exts->waitTillPresent('div#transactionscontent_wrapper');
	$this->exts->capture("4-invoices-classic");


    $invoices = [];
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('td a[href*="/create-invoice.php"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");

			$invoiceName = $this->exts->extract('td[data-label="Transaction ID"]', $row);
			$invoiceDate = $this->exts->extract('td[data-label="Date"]', $row);
			$invoiceAmount = $this->exts->extract('td[data-label="Order Total"]', $row);;
            
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
        
        $downloaded_file = $this->exts->click_and_print($invoice['invoiceUrl'], $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    while ($count < $restrictPages && $this->exts->exists('li[class="paginate_button page-item next"]')) {
        $this->exts->moveToElementAndClick('li[class="paginate_button page-item next"]');
        sleep(7);
        $count++; 
        $this->downloadInvoices($count);
    }
    
}