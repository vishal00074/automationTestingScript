<?php // added  captcha code and created download code 
// Server-Portal-ID: 801305 - Last modified: 27.12.2024 14:13:15 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://pn.notebooksbilliger.de/html.cgi?filename=index.htm';
public $loginUrl = 'https://pn.notebooksbilliger.de/html.cgi?filename=index.htm';
public $invoicePageUrl = '';

public $username_selector = 'input[name="PartnerID"]';
public $password_selector = 'input[name="Passwort"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div[class="well"]';
public $check_login_success_selector = 'span[rel="/logout.cgi"]';

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

        sleep(10);
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();

        if($this->exts->exists('div#menu_finances')){
            $this->exts->click_by_xdotool('div#menu_finances');
            sleep(4);
        }

        if ($this->exts->exists('div#collapseThree ul  li a[href*="auszahlungen.cgi"]')) {
            $this->exts->click_by_xdotool('div#collapseThree ul  li a[href*="auszahlungen.cgi"]');
            sleep(4);
        }

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

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
            }

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

            if($this->exts->exists('div > img[src*= "captcha"]')){
                $this->processImageCaptcha($count);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


private function processImageCaptcha($count)
{
    $this->exts->log("Processing Image Captcha");
    $this->exts->processCaptcha('div > img[src *= "captcha"]', 'input[name="challenge"]');
    sleep(5);
    $this->exts->capture("1-login-page-filled");
    if ($this->exts->exists($this->submit_login_selector)) {
        $this->exts->click_by_xdotool($this->submit_login_selector);
    }
    $this->exts->waitTillPresent($this->check_login_success_selector, 10);
    if(!$this->exts->exists($this->check_login_success_selector)){
        if($count < 3){
            $this->exts->openUrl($this->loginUrl);
            $count = $count + 1;
            $this->fillForm($count);
            sleep(4);
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

private function invoicePagination()
{
    $pages = $this->exts->querySelectorAll('div.btn-group-pagination a');
    $count = 0;
    if(count($pages) > 2){
        $count = count($pages) - 2;
    }
    $this->exts->log("Total Invoice pages: " .  $count );

    foreach($pages as $page){
        $pageNumber =  $this->exts->extract('a[class="btn btn-default active"] > font >font', $page);

        $this->exts->log("Invoice page number " .$pageNumber);

        $this->processInvoices($pageNumber);
        
        if(!$this->exts->exists('div.btn-group-pagination > a[rel="next"][class="btn btn-default "]')){
            break;
        }
        $this->exts->moveToElementAndClick('div.btn-group-pagination > a[rel="next"][class="btn btn-default "]');
        sleep(7);
    }
}

private function processInvoices($paging_count = 1)
{
	$this->exts->waitTillPresent('table tr', 20);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->querySelectorAll('table tr');
	foreach ($rows as $key => $row) {
        if($key == 0){
            continue;
        }

		if ($this->exts->querySelector('td:nth-child(11) a', $row) != null) {
			$invoiceUrl = $this->exts->getElement('td:nth-child(11) a', $row)->getAttribute('href');
           
            $trCount = $key + 1;
			$invoiceName = $this->exts->extract('tr:nth-child('. $trCount.') td:nth-child(2)'). $trCount
            . $this->exts->extract('tr:nth-child(' . $trCount . ') td:nth-child(3)  >');
			$invoiceAmount = $this->exts->extract('tr:nth-child(' . $trCount . ') td:nth-child(7)');
            
			$invoiceDate =  $this->exts->extract('tr:nth-child(' . $trCount . ') td:nth-child(1)');

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

		$invoiceFileName = $invoice['invoiceName'] . '.csv';
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/y', 'Y-m-d');
		$this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

        $newTab = $this->exts->openNewTab();
          
        $this->exts->openUrl($invoice['invoiceUrl']);
        sleep(7);
        $downloadBtn  = 'button[value = "Download"]';
        sleep(4);
		// $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		$downloaded_file = $this->exts->click_and_download($downloadBtn, 'csv', $invoiceFileName);
        sleep(2);
		if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
		}
        $this->exts->closeTab($newTab);
        sleep(4);
	}
}