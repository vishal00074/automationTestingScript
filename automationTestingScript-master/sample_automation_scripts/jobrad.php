<?php // updated login code and invoice code
// Server-Portal-ID: 804259 - Last modified: 06.02.2025 05:17:38 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://portal.jobrad.org/login.html';
public $loginUrl = 'https://portal.jobrad.org/login.html';
public $invoicePageUrl = 'https://fachhandel.jobrad.org/supplier/orders';

public $username_selector = 'input[id*=eMailAddressr0]';
public $password_selector = 'input[id*=passwordr1]';
public $remember_me_selector = '';
public $submit_login_selector = 'j-form button.j-button';

public $check_login_failed_selector = 'j-group[name*=failed]';
public $check_login_success_selector = 'a[href*=logout],div[class*=logout]';

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
    if ($this->exts->exists('button[id*=AllowAll]')) {
    $this->exts->click_element('button[id*=AllowAll');
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        if ($this->exts->exists('button[id*=AllowAll')) {
        $this->exts->click_element('button[id*=AllowAll');
        }
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();
        sleep(2);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        if($this->exts->exists('input[type="email"]') && $this->exts->exists('input[type="password"]')){
            $this->doAfterLogin();
        }
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
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                for($i=0; $i < 5; $i++ ){
                    if($this->exts->exists($this->username_selector)){
                        $this->checkFillRecaptcha();
                    }else{
                        break;
                    }
                }
                
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(5);
                }
                $this->exts->click_by_xdotool($this->submit_login_selector);


            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function doAfterLogin(){
    $this->exts->log(__FUNCTION__ . "::Begin fillForm ");
    $this->exts->waitTillPresent('input[type="email"]', 5);
    try {
        if ($this->exts->querySelector('input[type="email"]') != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType('input[type="email"]', $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType('input[type="password"]', $this->password);
            sleep(1);

            $this->exts->capture("1-invoice-login-page-filled");
            sleep(5);
            $this->exts->moveToElementAndClick('button[id="submit_login"]');
            sleep(10);

            if($this->exts->exists('div.lr_message-logsign div.alert-danger')){
                $this->exts->no_permission();
            }

        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}



private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'div.j-form__re-captcha-fallback iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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
    $this->exts->waitTillPresent('button[id*= "headlessui"]', 10);

    if ($this->exts->exists('button[id*= "headlessui"]')) {
        $this->exts->moveToElementAndClick('button[id*= "headlessui"]');
        sleep(4);
    }
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 10);
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
    $this->exts->waitTillPresent('div#order-list ul li.order', 30);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->querySelectorAll('div#order-list ul li.order');
    for ($i = 1;$i <= count($rows); $i++) {
        $this->exts->waitTillPresent("div#order-list ul li.order");
        $row = $this->exts->querySelector('div#order-list ul li.order:nth-child('.$i.')');

        $invoiceUrl = '';

        $this->exts->click_element($row);

        $this->isNoInvoice = false;

        $this->exts->waitTillPresent('a[href*="print/lau"]');

        $invoiceName = $this->exts->extract('div.order-detail div[class=col-sm-6]  h4.lr_box-title');
        $invoiceAmount =  '';
        $invoiceDate =  $this->exts->extract('div.order-detail div[class=col-sm-6]  span.font-primary');

        $invoice = array(
        'invoiceName' => $invoiceName,
        'invoiceDate' => $invoiceDate,
        'invoiceAmount' => $invoiceAmount,
        );

        $downloadBtn = 'a[href*="print/lau"]';
        // Download  invoice
            $this->exts->log('Invoices found: ' . count($invoices));
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoiceDate = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoiceDate, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        $this->exts->openUrl($this->invoicePageUrl);
    }
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->waitTillPresent("a[class*=next-page]:not([class*='disabled'])");
    if (
        $restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->querySelector("a[class*=next-page]:not([class*='disabled'])")
    ) {
        $paging_count++;
        $this->exts->log('Next invoice page found');
        $this->exts->click_element("a[class*=next-page]:not([class*='disabled'])");
        sleep(5);
        $this->processInvoices($paging_count);
    }
}