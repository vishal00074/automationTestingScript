<?php // migrated and update download code
// Server-Portal-ID: 1780972 - Last modified: 10.02.2025 06:57:33 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://accounts.hetzner.com/';
public $loginUrl = 'https://accounts.hetzner.com/';
public $invoicePageUrl = 'https://accounts.hetzner.com/invoice';

public $username_selector = 'input#_username';
public $password_selector = 'input#_password';
public $remember_me_selector = '';
public $submit_login_selector = 'form#login-form input#submit-login';

public $check_login_failed_selector = 'p.invalid-message';
public $check_login_success_selector = 'a[href*="/logout"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->check_solve_blocked_page();

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->check_solve_blocked_page();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->check_solve_blocked_page();
        if ($this->exts->exists('#main-frame-error button#reload-button')) {
            //$this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('div.loading-circle');
            for ($i = 0; $i < 5 && $this->exts->exists('div.loading-circle'); $i++) {
                sleep(5);
            }
            $this->check_solve_blocked_page();
        }
        if (!$this->exts->exists($this->password_selector)) {
            //$this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            for ($i = 0; $i < 5 && $this->exts->exists('div.loading-circle'); $i++) {
                sleep(5);
            }
            if ($this->exts->exists('div.loading-circle')) {
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }

            $this->check_solve_blocked_page();
        }
        $this->checkFillLogin();
        $this->exts->waitTillPresent('form#otp_form input#input-verify-code');
        if ($this->exts->exists('form#otp_form input#input-verify-code')) {
            $this->checkFillTwoFactor();
        }
        if ($this->exts->exists($this->password_selector)) {
            //$this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('div.loading-circle');
            for ($i = 0; $i < 5 && $this->exts->exists('div.loading-circle'); $i++) {
                sleep(5);
            }
            if ($this->exts->exists('div.loading-circle')) {
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }

            $this->check_solve_blocked_page();
            $this->checkFillLogin();
            $this->exts->waitTillPresent('form#otp_form input#input-verify-code');
            if ($this->exts->exists('form#otp_form input#input-verify-code')) {
                $this->checkFillTwoFactor();
            }
        }
    }


    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);
        $this->check_solve_blocked_page();
        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log('Url login failed: ' . $this->exts->getUrl());
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'fehlerhafte zugangsdaten') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'invalid credentials') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form#otp_form input#input-verify-code';
    $two_factor_message_selector = 'form#otp_form > p';
    $two_factor_submit_selector = 'form#otp_form input#btn-submit[type="submit"]';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = 'Hetzner 2-Faktor-Authentifizierung - Code' . $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
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

private function clearChrome(){
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i=0; $i < 2; $i++) { 
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i=0; $i < 5; $i++) { 
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}


private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}

private function processInvoices($paging_count = 1)
{
    $this->exts->waitTillPresent('.invoice-list .row', 25);
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $paths = explode('/', $this->exts->getUrl());
    $currentDomainUrl = $paths[0] . '//' . $paths[2];
    $rows = $this->exts->querySelectorAll('.invoice-list .row');
    foreach ($rows as $row) {
            $invoiceUrl = $this->exts->querySelector('a.btn-download[href*="/pdf"]', $row);
            if ($invoiceUrl != null) {
                $invoiceUrl = $invoiceUrl->getAttribute("href");
            }
            if (stripos($invoiceUrl, $currentDomainUrl) === false) {
                $invoiceUrl = $currentDomainUrl . $invoiceUrl;
            }
               
            $invoiceName = $this->exts->extract('span.invoice-number', $row);

            $invoiceDate = $this->exts->extract('span.invoice-date', $row);
            $invoiceAmount = $this->exts->extract('span.invoice-value', $row);

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
           
           
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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if (
        $restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->querySelector('.pagination-control a[rel="next"]') != null
    ) {
        $paging_count++;
        $this->exts->moveToElementAndClick('.pagination-control a[rel="next"]');
        sleep(5);
        $this->processInvoices($paging_count);
    }
}