<?php
// Server-Portal-ID: 8120 - Last modified: 20.11.2024 13:55:27 UTC - User: 1

public $baseUrl = 'https://www.metro.de/';
    
public $username_selector = 'form#authForm input[name="user_id"]';
public $password_selector = 'form#authForm input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#authForm button#submit';

public $check_login_failed_selector = 'div#toast-container div.toast-error, div.toast-error div.toast-message';
public $check_login_success_selector = 'a[href="/endsession"], a.profile_name, div.idam-user-profile, a[href*="/logout"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    $this->clearChrome();
    
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->accept_cookies();
    $this->exts->capture('1-init-page');
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null
        && strpos($this->exts->getUrl(), 'fehler') === false
        && strpos($this->exts->getUrl(), 'error_source=Login') === false) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        
        //Click to accept cookies button
        $this->accept_cookies();
        
        $this->exts->moveToElementAndClick('a[href*="/identity/externallogin"], .site-header-login a[href*="/authorize"], a[href*="/login"]');
        sleep(10);


        $this->checkFillLogin();
        sleep(20);
        
        $mesg = strtolower($this->exts->extract('div.main > h1', null, 'innerText'));
        if (strpos($mesg, 'server error') !== false) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            
            $this->checkFillLogin();
            sleep(20);
        }
    }
    sleep(5);
    $logout_element = $this->exts->execute_javascript('document.querySelector("cms-user-profile").shadowRoot.querySelector("a[href*=\"logout\"]");');
    sleep(5);
    // then check user logged in or not
    if (($this->exts->getElement($this->check_login_success_selector) != null || $logout_element != null)
        && strpos($this->exts->getUrl(), 'fehler') === false
        && strpos($this->exts->getUrl(), 'error_source=Login') === false) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in: '.$this->exts->getUrl());
        $this->exts->capture("3-login-success");
        
        $this->accept_cookies();
        
        // Open invoices url and download invoice
        $this->exts->openUrl('https://docs.metro.de/');
        sleep(15);
        $this->invoicePage();
        
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed: '.$this->exts->getUrl());
        if($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if(strpos(strtolower($this->exts->extract('div.alexUnavailableDescription', null, 'innerText')), 'not available') !== false){
            $this->exts->log('account not ready: '.$this->exts->extract('div.alexUnavailableDescription', null, 'innerText'));
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    $this->exts->type_key_by_xdotool('Return');
        sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}


private function accept_cookies() {
    if($this->exts->exists('.component.generic-component.component-position.cms.consent-disclaimer.reject.consent-disclaimer-intrusive-with-reject')) {
        $this->exts->moveToElementAndClick('button.accept-btn.btn-primary.field-accept-button-name');
        sleep(5);
    }
}

private function checkFillLogin() {
    sleep(3);
    $this->exts->log(__FUNCTION__.' current login url: '.$this->exts->getUrl());
    $this->exts->capture(__FUNCTION__);
    if($this->exts->getElement($this->password_selector) != null) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(5);
        
        if($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(5);
        
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);

        // Click second time if exists
        if($this->exts->exists($this->submit_login_selector)){
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(10);
        }
       // Click third time if exists
        if($this->exts->exists($this->submit_login_selector)){
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(10);
        }
       
        // Captcha Code
        if ($this->exts->exists('iframe[name="cs_chlg_ajax_frame_2"]')) {
             $this->exts->log("Fill Captcha");
                $this->switchToFrame('iframe[name="cs_chlg_ajax_frame_2"]');
                    $this->exts->log("switchToFrame");
                if($this->exts->exists('img[alt="Red dot"]')){

                   $this->exts->log("Process Captcha");
                    $this->exts->processImageCaptcha();
                }
            $this->exts->moveToElementAndClick('button[type="button"]');

        }


        

         
        
        // $current_url = $this->exts->getUrl();
        // if (strpos($current_url, 'error=403') !== false && strpos($current_url, 'error_description=Forbidden') !== false || strpos($current_url, 'error_source=Login') !== false) {
        //  $this->exts->capture('403-Forbidden');
        //  $this->exts->log('403: Forbidden');
        //  $this->exts->loginFailure();
        // }
        if($this->exts->getElement('div.box.box-red') != null) {
            $this->exts->account_not_ready();
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function processImageCaptcha()
{
    $this->exts->log("Processing Image Captcha");
    $this->exts->processCaptcha('img[alt="Red dot"]', 'input[id="ans"]');
    sleep(5);
    $this->exts->capture("1-login-page-filled");
    if ($this->exts->exists('button[type="button"][id="jar"]')) {
        $this->exts->click_by_xdotool('button[type="button"][id="jar"]');
    }
}

 public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

private function invoicePage() {
    //Change date range before account selection because if you submit the filter form for each account than it will download only for 1st account.
    //After dsate submission account get back to 1st card so it is better to change date 1st and then get each account url
    $this->changedaterage();
    sleep(10);
    
    $this->processInvoices();
}

public function changedaterage() {
    $current_url = $this->exts->getUrl();
    if($this->exts->exists('input#from')){
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if($restrictPages == 0){
            $startDate = date('Y-m-d', strtotime('-2 years'));
        } else {
            $startDate = date('Y-m-d', strtotime('-6 months'));
        }
        $this->exts->moveToElementAndType('div[data-testid="DateInputFieldFormField"] input', '');
        sleep(3);
        $this->exts->moveToElementAndType('div[data-testid="DateInputFieldFormField"] input', $startDate);
        sleep(3);
        $this->exts->moveToElementAndClick('input#id');
        sleep(1);
        $this->exts->moveToElementAndClick('button[data-testid="SearchFormButton"]');
        sleep(10);
        
        if ($this->exts->exists('.error-page')) {
            $mesg = strtolower(trim($this->exts->extract('.error-page h3', null, 'innerText')));
            if ($mesg != '' && strpos($mesg, '503') !== false) {
                $this->exts->openUrl($current_url);
                sleep(15);
                
                $this->exts->moveToElementAndType('div[data-testid="DateInputFieldFormField"] input', '');
                sleep(3);
                $this->exts->moveToElementAndType('div[data-testid="DateInputFieldFormField"] input', $startDate);
                sleep(3);
                $this->exts->moveToElementAndClick('button[data-testid="SearchFormButton"]');
                sleep(10);
            }
        }
    }
}

private function processInvoices($pageCount=1) {
    sleep(15);
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    
    $rows = $this->exts->getElements('table tbody > tr');
    foreach ($rows as $index=>$row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 7 && $this->exts->getElement('button', $tags[6]) != null) {
            $invoiceSelector = $this->exts->getElement('button', $tags[6]);
            $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-button-".$index."');", [$invoiceSelector]);
            $invoiceName = '';
            $invoiceDate = trim($tags[1]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';
            
            $this->isNoInvoice = false;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: '.$invoiceName);
            $this->exts->log('invoiceDate: '.$invoiceDate);
            $this->exts->log('invoiceAmount: '.$invoiceAmount);

            $this->exts->moveToElementAndClick('button#custom-pdf-button-'.$index);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $invoiceName = trim(explode('(', $invoiceName)[0]);
                $this->exts->log('Final invoice name: '.$invoiceName);

                // Download invoice if it not exisited
                if($this->exts->invoice_exists($invoiceName)){
                    $this->exts->log('Invoice existed '.$invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log('Timeout when download '.$invoiceFileName);
            }
        }
    }
    
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if($restrictPages == 0 && $pageCount < 20 && $this->exts->exists('button[data-testid="nextPageButton"]:not([disabled])')) {
        $pageCount++;
        $this->exts->moveToElementAndClick('button[data-testid="nextPageButton"]:not([disabled])');
        sleep(5);
        $this->processInvoices($pageCount);
    }
}