<?php
// Server-Portal-ID: 7820 - Last modified: 29.01.2025 14:23:19 UTC - User: 1

public $baseUrl = 'https://account.jetbrains.com/licenses';
public $loginUrl = 'https://account.jetbrains.com/licenses';
public $invoicePageUrl = 'https://account.jetbrains.com/licenses';

public $username_selector = 'form.js-auth-dialog-form input[name="username"], form input[name="email"]';
public $password_selector = 'form.js-auth-dialog-form input[name="password"], form input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button.login-submit-btn, form button[type="submit"]';

public $check_login_failed_selector = '.js-auth-dialog-div-errors, #server-error';
public $check_login_success_selector = 'a[href*="/logout"]';

public $isNoInvoice = true; 
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);       
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if(!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(5);
        $this->checkFillTwoFactor();
        sleep(5);
    }

    if($this->exts->exists($this->check_login_success_selector)) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        $this->doAfterLogin();
        
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed url: '.$this->exts->getUrl());
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'),'password is incorrect') !== false){
            $this->exts->log(trim($this->exts->extract($this->check_login_failed_selector)));
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin() {
    sleep(3);
    $this->exts->log(__FUNCTION__);
    $this->exts->capture(__FUNCTION__);
    // Continue with email
    $this->exts->moveToElementAndClick('//button[.//span[contains(text(),"with email")]]');
    sleep(2);
    if($this->exts->exists($this->username_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->checkFillRecaptcha();
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);
        $this->exts->capture("2-login-page-submitted");
        $this->checkFillRecaptcha();
        $this->exts->capture("2-login-page-submitted-1");
        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor() {
    $two_factor_selector = 'input[name="code"],input[name="secondFactor"]';
    $two_factor_message_selector = 'span[class*="rs-text_hardness_hard"],div.js-auth-dialog-input-2fa p';
    $two_factor_submit_selector = 'button[data-test="button"][type="submit"],button.login-submit-btn';

    if($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3){
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
        $this->exts->two_factor_notif_msg_en = "JetBrains Two-factor authentication - Code\n".$this->exts->two_factor_notif_msg_en;
        $this->exts->two_factor_notif_msg_de = "JetBrains 2-Faktor-Authentifizierung - Code\n".$this->exts->two_factor_notif_msg_de;

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if(!$this->exts->exists($two_factor_selector)){
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

private function doAfterLogin() {
    // download licenses invoice, then collect organizations and download invoice form each organization 
    $this->exts->openUrl('https://account.jetbrains.com/licenses/transactions');
    sleep(5);
    $this->processInvoices();

    sleep(10);
    $this->exts->capture('2.1-organization');
    $organizations = $this->exts->getElementsAttribute('.list-group-item a[href*="/organization/"]', 'href');
    $this->exts->log('ORGANIZATIONS found count: ' . count($organizations));
    foreach ($organizations as $key => $organization) {
        $organization_url = $organization.'/transactions';
        $this->exts->log('SWTICH to organization: ' .$organization_url);
        $this->exts->openUrl($organization_url);
        sleep(5);
        $this->processInvoices();
    }
}
private function processInvoices() {
    sleep(10);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('table > tbody > tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 6 && $this->exts->getElement('a[href*="pdf"]', $tags[5]) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="pdf"]', $tags[5])->getAttribute("href");
            $invoiceName = trim($tags[0]->getAttribute('innerText'));
            $invoiceDate = trim($tags[1]->getAttribute('innerText'));
            $invoiceAmount = '';

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

        $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M j, Y','Y-m-d', 'en') ;
        $this->exts->log('Date parsed: '.$parsed_date);
        
        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $downloaded_file);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
}