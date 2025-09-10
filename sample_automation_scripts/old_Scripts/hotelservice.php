<?php
// Server-Portal-ID: 1216 - Last modified: 12.11.2024 06:44:29 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://hotelservice.hrs.com/hpp';
public $loginUrl = 'https://hotelservice.hrs.com/hpp';
public $invoicePageUrl = 'https://hotelservice.hrs.com/hpp/Ledger';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form[data-vv-scope="loginForm"] button[type="submit"]';

public $check_login_failed_selector = 'span.field-validation-error';
public $check_login_success_selector = 'a#logout';

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
        $this->exts->success();
        sleep(2);

        $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->click_element("//a[contains(@class, 'btn-filter') and (contains(text(), 'Invoice') or contains(text(), 'Rechnung'))]");
        $this->invoicePagination();
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

            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2); // Portal itself has one second delay after showing toast
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

private function invoicePagination(){

    $this->exts->waitTillPresent('ul[class="pagination"] > li > a', 10);
    $totalPagination =  $this->exts->querySelectorAll('ul[class="pagination"] > li > a');

    $this->exts->log("Total number of page: " . count($totalPagination));

    if(count($totalPagination) > 0){
        for($i=0; $i < count($totalPagination); $i++){
         
            $this->exts->openUrl('https://hotelservice.hrs.com/hpp/Ledger?CurrentPage='.$i.'&PageSize=25');
            sleep(5);
            $this->exts->waitTillPresent('table#document-table tbody tr', 20);
            
            if($this->exts->getElement('table#document-table tbody tr') != null){
                    $this->exts->log('Open Next page');
                   $invoice = $this->processInvoices($i);
            }
        }
        
    }else{
          $this->processInvoices();
    }

    
}


private function processInvoices($paging_count = 1)
{    
    $this->exts->waitTillPresent('table#document-table tbody tr', 20);
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    $rows = $this->exts->querySelectorAll('table#document-table tbody tr');
    foreach ($rows as $row) {
        if ($this->exts->querySelector('ul.functions-menu a.pdf-btn', $row) != null) {
            $invoiceUrl = $this->exts->querySelector('ul.functions-menu a.pdf-btn', $row)->getAttribute('href');
            $nameText =  $this->exts->extract('td:nth-child(3)', $row);
            preg_match('/\d+$/', $nameText, $matches);
            $invoiceName = $matches[0];
            $invoiceAmount =  $this->exts->extract('td:nth-child(5)', $row);
            $invoiceDate =  $this->exts->extract('td:nth-child(2)', $row);

            $downloadBtn = $this->exts->querySelector('ul.functions-menu a.pdf-btn', $row);

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/y', 'Y-m-d');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
    
    }
   
    
}