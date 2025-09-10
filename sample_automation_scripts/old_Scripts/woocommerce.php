<?php //migrated and updated login  and download code
// Server-Portal-ID: 15308 - Last modified: 13.08.2024 14:48:58 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://woocommerce.com/my-account/";
public $loginUrl = "https://woocommerce.com/my-account/";
public $username_selector = "input[id='usernameOrEmail']";
public $password_selector = "input[id='password']";
public $submit_btn = "form button[type='submit']";
public $cookie_acceptor = "a[class='a8c-cookie-banner__ok-button']";
public $logout_link = "a[class='header-menu-logout']";
public $login_tryout = 0;
public $order_selector = "a[href='/my-account/orders/']";
public $receipt_selector = "tr[class='order']";
public $receipt_number_selector = "td[class='order-number']";
public $receipt_date_selector = "td[class='order-date']";
public $receipt_url_selector = "td[class='order-actions']";
public $receipt_amount_selector = "span[class='woocommerce-Price-amount']";
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture("Home-page-without-cookie");
    
    $isCookieLoginSuccess = false;
    // if($this->exts->loadCookiesFromFile()) {
    //     sleep(5);
        
    //     // $this->exts->loadLocalStorageFromFile();
       
        
    //     // $this->exts->loadSessionStorageFromFile();
    //     $this->exts->load_session_files();
    //     sleep(5);
        
    //     $this->exts->openUrl($this->baseUrl);
    //     sleep(10);
    //     $this->exts->capture("Home-page-with-cookie");
        
    //     if($this->checkLogin()) {
    //         $isCookieLoginSuccess = true;
    //     } else {
    //         $this->exts->clearCookies();
    //         $this->exts->openUrl($this->baseUrl);
    //         sleep(10);
    //     }
    // }
    // click continue user
    if($this->exts->exists('.continue-as-user a[href*="my-dashboard"]')){
        $this->exts->moveToElementAndClick('.continue-as-user a[href*="my-dashboard"]');
        sleep(15);
    }
    
    if(!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");
        if($this->exts->exists('a.browsehappy__anyway[href*="/log-in"]') && !$this->exts->exists($this->username_selector)){
            $this->exts->moveToElementAndClick('a.browsehappy__anyway[href*="/log-in"]');
            sleep(10);
        }
        $this->fillForm(0);
        
        //cookie error
        if(($this->exts->urlContains('error=access-denied') || $this->exts->exists('#reload-button')) && !$this->checkLogin()){
            
            $this->exts->clearCookies();

            // $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            if($this->exts->exists('a.browsehappy__anyway[href*="/log-in"]') && !$this->exts->exists($this->username_selector)){
                $this->exts->moveToElementAndClick('a.browsehappy__anyway[href*="/log-in"]');
                sleep(10);
            }
            $this->fillForm(0);
        }

        $greeting_mesg = strtolower($this->exts->extract('form#authorize h1.greeting', null, 'innerText'));
        if ($this->exts->exists('form#authorize button.approve') && strpos($greeting_mesg, 'approve your wordpress.com account to sign in to woocommerce.com') !== false) {
            $this->exts->moveToElementAndClick('form#authorize button.approve');
            sleep(15);
        }

        $is_wordpress = $this->exts->getElement('a[href="/notifications"]') != null;
        if($is_wordpress){
            $this->exts->openUrl($this->baseUrl);
        } else {
            if ($this->exts->urlContains('/log-in/webauthn')
                && $this->exts->exists('.two-factor-authentication__verification-code-form button')
                && $this->exts->exists('.two-factor-authentication__actions button[data-e2e-link="2fa-sms-link"], .two-factor-authentication__actions button[data-e2e-link="2fa-otp-link"]')) {
                // 2FA with usb security key => change method
                $this->exts->moveToElementAndClick('.two-factor-authentication__actions button[data-e2e-link="2fa-sms-link"], .two-factor-authentication__actions button[data-e2e-link="2fa-otp-link"]');
                sleep(10);
            }
            $is_two_factor = $this->exts->getElement('form input[name="twoStepCode"]') != null;
            if($is_two_factor) {
                $this->exts->log(__FUNCTION__ . " :: Found two factor auth, solve it");
                $this->handleTwoFactorCode('form input[name="twoStepCode"]', 'button.two-factor-authentication__form-button[type="submit"], form div.two-factor-authentication__verification-code-form button', 'form div.two-factor-authentication__verification-code-form p');
            }
        }
        if($this->exts->exists('a.header-menu-login') && $this->checkLogin()){
            $this->exts->capture("can not login, site return hompage");
            $this->exts->moveToElementAndClick('a.header-menu-login');
            sleep(15);
        }
        if($this->exts->exists('form#authorize button#approve') && !$this->checkLogin()){
            //Authorize your WordPress.com account to sign into WooCommerce.com. click button "Approve"
            $this->exts->moveToElementAndClick('form#authorize button#approve');
            sleep(15);
        }
        if($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->log($this->exts->extract('body', null, 'innerHTML'));
            $this->downloadInvoice();
            if($this->isNoInvoice){
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->downloadInvoice();
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
}

function fillForm($count){
    $this->exts->log("Begin fillForm ".$count);
    try {
        
        if($this->exts->getElement($this->username_selector) != null) {
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            
            if($this->exts->getElement($this->username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                
                $this->exts->moveToElementAndClick('.login__form-action button[type="submit"]');
                sleep(5);

                // accecpt cookies
                if($this->exts->exists('button[class="cookie-banner__accept-all-button"]')){
                     $this->exts->log("Accecpt cookies");
                     $this->exts->moveToElementAndClick('button[class="cookie-banner__accept-all-button"]');
                }
                sleep(5);

                if($this->exts->getElement($this->password_selector) != null ) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);
                    
                    $this->exts->capture("2-filled-login");
                    
                    $this->exts->moveToElementAndClick('.login__form-action button[type="submit"]');
                    sleep(5);
                }else{
                    if($this->exts->exists('button[class="button form-button is-primary"]')){
                        $this->exts->log("click on send link button");
                        $this->exts->moveToElementAndClick('button[class="button form-button is-primary"]');
                    }
                    sleep(5);
                    $this->checkVeryfyEmail();
                }
                // click on send link button
                
            }

            if($this->exts->getElement($this->password_selector) != null ) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);
                    
                    $this->exts->capture("2-filled-login");
                    
                    $this->exts->moveToElementAndClick('.login__form-action button[type="submit"]');
                    sleep(5);
            }
            
            if ($this->exts->exists('.form-input-validation.is-error')) {
                $this->exts->loginFailure(1);
            }
            
          
            if ($this->exts->exists('.form-input-validation.is-error')) {
                $this->exts->loginFailure(1);
            }
            
            sleep(10);
        }
    } catch(\Exception $exception){
        $this->exts->log("Exception filling loginform ".$exception->getMessage());
    }
}

// private function clearChrome(){
//     $this->exts->log("Clearing browser history, cookie, cache");
//     $this->exts->openUrl('chrome://settings/clearBrowserData');
//     sleep(10);
//     $this->exts->capture("clear-page");
//     $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::TAB);
//     $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::TAB);
//     $this->exts->log("Choose ALL time");
//     $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
//     $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::END);
//     sleep(1);
//     $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
//     sleep(3);
//     $this->exts->openUrl('chrome://settings/clearBrowserData');
//     sleep(10);
//     $this->exts->capture("clear-page");
//     $this->exts->executeSafeScript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearBrowsingDataConfirm").click();');
//     sleep(15);
//     $this->exts->capture("after-clear");
// }

function handleTwoFactorCode($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector = '#mfa-login-block > p') {
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
            // $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
            
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
    }
}
//cookie-banner__accept-all-button
private function checkVeryfyEmail() {
    $two_factor_selector = '.magic-login__check-email-image, .magic-login__request-link .magic-login__form-text';
    $two_factor_message_selector = '.magic-login__check-email-image + p, .magic-login__request-link .magic-login__form-text';
    $two_factor_submit_selector = '';
    
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if($this->exts->exists('.magic-login__request-link .magic-login__form-text')){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        } else if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Pls input "OK" after finished!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            if($this->exts->exists('.magic-login__request-link .magic-login__form-text')){
                $this->exts->log("checkFillTwoFactor: Open url: ".$two_factor_code);
                $this->exts->openUrl($two_factor_code);
                sleep(25);
                $this->exts->capture("after-open-url-two-factor");
            } else {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
                
                $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
                
                $this->exts->refresh();
                sleep(15);
                
                if($this->exts->getElement($two_factor_selector) == null && $this->exts->getElement('input[name="usernameOrEmail"]') == null){
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    
                    if ($this->exts->exists('input[name="usernameOrEmail"]')) {
                        $this->exts->moveToElementAndType('input[name="usernameOrEmail"]', $this->username);
                        // $this->exts->sendKeys('input[name="usernameOrEmail"]', $this->username);
                        sleep(1);
                        
                        $this->exts->moveToElementAndClick('button[type="submit"]');
                        sleep(15);
                    }
                    $this->checkVeryfyEmail();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            }
        } else {
            $this->exts->refresh();
            sleep(15);
            if ($this->exts->exists('input[name="usernameOrEmail"]')) {
                $this->exts->log("user not click link in their email!");
            } else {
                $this->exts->log("Not received two factor code");
            }
            
        }
    }
}

function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if($this->exts->getElement('.header-menu-logout, [aria-label*="account menu"]') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch(\Exception $exception){
        $this->exts->log("Exception checking loggedin ".$exception);
    }
    
    return $isLoggedIn;
}

public function downloadInvoice(){
    $this->exts->log("Begin downlaod invoice ");
    try{
        if($this->exts->getElement($this->cookie_acceptor) != null) {
            $this->exts->moveToElementAndClick($this->cookie_acceptor);
            sleep(2);
        }
        
        if($this->exts->getElement($this->order_selector) != null) {
            $this->exts->moveToElementAndClick($this->order_selector);
            sleep(2);
        }
        if($this->exts->getElement($this->receipt_selector) != null) {
            $receipts = $this->exts->getElements($this->receipt_selector);
            foreach ($receipts as $key => $receipt) {
                $invoicePage =   $this->exts->getUrl();

                $receipt = $this->exts->getElements($this->receipt_selector)[$key];
                try {
                    if ($this->exts->getElement('a[href*="/view-order/"]', $receipt) == null) {
                        $this->exts->capture('3-no-view-order');
                        continue;
                    }
                    $receipturl = $this->exts->getElement('a[href*="/view-order/"]', $receipt)->getAttribute('href');
                    $arr = explode('/', $receipturl);
                    $invoiceId = $arr[count($arr) - 2];
                    //$receipturl = $receipturl . '?pdfinvoice=true&order=' . $invoiceId;
                    
                    $receiptName = ltrim($this->exts->getElement('.order-number', $receipt)->getText(),'#');
                    $receiptFileName = $receiptName.'.pdf';
                    
                    $receiptDate = $this->exts->getElement('.order-date', $receipt)->getText();
                    $parsed_date = $this->exts->parse_date($receiptDate);
                    if(trim($parsed_date) != "") $receiptDate = $parsed_date;
                    
                    // $receiptAmount = $receipt->findElement(WebDriverBy::className('woocommerce-Price-amount'))->getText();

                    // updated code
                    $receiptAmount = $this->exts->getElement('woocommerce-Price-amount', $receipt);

                    $this->exts->log($receiptAmount);
                    
                    if(stripos($receiptAmount, "ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬") !== false && stripos($receiptAmount, "ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬") <= 1) {
                        $receiptAmount = trim(substr($receiptAmount, 2)).' EUR';
                    } else if(stripos($receiptAmount, "ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬") !== false && stripos($receiptAmount, "ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬") > 1) {
                        $receiptAmount = trim(substr($receiptAmount, 0, strlen($receiptAmount)-2)).' EUR';
                    } else if(stripos($receiptAmount, "$") !== false && stripos($receiptAmount, "$") <= 1) {
                        $receiptAmount = trim(substr($receiptAmount, 2)).' USD';
                    } else if(stripos($receiptAmount, "$") !== false && stripos($receiptAmount, "$") > 1) {
                        $receiptAmount = trim(substr($receiptAmount, 0, strlen($receiptAmount)-2)).' USD';
                    } else if(stripos($receiptAmount, "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") !== false && stripos($receiptAmount, "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") <= 1) {
                        $receiptAmount = trim(substr($receiptAmount, 2)).' GBP';
                    } else if(stripos($receiptAmount, "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") !== false && stripos($receiptAmount, "ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â£") > 1) {
                        $receiptAmount = trim(substr($receiptAmount, 0, strlen($receiptAmount)-2)).' GBP';
                    }
                    $this->exts->log("invoice_amount - " . $receiptAmount);
                    $this->exts->log($receipturl);
                    
                    // $this->exts->open_new_window();
                    $newTab = $this->exts->openNewTab();
                    
                    $this->exts->openUrl($receipturl);
                    sleep(10);

                    // $handles = $this->exts->webdriver->getWindowHandles();
                    
                    $this->exts->moveToElementAndClick('.woocommerce-order-details-after-order-table a.pdf-link[href*="/my-account/view-order/"][href*="?pdfinvoice=true&order="]');
                    sleep(10);
                    
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $receiptFileName);
                    if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($receiptName, $receiptDate, $receiptAmount, $downloaded_file);
                        $this->exts->log(">>>>>>>>>>>>>>>Download Invoice successful!!!!");
                        $this->exts->closeTab($newTab);
                        sleep(4);
                    } else {
                        // $current_handles = $this->exts->webdriver->getWindowHandles();
                        // $this->exts->log('Tabs - '.count($current_handles).' - '.count($handles));
                        // if(count($current_handles) > count($handles)) {
                        //     $this->exts->webdriver->switchTo()->window(end($current_handles));
                        //     $downloaded_file = $this->exts->download_current($receiptFileName, 5);
                        //     if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        //         $this->exts->new_invoice($receiptName, $receiptDate, $receiptAmount, $downloaded_file);
                        //         $this->exts->log(">>>>>>>>>>>>>>>Download Invoice successful!!!!");
                        //     }

                        //     //Close the new window here only otherwise it will not get closed and number of windows will increase
                        //     $this->exts->webdriver->close();
                        //     $handles = $this->exts->webdriver->getWindowHandles();
                        //     $this->exts->webdriver->switchTo()->window(end($handles));
                        // } else {
                            $downloaded_file = $this->exts->download_current($receiptFileName, 5);
                            if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($receiptName, $receiptDate, $receiptAmount, $downloaded_file);
                                $this->exts->log(">>>>>>>>>>>>>>>Download Invoice successful!!!!");
                                $this->exts->closeTab($newTab);
                                sleep(4);
                            }
                        // }
                    }
                    $this->isNoInvoice = false;
                    
                    // Close all tab except current
                    // $this->exts->closeAllTabsButThis();
                    
                    // if($this->exts->getElement('a[href*="/view-order/"]', $receipt) == null) {
                    //     // close new tab too avoid too much tabs
                    //     $handles = $this->exts->webdriver->getWindowHandles();
                    //     if(count($handles) > 1){
                    //         $this->exts->webdriver->switchTo()->window(end($handles));
                    //         $this->exts->webdriver->close();
                    //         $handles = $this->exts->webdriver->getWindowHandles();
                    //         if(count($handles) > 1){
                    //             $this->exts->webdriver->switchTo()->window(end($handles));
                    //             $this->exts->webdriver->close();
                    //             $handles = $this->exts->webdriver->getWindowHandles();
                    //         }
                    //         $this->exts->webdriver->switchTo()->window($handles[0]);
                    //     }
                    // }
                    
                } catch(\Exception $exception){
                    $this->exts->log("Exception downloading invoice - ".$exception->getMessage());
                }
            }
        }
        
    }catch(\Exception $exception){
        $this->exts->log("Exception downlaoding invoice ".$exception->getMessage());
    }
}