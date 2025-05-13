<?php
// Server-Portal-ID: 1230271 - Last modified: 01.02.2025 12:32:39 UTC - User: 1

public $baseUrl = 'https://espaceabonnes.vinci-autoroutes.com/';
public $loginUrl = 'https://espaceabonnes.vinci-autoroutes.com/';
public $invoicePageUrl = 'https://espaceabonnes.vinci-autoroutes.com/invoices-consos/factures';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button#login:not([disabled])';

public $check_login_failed_selector = 'div.login-error';
public $check_login_success_selector = '[iconfontclass="ulib-font-logout"]';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();
        sleep(2);

        if ($this->exts->exists('button[id="didomi-notice-agree-button"]')) {
            $this->exts->click_element('button[id="didomi-notice-agree-button"]');
        }

        $this->exts->openUrl($this->invoicePageUrl);
        $this->processInvoices();
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passe') !== false) {
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

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            if($this->exts->exists('button.frc-button')){
                $this->exts->click_element('button.frc-button');
            }

            for ($i=0; $i < 20 && $this->exts->exists('.frc-captcha .frc-progress'); $i++) { 
                sleep(7);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {

                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $this->exts->waitTillPresent('button#send-code-button', 20);
    if ($this->exts->exists('button#send-code-button')) {
        $this->exts->click_element('button#send-code-button');
    }
    $two_factor_selector = 'input[id="code"]';
    $two_factor_message_selector = "div.ulib-card-verifycode > p";
    $two_factor_submit_selector = 'button#code-button';
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(2);
            $this->exts->click_element($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
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



    if ($isLoggedIn) {

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    }

    return $isLoggedIn;
}

private function processInvoices($paging_count = 1)
{
    $this->exts->waitTillPresent('ulib-fact-item.ulib-fact-item', 30);
    $this->exts->capture("4-invoices-page");
    // Keep clicking more but maximum upto 10 times
    $maxAttempts = 10;
    $attempt = 0;

    while ($attempt < $maxAttempts && $this->exts->exists("//a[span[contains(text(), 'Voir plus')]]")) {
        $loadMore = $this->exts->queryXpath("//a[span[contains(text(), 'Voir plus')]]");
        $this->exts->execute_javascript("arguments[0].click();", [$loadMore]);
        $attempt++;
        sleep(5);
    }
    $invoices = [];

    $rows = $this->exts->querySelectorAll('ulib-fact-item.ulib-fact-item');
    foreach ($rows as $row) {
        if ($this->exts->querySelector('ulib-fact-item-download.ulib-fact-item-download a', $row) != null) {
            $invoiceUrl = '';
            $invoiceName = '';
            $invoiceAmount =  $this->exts->extract('ulib-price.ulib-price', $row);
            $invoiceDate =  '';

            $downloadBtn = $this->exts->querySelector('ulib-fact-item-download.ulib-fact-item-download a', $row);

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl,
                'downloadBtn' => $downloadBtn
            ));
            $this->isNoInvoice = false;
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
        $this->exts->wait_and_check_download('pdf');
        $downloaded_file = $this->exts->find_saved_file('pdf');
        $invoiceFileName = basename($downloaded_file);

        $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);


        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
    }
}