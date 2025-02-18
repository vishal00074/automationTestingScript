<?php // migrated // last // updated login code
// Server-Portal-ID: 4010 - Last modified: 17.01.2025 03:32:04 UTC - User: 1

public $baseUrl = "https://app.sendgrid.com/login";
public $loginUrl = "https://app.sendgrid.com/login";
public $homePageUrl = "https://app.sendgrid.com/settings/billing";
public $username_selector = "input#usernameContainer-input-id, input#username";
public $password_selector = "input#passwordContainer-input-id, input#password";
public $submit_button_selector = "div.login-btn button,button[type='submit'][name='action']";
public $login_tryout = 0;

private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture("Home-page-without-cookie");
    
    if(!$this->checkLogin()) {
        $this->exts->capture("after-login-clicked");
        if($this->exts->exists('a.acceptAllButtonLower')){
            $this->exts->moveToElementAndClick('a.acceptAllButtonLower');
            sleep(5);
        }
        $this->fillForm(0);
        sleep(10); 
        if($this->exts->exists('div.security-checkup-continue-link a')){
            $this->exts->moveToElementAndClick('div.security-checkup-continue-link a');
            sleep(10);
        }
        if($this->exts->exists('a.acceptAllButtonLower')){
            $this->exts->moveToElementAndClick('a.acceptAllButtonLower');
            sleep(5);
        }
        $this->checkFillTwoFactor();
        sleep(5);
        
        if($this->exts->exists('div.setup-2fa-header-horizontal-progress h2') && strpos(strtolower($this->exts->extract('div.setup-2fa-header-horizontal-progress h2')), 'add two-factor authentication') !== false){
            $this->exts->log("Setup 2FA: " .$this->exts->extract('div.setup-2fa-header-horizontal-progress h2'));
            $this->exts->account_not_ready();
        }

        if($this->exts->exists('[data-qahook="setup2FARequiredEmailCheckpoint"]') && strpos(strtolower($this->exts->extract('[data-qahook="setup2FARequiredEmailCheckpoint"]')), 'secure your account with two-factor authentication') !== false){
            $this->exts->log("Setup 2FA: " .$this->exts->extract('[data-qahook="setup2FARequiredEmailCheckpoint"]'));
            $this->exts->account_not_ready();
        }

        $err_msg = $this->exts->extract('form.login-form .login-form-error, #login-error-alert-container');
        
        if ($err_msg != "" && $err_msg != null) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }
        
        if($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
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

function fillForm($count){
    $this->exts->log("Begin fillForm ".$count);
     $this->exts->waitTillPresent($this->username_selector);
    if( $this->exts->exists($this->username_selector)) {
        sleep(2);
        $this->login_tryout = (int)$this->login_tryout + 1;
        $this->exts->capture("2-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->check_solve_cloudflare_page();
        $this->exts->moveToElementAndClick('button[data-role="continue-btn"]');
        sleep(10);
        if( $this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter cloudflare");
            // $this->check_solve_blocked_page();
            sleep(5);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            
            sleep(8);
        }
    } else {
        $this->exts->capture("2-login-page-not-found");
    }
}

function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if($this->exts->getElement('li[data-logout="logout"] a') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
            
        }
    } catch(Exception $exception){
        $this->exts->log("Exception checking loggedin ".$exception);
    }
    return $isLoggedIn;
}

private function checkFillTwoFactor() {
    $two_factor_selector = 'input#authyTokenContainer-input-id';
    $two_factor_message_selector = 'p[role="codeSentText"]';
    $two_factor_submit_selector = 'form[role="validateForm"] [role="validateBtn"]';
    
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            
            if($this->exts->getElement($two_factor_selector) == null){
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
    } else {
        $two_factor_selector = 'input#twoFactorCode,input#code';
        $two_factor_message_selector = '//p[contains(text(),"with two-factor authentication")],//h1[contains(text(),"Verify Your Identity")]';
        $two_factor_submit_selector = 'button[type="submit"][data-qahook="validate2FAContinueButton"],form div button[type="submit"][name="action"]';
        
        if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            
            if($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null){
                $this->exts->two_factor_notif_msg_en = "";
                for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) { 
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getText()."\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
            }
            if($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
            }
            
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if(!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
                
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                $this->exts->capture("2.2-two-factor-clicked-".$this->exts->two_factor_attempts);
                
                if($this->exts->getElement($two_factor_selector) == null){
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
                
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
}

function invoicePage() {
    $this->exts->log("invoice Page");
    
    if ($this->exts->getElement('a[data-nav-title="settings"]') != null) {
        $this->exts->moveToElementAndClick('a[data-nav-title="settings"]');
        sleep(15);
    }
    
    if ($this->exts->getElement('ul.subpages a[href*="/account/billing"]') != null) {
        $this->exts->moveToElementAndClick('ul.subpages a[href*="/account/billing"]');
        sleep(15);
    } else if ($this->exts->getElement('ul.subpages a[href*="/account/details"]') != null) {
        $this->exts->moveToElementAndClick('ul.subpages a[href*="/account/details"]');
        sleep(15);
    } else {
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);
    }
    $this->exts->moveToElementAndClick('li[data-qahook="billingTab"]');
    sleep(15);
    $this->downloadInvoice();
    
    if ($this->totalFiles == 0) {
        $this->exts->log("No invoices!!");
        $this->exts->no_invoice();
    }
    $this->exts->success();
}

public $tryFindBilling = 0;
public $totalFiles = 0;
function downloadInvoice(){
    $this->exts->log("Begin downlaod invoice 1");
    
    $this->exts->capture('4-List-invoices');
    
    try{
        if($this->exts->getElement("div.invoice-list > div") != null) {
            $invoices = array();
            $receipts = $this->exts->getElements('div.invoice-list > div');
            foreach ($receipts as $i => $receipt) {
                $this->exts->log("each record");
                $tags = $this->exts->getElements('div');
                if (count($tags) >= 3 && $this->exts->getElement('a.pdf-dowload-link', $receipt) != null) {
                    $receiptDate = trim($this->getInnerTextByJS('a.pdf-dowload-link', $receipt));
                    $receiptUrl = $this->exts->getElement('a.pdf-dowload-link', $receipt);
                    $this->exts->execute_javascript(
                        "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                        array($receiptUrl, $i)
                    );
                    
                    $receiptUrl = "div.invoice-list > div a.pdf-dowload-link#invoice" . $i;
                    // $receiptName = str_replace(",", "", $receiptDate);
                    $receiptName = preg_replace('/[, ]/', '', $receiptDate);
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate,'M d, Y','Y-m-d');
                    $receiptAmount = trim($this->getInnerTextByJS('div.amount span', $receipt));
                    $receiptAmount = str_replace("$", "", $receiptAmount) . ' USD';
                    $this->exts->log($receiptAmount);
                    
                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName,
                        'receiptUrl' => $receiptUrl,
                    );
                    
                    array_push($invoices, $invoice);
                    
                }
                
            }
            
            $this->exts->log("Number of invoices: " . count($invoices));
            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                if ($this->exts->getElement($invoice['receiptUrl']) != null) {
                    $this->exts->moveToElementAndClick($invoice['receiptUrl']);
                    
                    $this->exts->wait_and_check_download('pdf');
                    
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                    sleep(1);
                    
                    if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        // Create new invoice if it not exisited
                        if($this->exts->invoice_exists($invoice['receiptName'])){
                            $this->exts->log('Invoice existed '.$invoice['receiptFileName']);
                        } else {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $invoice['receiptFileName']);
                            sleep(1);
                        }
                    } else {
                        $this->exts->log('Timeout when download '.$invoice['receiptFileName']);
                    }
                }
                
            }
            
        } else {
            $str = "var div = document.querySelector('iframe.appcues-tooltip-container'); if (div != null) {  div.style.display = \"none\"; }";
            $this->exts->execute_javascript($str);
            sleep(1);
            
            if(stripos($this->exts->getUrl(), "/account/billing") == false && $this->tryFindBilling == 0) {
                if(stripos($this->exts->getUrl(), "/account/details") == false) {
                    $this->exts->openUrl($this->homePageUrl);
                    sleep(15);
                } else {
                    if ($this->exts->getElement('ul li.tab[data-qahook="billingTab"]') != null) {
                        $this->exts->moveToElementAndClick('ul li.tab[data-qahook="billingTab"]');
                        sleep(15);
                    } else {
                        $this->exts->openUrl($this->homePageUrl);
                        sleep(15);
                    }
                }
                $this->tryFindBilling = $this->tryFindBilling + 1;
                $this->downloadInvoice();
            }
        }
        
    } catch(\Exception $exception){
        $this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
    }
}

function getInnerTextByJS($selector_or_object, $parent = null){
    if($selector_or_object == null){
        $this->exts->log(__FUNCTION__.' Can not get innerText of null');
        return;
    }
    $element = $selector_or_object;
    if(is_string($selector_or_object)){
        $element = $this->exts->getElement($selector_or_object, $parent);
        if($element == null){
            $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
        }
        if($element == null){
            $this->exts->log(__FUNCTION__.':: Can not found element with selector/xpath: '. $selector_or_object);
        }
    }
    if ($element != null) {
        return $this->exts->execute_javascript("return arguments[0].innerText", [$element]);
    }
}