<?php
// Server-Portal-ID: 56514 - Last modified: 06.03.2024 13:29:38 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://app.fitogram.pro';
public $loginUrl = 'https://app.fitogram.pro/signin';
public $invoicePageUrl = '';

public $username_selector = 'form.managerLogin input[data-test-id="email"], form input[name="email"]';
public $password_selector = 'form.managerLogin input[data-test-id="password"], form input[name="password"]';
public $remember_me_selector = '';
public $submit_login_btn = 'form.managerLogin button[type="submit"], form button[type="submit"]';

public $checkLoginFailedSelector = '#fm-signin-error, div.tss-18opq55--errorContainer';
public $checkLoggedinSelector = 'a#basic-nav-dropdown .img-circle, button[data-testid="main.userMenu"]';

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
            
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in

    // Wait for selector that make sure user logged in
    sleep(10);
    if($this->exts->getElement($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);

        if($this->exts->exists("div#onetrust-banner-sdk")){
            $this->exts->capture('cookies-screen');
            $this->exts->moveToElementAndClick("button#onetrust-accept-btn-handler");
            $this->exts->capture('cookies-accept-screen');
        }

        sleep(10);
        $this->exts->waitTillPresent('form.managerLogin', 15);          
        $this->waitForLoginPage();

    }
}

private function waitForLoginPage($count=1) {
    sleep(5);
    if($this->exts->exists($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(4);

        if($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(5);
        if($this->exts->exists($this->password_selector) != null) {
            $this->waitForLoginPage();
        }
        $this->waitForLogin();
    } else {
        if($count < 5){
            $count = $count +1;
            $this->waitForLoginPage($count);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

private function waitForLogin($count=1) {
    sleep(5);
    if ($this->exts->urlContains('provider/select') && $this->exts->exists('div[data-testid="FitoListItem"]')) {
        $this->exts->moveToElementAndClick('div[data-testid="FitoListItem"]');
        sleep(20);
    }
    if($this->exts->getElement($this->checkLoggedinSelector) != null) {
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");
        if($this->exts->exists('#onetrust-accept-btn-handler')){
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
            sleep(1);
        }
        // Open invoices page
        // first: click Setting button on left menu
        $this->exts->moveToElementAndClick('.navbar-nav a[href*="/settings"]');
        sleep(5);
        // then: click Account button on sub-left menu
        $this->exts->moveToElementAndClick('a.settingsListItem[href$="/settings/account"]');
        sleep(7);
        //Click sub menu show Invoice history button
        if($this->exts->getElement('//*[contains(@id,"Deine RechnungenData")]/../h3', null, 'xpath') != null){
            $this->exts->getElement('//*[contains(@id,"Deine RechnungenData")]/../h3', null, 'xpath')->click();
            sleep(5);
        }
        if($this->exts->getElementByText('.invoice-section [data-test-id="button"]', 'Invoice history|Rechnungsverlauf|Historique des factures') != false){
        
            // Find Invoice history button and click.
                $this->exts->getElementByText('.invoice-section [data-test-id="button"]', 'Invoice history|Rechnungsverlauf|Historique des factures')->click();
        }
        // wait for invoices
        $this->processInvoices();
        
    } else {
        if($count < 5){
            $count = $count + 1;
            $this->waitForLogin($count);
        } else {
            $failedlogurl = $this->exts->webdriver->getCurrentURL();
            $this->exts->log($failedlogurl ." -no_permission");
            if($failedlogurl !== $this->loginUrl){
                $this->exts->no_permission();
            }else{
                $this->exts->log('Timeout waitForLogin');
                $this->exts->capture("LoginFailed");
                if(stripos($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText'),'Invalid username and/or password') !== false) {
                $this->exts->loginFailure(1);
                } else {
                $this->exts->loginFailure();
                } 
            }
        }
    }
}

private function processInvoices($count=1) {
    sleep(5);
    if($this->exts->getElement('table > tbody > tr') != null) {
        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $invoices = [];

        $rows = count($this->exts->getElements('.invoices-container table > tbody > tr'));
        for ($i=0; $i < $rows; $i++) {
            $row = $this->exts->getElements('.invoices-container table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if(count($tags) >= 5 && $this->exts->getElement('a .fa-arrow-circle-o-down', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a .fa-arrow-circle-o-down', $tags[4]);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName.'.pdf';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: '.$invoiceName);
                $this->exts->log('invoiceDate: '.$invoiceDate);
                $this->exts->log('invoiceAmount: '.$invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
                $this->exts->log('Date parsed: '.$parsed_date);

                // Download invoice if it not exisited
                if($this->exts->invoice_exists($invoiceName)){
                    $this->exts->log('Invoice existed '.$invoiceFileName);
                } else {
                    try{
                        $this->exts->log('Click download button');
                        $this->exts->click_element($download_button);
                        // $download_button->click();
                    } catch(\Exception $exception){
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                    }
                }
            }
        }

    } else {
        if($count < 5){
            $count = $count + 1;
            $this->processInvoices($count);
        } else {
            $this->exts->log('Timeout processInvoices');
            $this->exts->capture('4-no-invoices');
            $this->exts->no_invoice();
        }
    }
}