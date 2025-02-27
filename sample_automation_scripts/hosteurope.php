<?php // migrated and updated login code
// Server-Portal-ID: 396 - Last modified: 04.02.2025 06:40:52 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://kis.hosteurope.de/";
public $loginUrl = "https://kis.hosteurope.de/";
public $ssoLoginUrl = "https://sso.hosteurope.de/";
public $username_selector = 'input[name="identifier"]';
public $password_selector = 'input[name="password"]';
public $submit_button_selector = 'button[type="submit"]';
public $login_tryout = 0;
public $restrictPages = 3;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */

private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");
    
    if ($this->exts->exists('body > div.ReactModalPortal > div > div > div > div > form > div > div:nth-child(3) > div > span:nth-child(2) > span > button')) {
        $this->exts->click_by_xdotool('body > div.ReactModalPortal > div > div > div > div > form > div > div:nth-child(3) > div > span:nth-child(2) > span > button');
        
        $this->exts->openUrl($this->baseUrl);
    }
    
    $isCookieLoginSuccess = false;
    if($this->exts->loadCookiesFromFile()) {
        sleep(2);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        
        if($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            //$this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
        }
    }
    
    if(!$isCookieLoginSuccess) {
        sleep(15);
        //redirected you too many times.
        for ($i=0; $i < 2; $i++) { 
            if($this->exts->exists('#reload-button')){
                $this->exts->click_by_xdotool('#reload-button');
                sleep(10);
            } else {
                break;
            }
        }
        if ($this->exts->getElement($this->password_selector) === null) {
            $this->exts->clearCookies();
            sleep(1);
            $this->exts->execute_javascript('window.localStorage.clear(); window.sessionStorage.clear();');
            sleep(1);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
        }
        $this->fillForm(0);
        sleep(10);

        $ssoLoginUrl = $this->exts->findTabMatchedUrl(['sso']);
        if ($ssoLoginUrl != null) {
            $this->exts->openUrl($this->ssoLoginUrl);
            sleep(10);
            $this->exts->log('Login with sso');

            $this->fillForm(1);
        }

        $this->checkFillTwoFactor();
        
        if ($this->exts->exists('body > div.ReactModalPortal > div > div > div > div > form > div > div:nth-child(3) > div > span:nth-child(2) > span > button')) {
            $this->exts->click_by_xdotool('body > div.ReactModalPortal > div > div > div > div > form > div > div:nth-child(3) > div > span:nth-child(2) > span > button');
            $this->exts->openUrl($this->baseUrl);
        }
        sleep(2);
        
        if($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $err_msg = $this->exts->extract('div[style="margin-bottom: 16px;"] > div > span');
            if ($err_msg != "" && $err_msg != null && strpos(strtolower($err_msg), 'passwor')) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            } else if(strpos(strtolower($this->exts->extract('div[style="margin-bottom: 16px;"] > div > span')), 'beim einloggen ist leider ein fehler aufgetreten') !== false
                   || strpos(strtolower($this->exts->extract('div[style="margin-bottom: 16px;"] > div > span')), 'something went wrong when trying to log in') !== false){
                $this->exts->log("account not ready" . $this->exts->extract('div[style="margin-bottom: 16px;"] > div > span'));
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                if($this->exts->getElement('//span[contains(text(),"Username or Password are incorrect")]', null, 'xpath') != null) {
                    $this->exts->loginFailure(1);
                } else if($this->exts->getElement('//span[contains(text(),"Something went wrong when trying to log in")]', null, 'xpath') != null) {
                    $this->exts->account_not_ready();
                } else if($this->exts->getElement('//span[contains(text(),"einloggen ist leider ein fehler aufgetreten")]', null, 'xpath') != null) {
                    $this->exts->account_not_ready();
                } else if($this->exts->getElement('//span[contains(text(),"Beim Einloggen ist leider ein Fehler aufgetreten.")]', null, 'xpath') != null) {
                    $this->exts->account_not_ready();
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    $this->exts->type_key_by_xdotool("Tab");
    $this->exts->type_key_by_xdotool("Tab");
    $this->exts->log("Choose ALL time");
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool("End");
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearBrowsingDataConfirm").click();');
    sleep(15);
    $this->exts->capture("after-clear");
}


function fillForm($count){
    $this->exts->log("Begin fillForm ".$count);
    try {
        sleep(1);
        if( $this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            
           $this->checkFillRecaptcha();
            sleep(5);
            $this->exts->click_by_xdotool($this->submit_button_selector);
            
            sleep(25);
            if ($this->exts->getElementByText('span', ['Block von google.com'], null, false)) {
                $this->exts->type_key_by_xdotool('F5');
                sleep(10);
                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                $this->exts->type_key_by_xdotool("Delete");
                $this->exts->type_text_by_xdotool($this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(1);
                $this->exts->type_key_by_xdotool("Delete");
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);

                $this->capture_by_chromedevtool("2-login-page-filled");
                 $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(25);
            }
            $this->checkFillRecaptcha();
            if($this->exts->exists($this->submit_button_selector)){
                $this->exts->click_by_xdotool($this->submit_button_selector);
            }
        }
        
        sleep(10);
    } catch(\Exception $exception){
        $this->exts->log("Exception filling loginform ".$exception->getMessage());
    }
}
private function checkFillRecaptcha() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        
        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i=0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');
            
            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[100000], "", 0);
                return found ? "___grecaptcha_cfg.clients[100000]." + result : null;
            ');
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null || $gcallbackFunction != 'null'){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
}


/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if($this->exts->exists('#fl_logout_btn')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch(Exception $exception){
        $this->exts->log("Exception checking loggedin ".$exception);
    }
    return $isLoggedIn;
}

// 2 FA
private function checkFillTwoFactor() {
    $two_factor_selector = 'input[name*="verificationCode"]';
    $two_factor_message_selector = 'div p';
    $two_factor_submit_selector = 'form button[type*="submit"]';
    
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
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
            // $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector , $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->click_by_xdotool($two_factor_submit_selector);
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
    }
}

function invoicePage() {
    $this->exts->log("Invoice page");
    
    if($this->exts->exists('a.ui-dialog-titlebar-close')){
        $this->exts->click_by_xdotool('a.ui-dialog-titlebar-close');
    }
    sleep(5);
    
    $this->exts->click_by_xdotool('.fl_quick_menu a[href="/kundenkonto/rechnungen/"]');
    sleep(5);
    
    if($this->exts->exists('a.ui-dialog-titlebar-close')){
        $this->exts->click_by_xdotool('a.ui-dialog-titlebar-close');
    }
    sleep(5);
    if((int)$this->restrictPages == 0) {
        if (!$this->exts->exists('td.tdrechtshead a[href*="all=1"]')) {
            sleep(10);
        }
        $this->exts->click_by_xdotool('td.tdrechtshead a[href*="all=1"]');
        sleep(10);
    }
    
    $this->downloadInvoice();
    
    if ($this->totalFiles == 0) {
        $this->exts->log("No invoice !!! ");
        $this->exts->no_invoice();
    }
    $this->exts->success();
}

/**
 *method to download incoice
 */
public $totalFiles = 0;
function downloadInvoice($count=1, $pageCount=1){
    $this->exts->log("Begin download invoice");
    if($this->exts->exists('a.ui-dialog-titlebar-close')){
        $this->exts->click_by_xdotool('a.ui-dialog-titlebar-close');
    }
    
    sleep(10);
    
    $this->exts->capture('4-List-invoice');
    
    try{
        if ($this->exts->getElement('.fl_welcome > table > tbody> tr') != null) {
            $receipts = $this->exts->getElements('.fl_welcome > table > tbody> tr');
            $invoices = array();
            foreach ($receipts as $i=> $receipt) {
                $tags = $this->exts->getElements('td', $receipt);
                if (count($tags) == 6 && $this->exts->getElement('td form', $receipt) != null) {
                    $receiptDate = trim($tags[2]->getAttribute('innerText'));
                    $receiptName = trim($tags[3]->getAttribute('innerText'));
                    $receiptAmount = trim($tags[5]->getAttribute('innerText'));
                    $receiptFileName = $receiptName . '.pdf';
                    $receiptSelector = '.fl_welcome > table > tbody> tr:nth-child(' . ($i+1) . ') td:nth-child(2) form input[type="submit"]';
                    
                    $this->exts->log("_____________________" . ($i+1) . "___________________________________________");
                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $this->exts->log("Invoice Selector: " . $receiptSelector);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("________________________________________________________________");
                    
                    $invoice = array(
                        'receiptDate' => $receiptDate,
                        'receiptName' => $receiptName,
                        'receiptAmount' => $receiptAmount,
                        'receiptSelector' => $receiptSelector,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }
            
            $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));
            
            $this->totalFiles = count($invoices);
            $count = 1;
            foreach ($invoices as $invoice) {
                $invoiceDate = $this->exts->parse_date($invoice['receiptDate'], 'd.m.Y','Y-m-d');
                if ($invoiceDate == '') {
                    $invoiceDate = $invoice['receiptDate'];
                }
                $this->exts->log('Date parsed: '.$invoiceDate);
                
                $this->exts->click_by_xdotool($invoice['receiptSelector']);
                sleep(15);

                $this->exts->wait_and_check_download('pdf');
                
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                $this->exts->log("Download file: " . $downloaded_file);
                
                if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoiceDate , $invoice['receiptAmount'], $downloaded_file);
                    sleep(5);
                    $count++;
                }
            }
        }
    } catch(\Exception $exception){
        $this->exts->log("Exception downloading invoice ".$exception->getMessage());
    }
}