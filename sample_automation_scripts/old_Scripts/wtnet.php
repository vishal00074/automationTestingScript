<?php 
// Server-Portal-ID: 10067 - Last modified: 17.12.2024 13:45:36 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://portal.wtnet.de/';
public $loginUrl = 'https://portal.wtnet.de/';
public $invoicePageUrl = 'https://portal.wtnet.de/index.php?page=rechnungen';

public $username_selector = 'center form table tbody tr td input[type="text"]';
public $password_selector = 'center form table tbody tr td input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'center form table tbody tr td input[type="submit"]';

public $check_login_failed_selector = 'div.alert-error';
public $check_login_success_selector = 'a[href="?page=logout"]';

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
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($this->invoicePageUrl);
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

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
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



    if ($isLoggedIn) {

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    }

    return $isLoggedIn;
}

private function processInvoices($paging_count = 1)
{
    $this->exts->waitTillPresent('table tbody tr', 30);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->querySelectorAll('table tbody tr');
    foreach ($rows as $row) {
        if ($this->exts->querySelector('td:nth-child(3) a', $row) != null) {
            $invoiceUrl = $this->exts->querySelector('td:nth-child(3) a', $row)->getAttribute('href');
            $invoiceName = $this->exts->extract('td:nth-child(1)', $row);
            $invoiceAmount =  '';
            $invoiceDate =  $this->exts->extract('td:nth-child(2)', $row);

            $downloadBtn = $this->exts->querySelector('td:nth-child(3) a', $row);

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

        // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
    }
}