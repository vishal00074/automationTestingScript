<?php
// Server-Portal-ID: 6452 - Last modified: 27.01.2025 13:52:09 UTC - User: 1

public $baseUrl = 'https://app.hubspot.com';
public $loginUrl = 'https://app.hubspot.com/login';
public $invoicePageUrl = '';

public $username_selector = 'input#username';
public $password_selector = 'input#password, input#current-password';
public $remember_me_selector = 'div[data-test-id="remember-me"] input[type="checkbox"], form#hs-login [data-key="login.form.remember"]';
public $submit_login_btn = 'button#loginBtn';

public $checkLoginFailedSelector = 'form#hs-login .alert-danger h2, div[data-error-type="INVALID_USER"], div[data-error-type="INVALID_PASSWORD"], small#username-validationMessage, div[data-error-type="LOGIN_UNAVAILABLE"]';
public $checkLoggedinSelector = 'a#signout, .account-picker a, [data-portal-hook="account-menu"]';
public $isNoInvoice = true;

public $account_number = '';
public $login_with_google = '0';
public $login_with_microsoft = '0';

private function initPortal($count){
    $this->exts->log('Begin initPortal ' . $count);
    $this->account_number = isset($this->exts->config_array["account_number"]) ? $this->exts->config_array["account_number"] : '';
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : 0;
    $this->login_with_microsoft = isset($this->exts->config_array["login_with_microsoft"]) ? (int)@$this->exts->config_array["login_with_microsoft"] : 0;
    $this->security_phone_number = isset($this->exts->config_array["security_phone_number"]) ? trim($this->exts->config_array["security_phone_number"]) : '';
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(3);

    // after load cookies and open base url, check if user logged in
    $this->exts->openUrl($this->baseUrl);
    // Wait for selector that make sure user logged in
    sleep(15);

    for ($i = 0; $i < 10 && $this->exts->exists('#cf-error-details header h2') && strpos($this->exts->extract('#cf-error-details header h2'), 'DNS points to prohibited IP') !== false; $i++) {
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
    }

    if($this->exts->getElement($this->checkLoggedinSelector) == null) {
        if ((int)@$this->login_with_google == 1) {
            $this->exts->moveToElementAndType('form input#username', $this->username);
            sleep(2);
            $this->exts->moveToElementAndClick('button#loginBtn');
            sleep(5);
            $this->exts->moveToElementAndClick('button[data-test-id="google-sign-in"]');
            sleep(15);
            $this->loginGoogleIfRequired();
            sleep(5);
        } elseif ((int)@$this->login_with_microsoft == 1) {
            $this->exts->moveToElementAndType('form input#username', $this->username);
            sleep(2);
            $this->exts->moveToElementAndClick('button#loginBtn');
            sleep(5);
            $this->exts->moveToElementAndClick('button[data-test-id="microsoft-sign-in"]');
            sleep(15);
            $this->loginMicrosoftIfRequired();
            sleep(5);
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');

            $this->exts->openUrl($this->loginUrl);
            // sleep(15);
            // // sometimes hCaptcha (cloudflare) conflicts with itself => reload to request another hcatpcha URL
            // for ($retry = 0; $retry < 10 && $this->exts->exists('#cf-error-details header h2') && strpos($this->exts->extract('#cf-error-details header h2'), 'DNS points to prohibited IP') !== false; $retry++) {
            //     $this->exts->capture('cloudflare-conflict-url');
            //     $this->exts->openUrl($this->baseUrl);
            //     sleep(20);
            // }
            // $this->exts->moveToElementAndClick('button#hs-eu-confirmation-button');
            sleep(5);


            $this->checkFillLogin();
        }
    }

    if ($this->exts->exists('div[data-test-id="change-homepage-modal"] div[data-action="close"]')) {
        $this->exts->moveToElementAndClick('div[data-test-id="change-homepage-modal"] div[data-action="close"]');
        sleep(5);
    }

    if($this->exts->getElement($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        $accounts_array = array();
        if ($this->exts->exists('.account-picker a[href*="Id="]')) {
            $url_accounts =  $this->exts->getElements('.account-picker a[href*="Id="]');
            foreach ($url_accounts as $key => $url_account) {
                $acc_url = $url_account->getAttribute("href");
                $acc_id = trim(explode('&', end(explode('Id=', $acc_url)))[0]);
                $acc = array(
                    'acc_url' => $acc_url,
                    'acc_id' => $acc_id
                );

                array_push($accounts_array, $acc);
            }
            $this->exts->moveToElementAndClick('.account-picker a[href*="Id="]'); // click account
            sleep(10);
        }
        // get all accounts
        $accounts = $this->exts->execute_javascript('
            (function() {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "https://api.hubspot.com/accounts/v1/accounts/", false);
                    xhr.withCredentials = true;
                    xhr.send();
                    return JSON.parse(xhr.responseText).accounts;
                } catch(ex) {
                    return [];
                }
            })();
        ');
        $this->exts->log('ACCOUNTS FOUND: ' .count($accounts));
        
        if (count($accounts) == 0) {
            if ($this->exts->exists("div.private-error-msg__body")) {
                $msg = $this->exts->extract("div.private-error-msg__body");
                $this->exts->log("Unknown error ? " . $msg);
                if ($msg != null && trim($msg) != "" && stripos($msg, "no accounts")) {
                    $this->exts->account_not_ready();
                } else {
                    $this->exts->loginFailure();
                }
            } else if (count($accounts_array) > 0) {
                foreach ($accounts_array as $account) {
                    $this->exts->log('PROCESSING ACCOUNT: ' . $account["acc_id"]);
                    $this->exts->openUrl($account["acc_url"]);
                    sleep(15);
                    if ($this->exts->exists('div[data-action="close"]')) {
                        $this->exts->moveToElementAndClick('div[data-action="close"]');
                        sleep(2);
                    }
                    $this->exts->moveToElementAndClick('button#hs-global-toolbar-accounts');
                    sleep(5);
                    $this->exts->moveToElementAndClick('a#accountsAndBilling');
                    sleep(15);
                    $this->exts->moveToElementAndClick('a[href*="/transactions"]');
                    sleep(5);
                    $this->processInvoices();
                }
            } else if ($this->exts->exists('button#hs-global-toolbar-accounts')) {
                if ($this->exts->exists('div[data-action="close"]')) {
                    $this->exts->moveToElementAndClick('div[data-action="close"]');
                    sleep(2);
                }
                $this->exts->moveToElementAndClick('button#hs-global-toolbar-accounts');
                sleep(5);
                $this->exts->moveToElementAndClick('a#accountsAndBilling');
                sleep(15);
                $this->exts->moveToElementAndClick('a[href*="/transactions"]');
                sleep(5);
                $this->processInvoices();
            } else {
                $this->exts->loginFailure();
            }
        } else {
            $selected_accounts = explode(',', $this->account_number);
            array_walk($selected_accounts, function (&$element) {
                $element = trim($element);
            });
            print_r($selected_accounts);
            foreach ($accounts as $account) {
                if (in_array($account["id"], $selected_accounts) || trim($this->account_number) == '') {
                    $this->exts->log('PROCESSING ACCOUNT: ' . $account["id"]);
                    $this->invoicePageUrl = 'https://app.hubspot.com/account-and-billing/' . $account["id"] . '/transactions';
                    // Open invoices url
                    $this->exts->openUrl($this->invoicePageUrl);
                    $this->processInvoices();
                }
            }
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        $this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
        $this->exts->capture("LoginFailed");

        if ($this->exts->getElement($this->checkLoginFailedSelector) != null) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement('.private-error-msg__title') != null) {
            $this->exts->account_not_ready();
        } else if ((stripos($this->exts->extract('[data-key="login.requestReset.idleUser.instructions"]'), 'einige Zeit inaktiv sind') !== false
                || stripos($this->exts->extract('[data-key="login.requestReset.idleUser.instructions"]'), 'inactive for some time') !== false)
            && $this->exts->exists('button#reset-button')
        ) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
        
    }
}

private function checkFillLogin($count = 1){
    sleep(5);
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(4); 
        
        if ($this->remember_me_selector != ''){
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }
            
        sleep(2);
        // 
        $this->exts->moveToElementAndClick('div[role="button"]');
        sleep(10);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(10);

        // if ($this->remember_me_selector != '')
        //     $this->exts->moveToElementAndClick($this->remember_me_selector);
        // sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
        $this->checkFillTwoFactor();
        sleep(10);

        $this->checkFillTwoFactor();
        sleep(10);
    } else if($this->exts->getElement($this->username_selector) != null){
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(4); 
        
        if ($this->remember_me_selector != ''){
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        }
            
        sleep(2);
        // 
        $this->exts->moveToElementAndClick('div[role="button"]');
        sleep(10);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(10);

        // if ($this->remember_me_selector != '')
        //     $this->exts->moveToElementAndClick($this->remember_me_selector);
        // sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
        $this->checkFillTwoFactor();
        sleep(10);

    }  else {
        $this->exts->log("Login page not found");
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor(){
    $two_factor_selector = '';
    $two_factor_message_selector='';
    if ($this->exts->getElement('div.private-form__set input#code') != null) {
        $two_factor_selector = 'div.private-form__set input#code';
        $two_factor_message_selector = '[data-key="login.confirmToLogin.body"]';
        $two_factor_submit_selector = '[data-key="login.form.button"]';
    } else if ($this->exts->getElement('div.two-factor-auth input#code') != null) {
        $two_factor_selector = 'div.two-factor-auth input#code';
        $two_factor_message_selector = 'div.two-factor-auth div.two-factor-auth__header p';
        $two_factor_submit_selector = 'div.two-factor-auth button[type="submit"]';
    } else if ($this->exts->getElement('#hs-login input#code') != null) {
        $two_factor_selector = '#hs-login input#code';
        $two_factor_message_selector = '[class*="UIBox__Box"] h1';
        $two_factor_submit_selector = '#hs-login button[type="submit"]';
    } else if ($this->exts->getElement('[data-key="login.twoFactor.headerHubspotApp"]') != null) {
        $two_factor_message_selector = '[data-key="login.twoFactor.headerHubspotApp"]';
    }

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        $this->exts->notification_uid = '';

        if ($this->exts->getElement($two_factor_message_selector) != null) {
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
            
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->getElement('div.private-checkbox.remember-device input') != null) {
                $this->exts->moveToElementAndClick('div.private-checkbox.remember-device input');
                sleep(2);
            }
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            if ($this->exts->exists('button[data-2fa-rememberme="true"]')) {
                $this->exts->moveToElementAndClick('button[data-2fa-rememberme="true"]');
                sleep(15);
            }
            if ($this->exts->getElement($two_factor_selector) == null) {
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
    } else if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        // $two_factor_message_selector = 'form[action*="/2fa/email/"] > p';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");


        $this->exts->two_factor_notif_msg_en = "";
        for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
        }
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after Tap "Yes, it\'s me" on your mobile!';
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after Tap "Yes, it\'s me" on your mobile!';;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            sleep(15);
            if ($this->exts->exists('button[data-2fa-rememberme="true"]')) {
                $this->exts->moveToElementAndClick('button[data-2fa-rememberme="true"]');
                sleep(15);
            }
            if ($this->exts->getElement($two_factor_message_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                if ($this->exts->exists('[data-key="login.twoFactor.errors.hubspotAppDenied.button"]')) {
                    $this->exts->moveToElementAndClick('[data-key="login.twoFactor.errors.hubspotAppDenied.button"]');
                    sleep(3);
                }
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

// MICROSOFT Login
public $microsoft_username_selector = 'input[name="loginfmt"]';
public $microsoft_password_selector = 'input[name="passwd"]';
public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
public $microsoft_submit_login_selector = 'input[type="submit"]#idSIButton9';

public $microsoft_account_type = 0;
public $microsoft_phone_number = '';
public $microsoft_recovery_email = '';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function loginMicrosoftIfRequired() {
    $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
    $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
    $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

    if($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')){
        $this->checkFillMicrosoftLogin();
        sleep(10);
        $this->checkMicrosoftTwoFactorMethod();

        if($this->exts->exists('input#newPassword')){
            $this->exts->account_not_ready();
        } else if($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
            $this->exts->loginFailure(1);
        } 
    } else {
        $this->exts->log(__FUNCTION__.'::Not required microsoft login.');
        $this->exts->capture("3-no-microsoft-required");
    }
}
private function checkFillMicrosoftLogin() {
    $this->exts->log(__FUNCTION__);
    // When open login page, sometime it show previous logged user, select login with other user.
    if($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile')){
        $this->exts->moveToElementAndClick('[role="listbox"] .row #otherTile[role="option"], [role="listitem"] #otherTile');
        sleep(10);
    }

    $this->exts->capture("2-microsoft-login-page");
    if($this->exts->getElement($this->microsoft_username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter microsoft Username");
        $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
        sleep(10);
    }

    if($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')){
        // if site show: Already login with .. account, click logout and login with other account
        $this->exts->moveToElementAndClick('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
        sleep(10);
    }
    if($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')){
        // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
        //if account type is 1 then only personal account will be selected otherwise business account.
        if($this->microsoft_account_type == 1){
            $this->exts->moveToElementAndClick('#msaTile');
        } else {
            $this->exts->moveToElementAndClick('a#mso_account_tile_link, #aadTile');
        }
        sleep(10);
    }
    if($this->exts->exists('form #idA_PWD_SwitchToPassword')){
        $this->exts->moveToElementAndClick('form #idA_PWD_SwitchToPassword');
        sleep(5);
    }

    if($this->exts->getElement($this->microsoft_password_selector) != null) {
        $this->exts->log("Enter microsoft Password");
        $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
        sleep(1);
        $this->exts->moveToElementAndClick($this->microsoft_remember_me_selector);
        sleep(2);
        $this->exts->capture("2-microsoft-password-page-filled");
        $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
        sleep(10);
        $this->exts->capture("2-microsoft-after-submit-password");
    } else {
        $this->exts->log(__FUNCTION__.'::microsoft Password page not found');
    }

    $this->checkConfirmMicrosoftButton();
}
private function checkConfirmMicrosoftButton(){
    // After submit password, It have many button can be showed, check and click it
    if($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')){
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->moveToElementAndClick('form input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
        $this->exts->moveToElementAndClick("#verifySetup a#verifySetupCancel");
        sleep(10);
    }
    if($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
        $this->exts->moveToElementAndClick('#authenticatorIntro a#iCancel');
        sleep(10);
    }
    if($this->exts->getElement("input#iLooksGood") != null) {
        $this->exts->moveToElementAndClick("input#iLooksGood");
        sleep(10);
    }
    if($this->exts->getElement("input#StartAction") != null) {
        $this->exts->moveToElementAndClick("input#StartAction");
        sleep(10);
    }
    if($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
        $this->exts->moveToElementAndClick(".recoveryCancelPageContainer input#iLandingViewAction");
        sleep(10);
    }
    if($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
        $this->exts->moveToElementAndClick("input#idSubmit_ProofUp_Redirect");
        sleep(10);
    }
    if($this->exts->getElement('div input#iNext') != null) {
        $this->exts->moveToElementAndClick('div input#iNext');
        sleep(10);
    }
    if($this->exts->getElement('input[value="Continue"]') != null) {
        $this->exts->moveToElementAndClick('input[value="Continue"]');
        sleep(10);
    }
    if($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
        $this->exts->moveToElementAndClick('form[action="/kmsi"] input#idSIButton9');
        sleep(10);
    }
    if($this->exts->getElement('a#CancelLinkButton') != null) {
        $this->exts->moveToElementAndClick('a#CancelLinkButton');
        sleep(10);
    }
    if($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')){
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9');
        sleep(10);
    }
}
private function checkMicrosoftTwoFactorMethod() {
    // Currently we met 4 two factor methods
    // - Email 
    // - Text Message
    // - Approve request in Microsoft Authenticator app
    // - Use verification code from mobile app
    $this->exts->log(__FUNCTION__);
    sleep(5);
    $this->exts->capture("2.0-microsoft-two-factor-checking");
    // STEP 0 if it's hard to solve, so try back to choose list
    if($this->exts->exists('[value="PhoneAppNotification"]') && $this->exts->exists('a#signInAnotherWay')){
        $this->exts->moveToElementAndClick('a#signInAnotherWay');
        sleep(5);
    }
    // STEP 1: Check if list of two factor methods showed, select first
    if($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')){
        if($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])')){
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]:not([data-value*="Voice"])');
        } else {
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
        }
        sleep(3);
    } else if($this->exts->exists('#iProofList input[name="proof"]')){
        $this->exts->moveToElementAndClick('#iProofList input[name="proof"]');
        sleep(3);
    } else if($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')){
        // Updated 11-2020
        if($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')){
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
        } else if($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')){
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')){
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
        } else {
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"]');
        }
        sleep(5);
    }

    // STEP 2: (Optional)
    if($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')){
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $this->exts->two_factor_attempts = 2;
        $this->fillMicrosoftTwoFactor('', '', '', '');
    } else if($this->exts->exists('[data-bind*="Type.TOTPAuthenticatorV2"]')){
        // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
        // Then wait. If not success, click to select two factor by code from mobile app
        $input_selector = '';
        $message_selector = 'div#idDiv_SAOTCAS_Description';
        $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 2;
        $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        sleep(30);

        if($this->exts->exists('a#idA_SAASTO_TOTP')){
            $this->exts->moveToElementAndClick('a#idA_SAASTO_TOTP');
            sleep(5);
        }
    } else if($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[name^="iProof"] .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"]:not([type="hidden"])')){
        // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[name^="iProof"] .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
    }

    // STEP 3: input code
    if($this->exts->exists('input[name="otc"], input[name="iOttText"]')){
        $input_selector = 'input[name="otc"], input[name="iOttText"]';
        $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel';
        $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
        $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction';
        $this->exts->two_factor_attempts = 0;
        $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
    }
}
private function fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector) {
    $this->exts->log(__FUNCTION__);
    $this->exts->log("microsoft Two factor page found.");
    $this->exts->capture("2.1-microsoft-two-factor-page");
    $this->exts->log($message_selector);
    if($this->exts->getElement($message_selector) != null){
        $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
    $this->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(!empty($two_factor_code) && trim($two_factor_code) != '') {
        if($this->exts->getElement($input_selector) != null){
            $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            if($this->exts->exists($remember_selector)){
                $this->exts->moveToElementAndClick($remember_selector);
            }
            $this->exts->capture("2.2-microsoft-two-factor-filled-".$this->exts->two_factor_attempts);

            if($this->exts->exists($submit_selector)){
                $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            }
            sleep(15);

            if($this->exts->getElement($input_selector) == null){
                $this->exts->log("microsoftTwo factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            } else {
                $this->exts->log("microsoft Two factor can not solved");
            }
        } else {
            $this->exts->log("Not found microsoft two factor input");
        }
    } else {
        $this->exts->log("Not received microsoft two factor code");
    }
}
//*********** END Microsoft Login

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
private function loginGoogleIfRequired() {
    if($this->exts->urlContains('google.')){
        if($this->exts->urlContains('/webreauth')){
            $this->exts->moveToElementAndClick('#identifierNext');
            sleep(6);
        }
        $this->googleCheckFillLogin();
        sleep(5);
        if($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }
        if($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }

        // Click next if confirm form showed
        $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->googleCheckTwoFactorMethod();

        if($this->exts->exists('#smsauth-interstitial-remindbutton')){
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if($this->exts->exists('#tos_form input#accept')){
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')){
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')){
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')){
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')){
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')){
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if($this->exts->exists('#submit_approve_access')){
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if($this->exts->exists('form #approve_button[name="submit_true"]')){
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        $this->exts->capture("3-google-before-back-to-main-tab");
    } else {
        $this->exts->log(__FUNCTION__.'::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function googleCheckFillLogin() {
    if($this->exts->exists('form ul li [role="link"][data-identifier]')){
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)){
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }
    
    $this->exts->capture("2-google-login-page");
    if($this->exts->exists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
        } else if($this->exts->urlContains('/challenge/recaptcha')){
            $this->googlecheckFillRecaptcha();
            $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
            sleep(5);
        }

        // Which account do you want to use?
        if($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')){
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')){
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }
    
    if($this->exts->exists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if($this->exts->exists('#captchaimg[src]')){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }
        
        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)){
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if($this->exts->exists('#captchaimg[src]')){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)){
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-google-login-pageandcaptcha-filled");
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            }
        } else {
            $this->googlecheckFillRecaptcha();
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Google password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function googleCheckTwoFactorMethod() {
    // Currently we met many two factor methods
    // - Confirm email account for account recovery
    // - Confirm telephone number for account recovery
    // - Call to your assigned phone number
    // - confirm sms code
    // - Solve the notification has sent to smart phone
    // - Use security key usb
    // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
    $this->exts->log(__FUNCTION__);
    sleep(5);
    $this->exts->capture("2.0-before-check-two-factor-google");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')){
        $this->exts->moveToElementAndClick('#assistActionId');
        sleep(5);
    } else if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
        if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')){
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
        exec("sudo docker exec ".$node_name." bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('input[name="ootpPin"]')){
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }
    
    // STEP 1: Check if list of two factor methods showed, select first
    if($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')){
        // We most RECOMMEND confirm security phone or email, then other method
        if($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')){
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } elseif($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')){
            $this->exts->moveToElementAndClick('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
            sleep(5);
            if($this->exts->exists($this->google_password_selector)){
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if($this->exts->exists('#captchaimg[src]')){
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)){
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-google-login-pageandcaptcha-filled");
                    $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                }
                if($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                    $this->exts->loginFailure(1);
                }
                if($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                    $this->exts->loginFailure(1);
                }
            }
        } else if($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')){
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')){
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')){
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')){
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    }
    
    // STEP 2: (Optional)
    if($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')){
        // If methos is recovery email, send 2FA to ask for email
        $this->exts->two_factor_attempts = 2;
        $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')){
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(5);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('input#phoneNumberId')){
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')){
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }
    
    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if(count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0){
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }
    
    
    // STEP 4: input code
    if($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')){
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')){
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('input[name="Pin"]')){
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('input[name="secretQuestionResponse"]')){
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter=false) {
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Google two factor page found.");
    $this->exts->capture("2.1-two-factor-google");
    
    if($this->exts->querySelector($message_selector) != null){
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    
    $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(!empty($two_factor_code) && trim($two_factor_code) != '') {
        if($this->exts->querySelector($input_selector) != null){
            if(substr(trim($two_factor_code), 0, 2) === 'G-'){
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if(substr(trim($two_factor_code), 0, 2) === 'g-'){
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log(__FUNCTION__.": Entering two_factor_code: ".$two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(1);
            if($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])){
                $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                sleep(1);
            }
            $this->exts->capture("2.2-google-two-factor-filled-".$this->exts->two_factor_attempts);
            
            if($this->exts->exists($submit_selector)){
                $this->exts->log(__FUNCTION__.": Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            } else if($submit_by_enter){
                $this->exts->type_key_by_xdotool("Return");
            }
            sleep(10);
            $this->exts->capture("2.2-google-two-factor-submitted-".$this->exts->two_factor_attempts);
            if($this->exts->querySelector($input_selector) == null){
                $this->exts->log("Google two factor solved");
            } else {
                if($this->exts->two_factor_attempts < 3){
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Google Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Google not found two factor input");
        }
    } else {
        $this->exts->log("Google not received two factor code");
        $this->exts->two_factor_attempts = 3;
    }
}
private function googlecheckFillRecaptcha() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        $url = reset(explode('?', $this->exts->getUrl()));
        $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
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
                    document.querySelector("[data-callback]").getAttribute("data-callback");
                } else {
                    var result = ""; var found = false;
                    function recurse (cur, prop, deep) {
                        if(deep > 5 || found){ return;}console.log(prop);
                        try {
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                            } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); return; }
                    }

                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    found ? "___grecaptcha_cfg.clients[0]." + result : null;
                }
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
// End GOOGLE login

private function processInvoices($count = 1){
    sleep(25);
    if ($this->exts->getElement('tr[class*="transactions-table-row"] a[class*="_pdf-url"]') != null) {
        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $pageCount = 1;
        if ($restrictPages == 0) {
            while ($this->exts->getElement('button[data-selenium-test="account-and-billing-ui.detailed-transactions-table.load-more-cta"]') != null && $pageCount <= 30) {
                $pageCount++;
                $this->exts->moveToElementAndClick('button[data-selenium-test="account-and-billing-ui.detailed-transactions-table.load-more-cta"]');
                sleep(5);
            }
        }

        $invoices = [];

        $rows = $this->exts->getElements('tr[class*="transactions-table-row"]');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('a[class*="_pdf-url"]', $row);
            $invoiceUrl = '';
            if($tags[0] != null) {
                $invoiceUrl = $tags[0]->getAttribute('href');
                $invoiceName = $invoiceName = explode('?',
                    array_pop(explode('INVOICE/', $invoiceUrl))
                )[0];
                $invoiceDate = trim($this->exts->getElement('span.short-date time', $row)->getAttribute('datetime'));
                $invoiceAmount = '';
            } 
            $this->exts->log('URL - ' . $invoiceUrl);
            if (stripos($invoiceUrl, 'invoice') === FALSE) {
                $this->exts->log('Rejecting URL because it is not invoice - ' . $invoiceUrl);
                continue;
            }

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices: ' . count($invoices));
        $count = 1;
        $totalFiles = count($invoices);

        foreach ($invoices as $invoice) {
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';

            $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // Download invoice if it not exist
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    $count++;
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        }
    }
}