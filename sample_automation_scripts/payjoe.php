<?php // added download code 
// Server-Portal-ID: 778938 - Last modified: 24.12.2024 14:08:10 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://login.payjoe.de/login';
public $loginUrl = 'https://login.payjoe.de/login';
public $invoicePageUrl = 'https://login.payjoe.de/activities';

public $username_selector = 'input[id="mat-input-0"]';
public $password_selector = 'input[id="mat-input-1"]';
public $remember_me_selector = 'label[class="mat-checkbox-layout"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div mat-error[id="mat-error-0"]';
public $check_login_success_selector = 'a[href="/activities"]';

public $isNoInvoice = true;

/**

 * Entry Method thats called for a portal

 * @param Integer $count Number of times portal is retried.  a[href*="SignIn"]

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
        

        $this->processInvoice();

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

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }

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

    return $isLoggedIn;
}


/**
 * download invoice of current year
 * 
 */
private function processInvoice()
{
    sleep(5);
    $year = date('Y');
    $month = date('m');
    $this->exts->log("Invoice Listing of year : " . $year. "Current month: " . $month);

    for($i=1; $i <= $month; $i++)
    {
      $this->exts->log("Download Invoice year: " . $year. "month: " . $i);  
       $openUrl = 'https://login.payjoe.de/activities?year='.$year.'&month='.$i;
       $this->exts->openUrl($openUrl);
       sleep(5);
       $this->exts->waitTillPresent('table > tbody > tr');

       $this->exts->capture("4-invoices-page-month ".$i);

       $this->downloadInvoice();
   
    }
}

private function downloadInvoice()
{
    $invoices = [];

    $rows = $this->exts->getElements('table > tbody > tr');
    $this->exts->log('No of rows: '.count($rows));
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);

        $this->exts->log('No of columns: '.count($tags));

        if(count($tags) > 1 && $this->exts->getElement('a[href*="/download"]', $tags[6]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="/download"]', $tags[6])->getAttribute("href");
            $parts = explode('/', $invoiceUrl);
            $lastPart = array_pop($parts); 

            sleep(1);
            $invoiceName = trim($tags[1]->getAttribute('innerText')) . '_' . time();

            $invoiceName = trim(preg_replace('/[^\d]/', '', $invoiceName));
            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y','Y-m-d');
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