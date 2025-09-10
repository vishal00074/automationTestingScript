<?php // updated login code added 2fa code
// Server-Portal-ID: 541366 - Last modified: 24.01.2025 14:00:37 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://my.surfshark.com/auth/login?';
public $loginUrl = 'https://my.surfshark.com/auth/login?';
public $invoicePageUrl = 'https://my.surfshark.com/account/subscription/payments';

public $username_selector = 'input[name="emailField"], input[type="email"]';
public $password_selector = 'input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[id="loginSubmit"]';

public $check_login_failed_selector = 'div[data-test="login-error"]';
public $check_login_success_selector = 'button[data-test="user-menu-button"]';

public $isNoInvoice = true;

/**

 * Entry Method thats called for a portal.

 * @param Integer $count Number of times portal is retried.   

 */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    $this->check_solve_blocked_page();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->check_solve_blocked_page();
        if ($this->exts->exists('.nav-wrapper button')) {
            $this->exts->click_by_xdotool('.nav-wrapper button');
        }
        sleep(10);
        $this->fillForm(0);
        sleep(10);
        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();
        sleep(2);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
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
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->solve_login_cloudflare();

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            $this->exts->waitTillPresent($this->submit_login_selector, 20);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


private function checkFillTwoFactor() {
    $two_factor_selector = 'input[autocomplete="one-time-code"], input#code';
    $two_factor_message_selector = 'header p, [class*="loginChallengePage"] > p';
    $two_factor_submit_selector = 'button[id="loginSubmit"]';

    if($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if($this->exts->querySelector($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $code_array = str_split((string)$two_factor_code);
            foreach($code_array as $number){
                $this->exts->type_text_by_xdotool($number);
                sleep(1);
            }
            // $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if($this->exts->querySelector($two_factor_selector) == null){
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


private function solve_login_cloudflare()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if ($this->exts->check_exist_by_chromedevtool('div#login-form > div')) {
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('div#login-form > div', 30, 28);
        sleep(20);
        if ($this->exts->check_exist_by_chromedevtool('div#login-form > div')) {
            $this->exts->click_by_xdotool('div#login-form > div', 30, 28);
            sleep(20);
        }
        if ($this->exts->check_exist_by_chromedevtool('div#login-form > div')) {
            $this->exts->click_by_xdotool('div#login-form > div', 30, 28);
            sleep(20);
        }
    }
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


/**

 * Method to Check where user is logged in or not

 * return boolean true/false

 */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }


    return $isLoggedIn;
}


private function processInvoices($paging_count = 1)
{
    $this->exts->waitTillPresent('table tbody tr', 30);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->querySelectorAll('table tbody tr td:nth-child(5) a');
    foreach ($rows as $row) {
        array_push($invoices, array(
            'invoiceUrl' => $row->getAttribute('href')
        ));
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->openUrl($invoice['invoiceUrl']);
        $this->exts->waitTillPresent("div[class='FDHlS']", 20);
        if ($this->exts->querySelector("div[class='FDHlS']") != null) {
            $invoiceName = '';
            $invoiceDate = '';
            $invoiceAmount = '';
            $downloadBtn = $this->exts->querySelector("div[class='FDHlS']");

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $this->isNoInvoice = false;
        }
        $invoiceFileName = $invoiceName . '.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoiceDate);

        // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
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