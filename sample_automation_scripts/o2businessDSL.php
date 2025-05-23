<?php // update download code  // updated login code // updated loginfailure code
// Server-Portal-ID: 1854565 - Last modified: 29.01.2025 14:38:11 UTC - User: 1

public $baseUrl = 'https://easyaccess.o2business.de/';
public $loginUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';
public $invoicePageUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';

public $username_selector = 'input[type="text"]';
public $password_selector = 'input[type="password"]';
public $submit_login_selector = '.eCareLoginBox .buttonBoxEcare button';

public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
public $check_login_success_selector = '#userNavItemId li a#userInfoBtnId';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->openUrl($this->baseUrl);
    sleep(10);
   

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->loginUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->checkFillLoginUndetected();
        sleep(10);
    }
   
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    $this->exts->capture("check-login-status");

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        $this->exts->type_key_by_xdotool("F5");
        sleep(5);
        $this->exts->type_text_by_xdotool($this->invoicePageUrl);
       
        sleep(5);

        try {
            $this->exts->moveToElementAndClick('[data-region-name="contentComponent"] select option[value="Alle"]');
            sleep(2);
        } catch (\Exception $exception) {
            $this->exts->log('ERROR in slecting all customer ' . $exception->getMessage());
        }

        for ($i = 0; $i < 20; $i++) {
            $this->exts->type_key_by_xdotool("Tab");
            sleep(1);
        }
        $this->exts->type_key_by_xdotool("Return");
        sleep(10);
        $this->exts->type_key_by_xdotool("Tab");
        $this->exts->type_key_by_xdotool("Return");

         for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool("Tab");
            sleep(1);
        }
        $this->exts->type_key_by_xdotool("Return");

        $this->processBilling(1);

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.")');

        $this->exts->log('isErrorMessage:'. $isErrorMessage);

        $this->exts->capture("login-failed");
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else if($isErrorMessage) {
            $this->exts->log("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.");
            $this->exts->loginFailure(1);
        } 
        else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLoginUndetected()
{
    $this->exts->capture('login-page');
    $this->exts->openUrl($this->loginUrl);
    sleep(15);
    for ($i = 0; $i < 8; $i++) {
        $this->exts->type_key_by_xdotool("Tab");
        sleep(2);
    }
    $this->exts->capture('login-page-1');

    $this->exts->log("Enter Username");
    $this->exts->type_text_by_xdotool($this->username);
    $this->exts->capture("enter-username");
    $this->exts->type_key_by_xdotool("Return");
    sleep(5);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(1);
    $this->exts->log("Enter Password");
    $this->exts->type_text_by_xdotool($this->password);
    $this->exts->capture("enter-password");
    sleep(5);
    $this->exts->log("Submit Login Form");
    $this->exts->moveToElementAndClick($this->submit_login_selector);
    $this->exts->capture("submit-login");
}


private function processBilling($page = 1)
{
    $this->exts->waitTillPresent('table > tbody > tr', 30);

    if(!$this->exts->exists('table > tbody > tr')){
      $this->exts->moveToElementAndClick('div[class="slds-grid slds-wrap cECareOnlineInvoice"] > div:nth-child(3) > button:nth-of-type(1)');
      $this->exts->waitTillPresent('table > tbody > tr', 30);
    }

    $this->exts->capture("4-billing-page");
    $rows = $this->exts->getElements('table > tbody > tr');
    $invoiceLog = "No Invoice downloaded";
    $key = 1;
    $totalInvoices = count($rows);
    $this->exts->log('totalInvoices: '.$totalInvoices);
    foreach ($rows as $index => $row) {

        $this->exts->log('key: '. $index);
        // create custom name becuase cannot  logged in on remote chrome
        $invoiceNumber =  time(). $index;
        $invoiceFileName = $invoiceNumber . '.pdf';
        $invoiceLink = "table > tbody > tr:nth-of-type(".$key.") > td:nth-of-type(8) a";
        $invoiceDate = '';
        $invoiceAmount = '';
        $this->exts->log('invoiceName: ' . '');
        $this->exts->log('invoiceDate: ' . '');
        $this->exts->log('invoiceAmount: ' . '');
        $this->exts->log('invoiceUrl: ' . $invoiceLink);

        $this->exts->log($invoiceLink);

        if($this->exts->exists($invoiceLink)){
            $downloaded_file = $this->exts->click_and_download($invoiceLink, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceNumber, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
                $this->isNoInvoice = false;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $key++;
    }
   

}
