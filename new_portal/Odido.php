<?php

/*Define constants used in script*/
public $baseUrl = 'https://www.odido.nl/zakelijk/';
public $loginUrl = 'https://www.odido.nl/zakelijk/login';
public $invoicePageUrl = 'https://www.odido.nl/my/facturen';

public $username_selector = 'input[data-interaction-id="login-username"]';
public $password_selector = 'input[data-interaction-id="login-password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"][class*="button-base"] ';

public $check_login_failed_selector = 'div.callout-danger';
public $check_login_success_selector = 'a[href="/logout"]';

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
    // Accecpt cookies
    if($this->exts->exists('button[data-interaction-id="koekje-settings-save-button"]')){
        $this->exts->moveToElementAndClick('button[data-interaction-id="koekje-settings-save-button"]');
        sleep(7);
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        // Accecpt cookies
        if($this->exts->exists('button[data-interaction-id="koekje-settings-save-button"]')){
            $this->exts->moveToElementAndClick('button[data-interaction-id="koekje-settings-save-button"]');
            sleep(7);
        }
        $this->fillForm(0);


        if($this->exts->exists('div[data-interaction-id="verify-2fa-Pincode"]')){
            $this->checkFillTwoFactor();
        }

    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();

        $this->doAfterLogin();
        sleep(5);

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
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

function doAfterLogin()
{
    //  Select subscription after login

    // Commented Selector for now script have different behaviour
    // $rows = $this->exts->getElements('button[id*="SelectSubscriberAfterLogin"]');
    $rows = $this->exts->getElements('button[name*="SwitchMobileSubscription"]');
    
    $this->exts->log("Subscription account count:: ". count($rows));

    foreach($rows as $row)
    {
        try{
            // Click on first Element
            $row->click();
        }catch(\Exception $e){
            $this->exts->log("Subscription Error:: ". $e->getMessage());
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

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="verification-code"]';
    $two_factor_message_selector = 'div.invalid-feedback';
    $two_factor_submit_selector = '';
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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

            $resultCodes = str_split($two_factor_code);

            foreach($resultCodes as $inputVal){
				$this->exts->log("inputVal: " . $inputVal);
				sleep(2);
				$this->exts->type_text_by_xdotool($inputVal);
			}

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            sleep(10);
            if ($this->exts->exists('div[class*="errorMessage"]')) {

                $this->exts->capture("wrong 2FA code error-" . $this->exts->two_factor_attempts);
                $this->exts->log('The code you entered is incorrect. Please try again.');
            }

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
private function downloadInvoices() {
	$this->exts->log(__FUNCTION__);

    $this->exts->waitTillPresent('div[id*="Invoices_Container"]');
	$this->exts->capture("4-invoices-classic");
	
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    for($i= 0; $i < $restrictPages && $this->exts->exists('a[id*="Invoices_MoreOrLessIvoices"][class="button-outline-base toggle"]'); $i++) {
        $this->exts->moveToElementAndClick('a[id*="Invoices_MoreOrLessIvoices"][class="button-outline-base toggle"]');
        sleep(7);
    }

    $invoices = [];
	$rows = $this->exts->getElements('div[id*="Invoices_Container"] ul.list-group-inset li');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('div.column-tablet-7 a[href*="my/facturen"][class*="button-base"]', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");

            parse_str(parse_url($invoiceUrl, PHP_URL_QUERY), $params);
            $invoiceName = $params['id'] ?? time();
			$invoiceName = trim($invoiceName);
			$invoiceDate = $this->exts->extract('div.column-6 p', $row);
			$invoiceAmount = $this->exts->extract('div.column-tablet-5 span', $row);;
            
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
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
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