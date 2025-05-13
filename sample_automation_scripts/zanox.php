<?php // updated login code
// Server-Portal-ID: 921 - Last modified: 05.12.2024 13:52:34 UTC - User: 1

public $baseUrl = 'https://ui.awin.com/idp/de/login';

public $username_selector = 'input#email';
public $password_selector = 'input#password';
public $submit_login_selector = 'button[name="action"], button#anmeldung, button#login';

public $check_login_failed_selector = 'div.alert span';
public $check_login_success_selector = 'ul[class="navbar-nav nav-right"] > li[class=logout-mobile], .headerNav a[href*="/logout"], #top_navigation a[href*="/logout"], .user-menu a[href*="/logout"]';

public $isNoInvoice = true;

public $last_state = array();
public $current_state = array();

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    
    if($this->exts->docker_restart_counter == 0) {
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
    } else {
        $this->last_state = $this->current_state;
    }
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->capture('1-init-page');
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(20);
        $this->checkFillLogin();
        $this->exts->waitTillPresent($this->check_login_success_selector, 30);
    }
    
    if(strpos($this->exts->getUrl(), "login/2fa/intro") !== false){
        $this->exts->moveToElementAndClick('button[label="NoThanks"]');
        sleep(10);
    }
    
    if($this->exts->exists('div[id*="code-wrapper"] input[type*="number"]')){
        $this->exts->log("2 FA");
        $this->checkFillTwoFactor();
        sleep(10);
    }
    if ($this->exts->getElement('button.btn-link') != null) {
        $this->exts->moveToElementAndClick('button.btn-link');
        sleep(5);
        if($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__.'::User logged in');
            $this->exts->capture("3-login-success");
            
            // If portal script supports restart docker and resume portal execution
            // Enable this only after successfull login, otherwise no need to process restart
            $this->support_restart = true;
            
            $this->doAfterLogin();
        } else {
            $this->exts->log(__FUNCTION__.'::Use login failed');
            if($this->exts->getElement($this->check_login_failed_selector) != null && (stripos($this->exts->extract($this->check_login_failed_selector), 'passwor') !== false ||stripos($this->exts->extract($this->check_login_failed_selector), 'kennwort') !== false)) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } else {
        if($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__.'::User logged in');
            $this->exts->capture("3-login-success");
            
            // If portal script supports restart docker and resume portal execution
            // Enable this only after successfull login, otherwise no need to process restart
            $this->support_restart = true;
            
            $this->doAfterLogin();
        } else {
            $this->exts->log(__FUNCTION__.'::Use login failed');
            if((stripos($this->getInnerTextByJS($this->check_login_failed_selector), 'passwor') !== false 
                || stripos($this->getInnerTextByJS($this->check_login_failed_selector), 'kennwort') !== false)) {
                $this->exts->loginFailure(1);
            } else if((stripos($this->exts->extract('.row-login-form .alert.alert-warning'), 'nden das Passwort') !== false
                && stripos($this->exts->extract('.row-login-form h1'), 'Kennwort zur') !== false)
            || (stripos($this->exts->extract('.row-login-form .alert.alert-warning'), 'please reset the password') !== false
                && stripos($this->exts->extract('.row-login-form h1'), 'Reset password') !== false)){
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}
private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(7);
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
    sleep(10);
    $this->exts->capture("after-clear");
}
private function checkFillLogin() {
    $this->exts->waitTillPresent($this->username_selector);

    if($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);
        $this->checkFillRecaptcha();
        $this->exts->moveToElementAndClick('button#login');
       
        $this->exts->waitTillPresent($this->password_selector, 5);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(4);
        $this->checkFillRecaptcha();

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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
            $total_recTxt = count($recaptcha_textareas);
            for ($i=0; $i < $total_recTxt; $i++) {
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

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            ');
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
}
private function checkFillTwoFactor() {
    if($this->exts->getElement('div[id*="code-wrapper"] input[type*="number"]') != null){
        $this->exts->log("Current URL - ".$this->exts->getUrl());
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement('small[class*="text-light"]') != null){
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('small[class*="text-light"]')->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = $this->exts->fetchTwoFactorCode();
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->getElements('div[id*="code-wrapper"] input[type*="number"]');
            // foreach ($code_inputs as $key => $code_input) {
            // 	if(array_key_exists($key, $resultCodes)){
            // 		$this->exts->log('"checkFillTwoFactor: Entering key '. $resultCodes[$key] . 'to input #');
            // 		$code_input->sendKeys($resultCodes[$key]);
            // 	} else {
            // 		$this->exts->log('"checkFillTwoFactor: Have no char for input #');
            // 	}
            // }
            $this->exts->click_by_xdotool('div[id*="code-wrapper"] input[type*="number"]:first-child');
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(10);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            sleep(15);
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else {
        $two_factor_selector = 'input#otp';
        $two_factor_message_selector = '#code-wrapper div.description';
        $two_factor_submit_selector = 'button#login';

        if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if($this->exts->getElement($two_factor_message_selector) != null){
                $this->exts->two_factor_notif_msg_en = "";
                for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
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
        }
    }
}


private function doAfterLogin() {
    $this->exts->openUrl('https://ui.awin.com/user');
    sleep(20);
    $this->exts->capture('3-users-page');
    
    if(empty($this->last_state['stage']) || $this->last_state['stage'] == 'INVOICE') {
        // keep current state of processing
        $this->current_state['stage'] = 'INVOICE';
        $this->last_state['stage'] = '';
    }
    $paths = explode('/', $this->exts->getUrl());
    $currentDomainUrl = $paths[0].'//'.$paths[2];
    // Check and loop through all account
    $accounts = $this->exts->getElementsAttribute('#accountsOverview .list-group li a.profile', 'href');
    $this->exts->log(__FUNCTION__.'::ACCOUNTS FOUND: ' . count($accounts));
    foreach ($accounts as $key => $account_url) {
        $tempArr = explode('/', trim($account_url, '/'));
        $account_number = end($tempArr);
        $tempArr = explode('?', $account_number);
        $account_number = $tempArr[0];
        if($this->exts->docker_restart_counter > 0 && !empty($this->last_state['accounts']) && in_array($account_number, $this->last_state['accounts'])) {
            $this->exts->log("Restart: Already processed earlier - Account-value  ".$account_number);
            continue;
        }
        $this->exts->log(__FUNCTION__.'::PROCCESSING ACCOUNT: '.$account_number);
        if(strpos($account_url, $currentDomainUrl) === false){
            $account_url = $currentDomainUrl . $account_url;
        }
        $this->exts->openUrl($account_url);
        sleep(3);
        if($this->exts->urlContainsAny(["/awin/", 'awin.'])){
            $this->processAwinInvoices($account_number);
        } else {
            $this->processZanoxInvoices($account_number);
        }
        
        // Keep completely processed account key
        $this->current_state['accounts'][] = $account_number;
    }
    $this->last_state['accounts'] = array();
    
    // Final, check no invoice
    if($this->isNoInvoice){
        $this->exts->no_invoice();
    }
    
    $this->exts->success();
}
private function processZanoxInvoices($account_number='') {
    $this->exts->log(__FUNCTION__.'::'.$this->exts->getUrl());
    sleep(5);
    // Click "Setting" on top-right menu > Click "Payment"
    $this->exts->moveToElementAndClick('#settingsCog li a[href*="dest=accountInformation"]');
    // Click "Credit Notes" tab
    $this->exts->moveToElementAndClick('form[name="form_money_account"] table table tr td:last-child  a.prmreiter_nonactiv');
    $this->exts->log(__FUNCTION__.'::Waiting for invoice...');
    sleep(25);
    
    $this->exts->capture("4-zanox-invoices-page");
    $invoices = [];
    
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $rows = $this->exts->getElements('form[name="form_money_account"] table > tbody > tr');
    foreach ($rows as $row) {
        if($this->exts->getElements('tr', $row) == null){
            $tags = $this->exts->getElements('td', $row);
            if(count($tags) >= 7 && $this->exts->getElement('a', $tags[6]) != null) {
                $invoiceUrl = $this->exts->getElement('a', $tags[6])->getAttribute("href");
                $tempArr = explode('?', $invoiceUrl);
                $tempArr = explode('_',end($tempArr));
                $invoiceName = end($tempArr);
                // invoice url maybe pdf url, invoice name is difference in this case.
                $tempArr1 = explode('id=', $invoiceName);
                $invoiceName = end($tempArr1);
                // remove all special symbol
                $invoiceName = preg_replace("/[^\d\w]/", '', $invoiceName);
                $invoiceName = $account_number . "_" . $invoiceName;
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' ' . trim($tags[2]->getAttribute('innerText'));
                
                array_push($invoices, array(
                    'invoiceName'=>$invoiceName,
                    'invoiceDate'=>$invoiceDate,
                    'invoiceAmount'=>$invoiceAmount,
                    'invoiceUrl'=>$invoiceUrl
                ));
                
                //Stop collection if invoice is older then 90days and restrictpages is not zero
                if((int)@$restrictPages > 0) {
                    $invoice_date = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
                    $timeDiff = strtotime("now") - strtotime($invoice_date);
                    $diffDays = ceil($timeDiff / (3600 * 24));
                    $this->exts->log("diffDays - ".$diffDays);
                    if($diffDays > 90) {
                        $this->exts->log("Skipped download if it is older then 90days");
                        break;
                    }
                }
            }
        }
    }
    
    // Download all invoices
    $this->exts->execute_javascript('JSON.stringify = function(t){return "";}');
    $this->exts->log(__FUNCTION__.'::Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        if(stripos($invoice['invoiceUrl'], '/download') !== false){
            $this->isNoInvoice = false;
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            }
        } else {
            $this->exts->log(__FUNCTION__.'::No download URL found '.$invoiceFileName);
        }
        
    }
}
private function processAwinInvoices($account_number='') {
    $this->exts->log(__FUNCTION__.'::'.$this->exts->getUrl());
    sleep(5);
    // Click "Report" on top menu > Click "History"
    $this->exts->moveToElementAndClick('li a#paymentHistory');
    sleep(5);
    $this->exts->capture("4-awin-invoices-page");
    
    // Loop through all currency tab and get invoices
    do {
        // Collect and download invoice, handle next page if restrictPages == 0
        $currency_text = trim($this->exts->extract('#payments ul.nav-tabs li.selected a'));
        $this->exts->log('Current Tab: '.$currency_text);
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0].'//'.$paths[2];
        do {
            $rows_count = count($this->exts->getElements('table > tbody > tr'));
            for ($i=0; $i < $rows_count; $i++) { 
                $row = $this->exts->getElements('table > tbody > tr')[$i];
                $direct_download_link = $this->exts->getElement('a[href*="/payments/"][href*="/download"]', $row);
                $detail_link = $this->exts->getElement('a[href*="/paymentId/"], a[href*="/payments/"]', $row);
                if($direct_download_link != null) {
                    // HUY added this 202205 since this site provided download button on record row.
                    $this->isNoInvoice = false;
                    $invoiceUrl = $direct_download_link->getAttribute("href");
                    $tempArr = explode('/payments/', $invoiceUrl);
                    $invoiceName = end($tempArr);
                    $tempArr = explode('/', $invoiceName);
                    $invoiceName = $tempArr[0];
                    
                    $invoiceName = $account_number . "_" . $invoiceName;
                    $invoiceFileName = $invoiceName.'.pdf';
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: '.$invoiceName);
                    if(strpos($invoiceUrl, $currentDomainUrl) === false){
                        $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                    }
                    $this->exts->log('invoiceUrl: '.$invoiceUrl);

                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                    if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                    }
                } else if($detail_link != null) {
                    $invoiceUrl = $detail_link->getAttribute("href");
                    if (strpos($invoiceUrl, 'paymentId') !== false) {
                        $tempArr = explode('/paymentId/', $invoiceUrl);
                        $tempArr = explode('/',end($tempArr));
                        $invoiceName = end($tempArr);
                    } else {
                        $tempArr = explode('/payments/', $invoiceUrl);
                        $invoiceName = end($tempArr);
                        $tempArr = explode('/', $invoiceName);
                        $invoiceName = $tempArr[0];
                    }
                    if(strpos($invoicePageUrl, $currentDomainUrl) === false){
                        $invoicePageUrl = $currentDomainUrl . $invoicePageUrl;
                    }
                    
                    $invoiceName = $account_number . "_" . $invoiceName;
                    if(!$this->exts->invoice_exists($invoiceName)) {
                        $invoiceDate = trim($this->getInnerTextByJS($this->exts->getElement('td:nth-child(1)', $row)));
                        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($this->exts->getElement('td:nth-child(1)', $row))) . ' ' . $currency_text;
                        
                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: '.$invoiceName);
                        $this->exts->log('invoiceDate: '.$invoiceDate);
                        $this->exts->log('invoiceAmount: '.$invoiceAmount);
                        if(strpos($invoiceUrl, $currentDomainUrl) === false){
                            $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                        }
                        $this->exts->log('invoiceUrl: '.$invoiceUrl);
                        
                        $invoiceFileName = $invoiceName.'.pdf';
                        $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
                        $this->exts->log('Date parsed: '.$invoiceDate);
                        
                        // Open New window To process Invoice
                        // $this->exts->open_new_window();
                        
                        // // Call Processing function to process current page invoices
                        // $this->exts->openUrl($invoiceUrl);
                        $this->exts->openNewTab($invoiceUrl, false, true);
                        sleep(8);
                        
                        if(stripos($this->exts->getUrl(), "login") !== false || $this->exts->getElements("div.row-login-form form input[name=\"email\"]") != null  || $this->exts->getElements("div.row-login-form form input[name=\"password\"]") != null) {
                            $this->checkFillLogin();
                            $this->checkFillRecaptcha();
                            sleep(4);
                            if($this->exts->getElement($this->check_login_success_selector) != null) {
                                $this->exts->openUrl($invoiceUrl);
                                sleep(2);
                            }
                        }

                        if($this->exts->exists('a[href*="/download"]')) {
                            $invoiceUrl = $this->exts->getElement('a[href*="/download"]')->getAttribute("href");
                            if(strpos($invoiceUrl, $currentDomainUrl) === false){
                                $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                            }
                            $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                            
                            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                            } else {
                                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                            }
                            $this->isNoInvoice = false;
                        } else if($this->exts->exists("a#printInvoice")) {
                            $invoiceUrl = $this->exts->getElement("a#printInvoice")->getAttribute("href");
                            if(strpos($invoiceUrl, $currentDomainUrl) === false){
                                $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                            }
                            $downloaded_file = $this->exts->download_capture($invoiceUrl, $invoiceFileName, 1);
                            
                            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            } else {
                                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                            }
                            $this->isNoInvoice = false;
                        } else {
                            $this->exts->log(__FUNCTION__.'::No print icon found');
                        }
                        // Close new window
                        // $this->exts->close_new_window();
                        $this->exts->switchToInitTab();
                        $this->exts->closeAllTabsButThis();
                        sleep(1);
                    } else {
                        $this->exts->log('Invoice already exist - '.$invoiceName);
                    }
                }
            }
            
            
            // Check if have next page and restrictPage == 0, click next
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if($this->exts->exists('#paymentHistory .pagination li#nextPage a') && $restrictPages == 0){
                $this->exts->log(__FUNCTION__.'::Click next page');
                isset($page_count) ? $page_count++ : $page_count = 1;
                $have_next_page = true;
                $this->exts->moveToElementAndClick('#paymentHistory .pagination li#nextPage a');
                sleep(5);
            } else {
                $have_next_page = false;
            }
        } while ($have_next_page && $page_count < 30);
        
        // Check if have other currency tab, Click to move next tab and collect invoice
        if($this->exts->exists('#payments ul.nav-tabs li.selected + li a[href*="paymentRegion="]')){
            isset($number_currency_tab) ? $number_currency_tab++ : $number_currency_tab = 1;
            $have_more_currency = true;
            $this->exts->moveToElementAndClick('#payments ul.nav-tabs li.selected + li a[href*="paymentRegion="]');
            sleep(5);
        } else {
            $have_more_currency = false;
        }
    } while ($have_more_currency && $number_currency_tab < 5);
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