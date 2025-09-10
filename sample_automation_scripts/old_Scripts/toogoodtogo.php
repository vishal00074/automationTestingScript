<?php // update login and download code
// Server-Portal-ID: 534311 - Last modified: 08.02.2025 15:18:57 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://store.toogoodtogo.com/login';
public $loginUrl = 'https://store.toogoodtogo.com/login';
public $invoicePageUrl = '';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div[variant="error"]';
public $check_login_success_selector = 'a[href*="logout"], a[href^="/chains/"][href$="/dashboard"]';

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

        $this->exts->click_element('a[href*="sales"]');
        $this->processInvoices();
        
        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        
        $this->exts->success();
    } else {
        if ($this->exts->exists($this->check_login_failed_selector)) {
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
            sleep(5);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("1-login-page-filled");
            sleep(5);

        

            if ($this->exts->exists('button[type = "submit"][disabled]')) {
                sleep(5);
            }

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

private function processInvoices($paging_count = 1) {
    $this->exts->waitTillPresent('tr.MuiTableRow-root', 30);
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    
    $paginationCountString = $this->exts->extract('p.MuiTablePagination-displayedRows');
    $explodeString = explode(' ', $paginationCountString);
    
    $totalInvoices = $explodeString[2];
    $this->exts->log('Total invoices from pagination: ' . $totalInvoices);
    
    $totalPages = intdiv($totalInvoices, 5);
    $this->exts->log('Total pages: ' . $totalPages);
    
    for($i=0; $i<=$totalPages; $i++){
        $this->exts->log('Pagination page: ' . $i + 1);
        
        $rows = $this->exts->querySelectorAll('tr.MuiTableRow-root');
        $anchor = '.MuiTableCell-root:nth-child(3) a';

        foreach ($rows as $row) {
            if ($this->exts->querySelector($anchor, $row) != null) {

                $invoiceUrl = $this->exts->querySelector($anchor, $row)->getAttribute('href');
                $invoiceName = $this->exts->extract('.MuiTableCell-root:first-child div', $row);
                $invoiceAmount = '';
                $invoiceDate = $invoiceName;

                $downloadBtn = $this->exts->querySelector($anchor, $row);

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
        
        if ($this->exts->exists('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:nth-child(3)')) {
            $this->exts->click_element('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:nth-child(3)');
        }
        
        sleep(5);
    }
    
    if ($this->exts->exists('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:first-child')) {
        $this->exts->click_element('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:first-child');
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    $count = 0;
    foreach ($invoices as $invoice) {
        $count++;
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        $invoiceFileName = $invoice['invoiceName'] . '.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F Y', 'Y-m-t');
        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
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
        
        if($count >= 5 && $count % 5 === 0) {
            if ($this->exts->exists('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:nth-child(3)')) {
                $this->exts->click_element('div.MuiTablePagination-toolbar div.css-7ukd3u .MuiButtonBase-root:nth-child(3)');
            }
            
            sleep(5);
        }
    }
}