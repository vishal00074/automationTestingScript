<?php // updated login code
// Server-Portal-ID: 1235036 - Last modified: 13.01.2025 13:11:22 UTC - User: 1

public $baseUrl = 'https://app.apollo.io/#/login';
public $loginUrl = 'https://app.apollo.io/#/login';
public $invoicePageUrl = 'https://app.apollo.io/#/settings/plans/billing';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '//label[.//div[@data-cy-status="unchecked"]]';
public $submit_login_selector = 'button[data-cy="login-button"]';

public $check_login_failed_selector = 'form > div';
public $check_login_success_selector = '[data-tour="user-profile-button"]';

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
	$this->check_solve_cloudflare_page();
	if (!$this->checkLogin()) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(25);
		$this->check_solve_cloudflare_page();
		$this->fillForm(0);
	}

	if ($this->checkLogin()) {
		$this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
		$this->exts->capture("LoginSuccess");
		$this->exts->success();
		sleep(2);

		$this->exts->openUrl($this->invoicePageUrl);
		$this->processInvoices();
		// Final, check no invoice
		if ($this->isNoInvoice) {
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), " don't match with any") !== false) {
			$this->exts->log("Wrong credential !!!!");
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function check_solve_cloudflare_page() {
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if(
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ){
        for ($waiting=0; $waiting < 10; $waiting++) {
            sleep(2);
            if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                sleep(3);
                break;
            }
        }
    }

    if($this->exts->exists($unsolved_cloudflare_input_xpath)){
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
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
				$this->exts->click_element($this->remember_me_selector);
				sleep(1);
			}

			$this->exts->click_by_xdotool($this->submit_login_selector);
			sleep(2); // Portal itself has one second delay after showing toast
			$this->check_solve_cloudflare_page();

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
	$this->exts->execute_javascript('window.scrollBy(0, 500);');
	$this->exts->waitTillPresent('table tr', 20);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->querySelectorAll('table tr');
	foreach ($rows as $row) {
		if ($this->exts->querySelector('td:nth-child(7) a', $row) != null) {
			$invoiceUrl = '';
			$invoiceName = $this->exts->extract('td:nth-child(2)', $row);
			$invoiceAmount =  $this->exts->extract('td:nth-child(6)', $row);
			$invoiceDate =  $this->exts->extract('td:nth-child(1)', $row);

			$downloadBtn = $this->exts->querySelector('td:nth-child(7) a', $row);

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