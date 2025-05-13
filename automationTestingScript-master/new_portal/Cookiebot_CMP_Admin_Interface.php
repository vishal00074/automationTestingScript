<?php 

/*Define constants used in script*/
public $baseUrl = 'https://admin.cookiebot.com/';
public $loginUrl = 'https://admin.cookiebot.com/';
public $invoicePageUrl = 'https://admin.cookiebot.com/settings?tab=invoices';

public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="action"][class*="_button-login-id"]';

public $check_login_failed_selector = 'span#error-element-password';
public $check_login_success_selector = 'button[aria-label="avatar-menu-button"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->loadCookiesFromFile();

    sleep(10);
   
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
       
        $this->fillForm(0);

    }

    // Accecpt Cookies 
    $this->exts->waitTillPresent('div.CybotCookiebotDialogContentWrapper');
    if($this->exts->exists('div.CybotCookiebotDialogContentWrapper')){
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        sleep(5);
    }  

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($this->invoicePageUrl);
        $this->downloadInvoices();
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
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            if ($this->exts->exists($this->submit_login_selector)  && !$this->exts->exists('img[alt="captcha"]'))  {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }

            if ($this->exts->exists('img[alt="captcha"]')) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->log('process Image Captcha 1');
                $this->processImageCaptcha();

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(10);
                }

                
            }

            if ($this->exts->exists($this->submit_login_selector))  {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }

            if ($this->exts->exists('img[alt="captcha"]')) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->log('process Image Captcha 1');
                $this->processImageCaptcha();

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(10);
                }

                
            }

            if ($this->exts->exists('img[alt="captcha"]')) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->log('process Image Captcha 1');
                $this->processImageCaptcha();

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(10);
                }

                
            }


            $this->exts->waitTillPresent($this->password_selector);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }
            if ($this->exts->exists('button[class*="_button-login-password"]')) {
                $this->exts->moveToElementAndClick('button[class*="_button-login-password"]');
                sleep(10);
            }
            $this->exts->capture("1-login-page-filled");
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function processImageCaptcha()
{
    $this->exts->log("Processing Image Captcha");
    $this->exts->processCaptcha('img[alt="captcha"]', 'input[id="captcha"]');
    sleep(5);

    $this->exts->capture("filled-captcha");
    $this->exts->moveToElementAndClick($this->submit_login_selector);
    sleep(7);
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
    return $isLoggedIn;
}


private function downloadInvoices($count = 0) {
	$this->exts->log(__FUNCTION__);

    $this->exts->waitTillPresent('div[class*="chakra-stack"]');
	$this->exts->capture("4-invoices-classic");

	$rows = $this->exts->getElements('div[class*="chakra-stack"] div[role="row"]');
	foreach ($rows as $row) {
		$downloadBtn = $this->exts->getElement('button[type="button"][aria-label="download invoice"]', $row);
		if($downloadBtn != null) {
			$invoiceUrl = '';
			$invoiceName = time();// Added Custom name no name found
			$invoiceDate = $this->exts->extract('div[aria-label="invoice"] div:nth-child(1)', $row);
			$invoiceAmount = $this->exts->extract('div[aria-label="invoice"] div:nth-child(2)', $row);;
            

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: '.$invoiceName);
            $this->exts->log('invoiceDate: '.$invoiceDate);
            $this->exts->log('invoiceAmount: '.$invoiceAmount);
            $this->exts->log('invoiceUrl: '.$invoiceUrl);
    
            $invoiceFileName = $invoiceName.'.pdf';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
            $this->exts->log('Date parsed: '.$invoiceDate);
            
            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
			$this->isNoInvoice = false;
		}
	}


    while ($count < $restrictPages && $this->exts->exists('button[aria-label="next"]:not(:disabled)')) {
        $this->exts->moveToElementAndClick('button[aria-label="next"]:not(:disabled)');
        sleep(7);
        $count++; 
        $this->downloadInvoices($count);
    }
    
}