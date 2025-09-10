<?php
// Server-Portal-ID: 51030 - Last modified: 31.10.2024 12:09:01 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.klaviyo.com';
public $invoicePageUrl = 'https://www.klaviyo.com/account';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = '.form-container form#login-form [type="submit"]';

public $check_login_failed_selector = '#errorMsg';
public $check_login_success_selector = 'a[href*="/logout"], .nav-primary a[href="/dashboard"], div[class*="UserDisplay"]';
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl('https://www.klaviyo.com/login');
        sleep(15);
        if(stripos($this->exts->extract('h2[class*="Heading__GeneratedStyledHeading"]'),'headerText') !== false){
            $this->exts->openUrl('https://www.klaviyo.com/login');
            sleep(15);
        }
        $this->checkFillLogin();
        sleep(15);
        
        $this->checkFillTwoFactor();

        if ($this->exts->urlContains('account-setup')) {
            $this->exts->moveToElementAndClick('button[class*="AccountDropdown"]');
            sleep(2);
            if (count($this->exts->getElements('button[class*="AccountOption"]')) > 1)  {
                $this->exts->click_element($this->exts->getElements('button[class*="AccountOption"]')[1]);
                sleep(15);
            }else{
                $this->exts->account_not_ready();
            }
        }
    }
    
    // then check user logged in or not
    if($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        //Process current account
        $this->exts->openUrl('https://www.klaviyo.com/account#payment-history-tab');
        $this->processInvoices();

        //Process other accounts
        $this->handleMultipleAccount();
        
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        $this->exts->log($this->exts->getUrl());
        if ($this->exts->exists('ul.errorlist li') 
            && (strpos($this->exts->extract('ul.errorlist'), 'Your username and password ') !== false
                || strpos($this->exts->extract('ul.errorlist'), 'Ihr Benutzername und Ihr Passwort stimmen nicht') !== false)) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)){
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else if(stripos(strtolower($this->exts->extract('div.container-main')), 'two-step authentication is required') !== false && $this->exts->getElement('button#mfa_configure_button') != null) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin() {
    if($this->exts->exists($this->password_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $recaptcha_token = $this->get_recaptcha_token();
        if(empty($recaptcha_token)){
            $recaptcha_token = $this->get_recaptcha_token();
        }
        // We can not embed token to the login request, so we fake login by javascript.
        $base64_username = base64_encode($this->username);
        $base64_password = base64_encode($this->password);
        $base64_recaptcha_token = base64_encode($recaptcha_token);

        $login_response = $this->exts->execute_javascript('
            var username = atob("'.$base64_username.'");
            var password = atob("'.$base64_password .'");
            var recaptcha_token = atob("'.$base64_recaptcha_token.'");
            var form_data = new FormData();
            form_data.append("email", username);
            form_data.append("password", password);
            form_data.append("g-recaptcha-response", recaptcha_token);
            var csrf_token = document.cookie.split("kl_csrftoken=").pop().split(";")[0];
            // Send login request
            var xhr = new XMLHttpRequest();
            var csrf_token = document.cookie.split("kl_csrftoken=").pop().split(";")[0];
            xhr.open("POST", "https://www.klaviyo.com/ajax/login", false);
            xhr.setRequestHeader("X-Csrftoken", csrf_token);
            xhr.send(form_data);

            var response_data = JSON.parse(xhr.response);
            if(JSON.stringify(response_data).indexOf("authentication_error") > -1){
                "authentication_error"
                var para = document.createElement("div");
                var node = document.createTextNode(response_data.__all__[0].message);
                para.appendChild(node);
                para.style["background-color"] = "yellow";
                para.style["color"] = "red";
                var form = document.querySelector("form");
                form.insertBefore(para, form.firstChild);
                return "authentication_error";
            } else if(JSON.stringify(response_data).indexOf("bad_captcha") > -1){
                return "captcha";
            } else if(JSON.stringify(response_data).indexOf("redirect_url") > -1){
                return origin + response_data.data.redirect_url;
            }
            return "";
        ');
        $this->exts->log($login_response);
        if (!empty($login_response) && filter_var($login_response, FILTER_VALIDATE_URL) !== FALSE) {
            $this->exts->log("Login request OK, redirect to " . $login_response);
            $this->exts->openUrl($login_response);
        } else if($login_response == 'authentication_error'){
            $this->exts->loginFailure(1);
        }

    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor() {
    $two_factor_selector = 'input[name="mfa_code"], input[name="verification_code"],input[data-testid="verification-code"]';
    $two_factor_message_selector = 'p.subtitle, [class*="Tooltipstyles"] p';
    $two_factor_submit_selector = 'button[type="submit"].btn-primary, button[type="submit"].submit-button, button[title="Log in"]';
    
    if($this->exts->getElement($two_factor_selector) != null){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            $total_message_selectors = count($this->exts->getElements($two_factor_message_selector));
            for ($i=0; $i < $total_message_selectors; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        // clear input
        $this->exts->click_by_xdotool($two_factor_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");

        $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code. ".$two_factor_code);
            // $this->exts->moveToElementAndClick($two_factor_selector);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->type_key_by_xdotool("Tab");
            
            if($this->exts->exists('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)')) {
                $this->exts->moveToElementAndClick('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)');
            }
            // sleep(1);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);
            
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
private function get_recaptcha_token() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"], iframe[src*="/recaptcha/enterprise/anchor"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $temp_array = explode("&k=", $iframeUrl);
        $data_siteKey = end($temp_array);
        $temp_array = explode('&', $data_siteKey);
        $data_siteKey = reset($temp_array);
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        
        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            return $this->exts->recaptcha_answer;
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
    return "";
}

private function handleMultipleAccount(){
    $this->exts->moveToElementAndClick('button#account-switcher-toggle');
    sleep(3);
    if ($this->exts->exists('button[class*="FilterableAccountList__ShowAllButton"]')) {
        $this->exts->moveToElementAndClick('button[class*="FilterableAccountList__ShowAllButton"]');
        sleep(5);
        $numberOfAccount = count($this->exts->getElements('div#account-filter-list-body div[role="option"]'));
        for ($i=2; $i <= $numberOfAccount + 1; $i++) { 
            $this->exts->moveToElementAndClick('div#account-filter-list-body div[role="option"]:nth-child(' . $i . ')');
            sleep(15);
            $this->exts->openUrl('https://www.klaviyo.com/account#payment-history-tab');
            $this->processInvoices();
            $this->exts->moveToElementAndClick('button#account-switcher-toggle');
            sleep(3);
            $this->exts->moveToElementAndClick('button[class*="FilterableAccountList__ShowAllButton"]');
            sleep(5);
        }
    }else{
        $numberOfAccount = count($this->exts->getElements('button[class*="StaticAccountList__AccountButton"]'));
        for ($i=2; $i <= $numberOfAccount + 1; $i++) { 
            $this->exts->moveToElementAndClick('button[class*="StaticAccountList__AccountButton"]:nth-child(' . $i . ')');
            sleep(15);
            $this->exts->openUrl('https://www.klaviyo.com/account#payment-history-tab');
            $this->processInvoices();
            $this->exts->moveToElementAndClick('button#account-switcher-toggle');
            sleep(3);
        }
    }
}
private function processInvoices($paging_count=1) {
    sleep(25);
    $this->exts->capture("4-invoices-page");
    
    $rows = $this->exts->getElements('table > tbody > tr');
    foreach ($rows as $index=>$row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 4 && $this->exts->getElement('td.DataTable-actionsCell button', $row) != null) {
            $invoiceSelector = $this->exts->getElement('td.DataTable-actionsCell button', $row);
            $this->exts->webdriver->executeScript("arguments[0].setAttribute('id', 'custom-pdf-button-".$index."');", [$invoiceSelector]);

            $invoiceDate = '';
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getText())) . ' USD';

            $this->isNoInvoice = false;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: '.$invoiceDate);
            $this->exts->log('invoiceAmount: '.$invoiceAmount);

            // click and download invoice
            $this->exts->moveToElementAndClick('button#custom-pdf-button-'.$index);
            sleep(1);
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
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                }
            } else {
                $this->exts->log('Timeout when download '.$invoiceFileName);
            }
        }
    }

    
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if($restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->getElement('span[data-next="true"]:not([data-disabled="true"])') != null
    ){
        $paging_count++;
        $this->exts->moveToElementAndClick('span[data-next="true"]:not([data-disabled="true"])');
        sleep(5);
        $this->processInvoices($paging_count);
    }
}
