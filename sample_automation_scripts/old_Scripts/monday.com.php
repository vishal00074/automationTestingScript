<?php // updated login code
// Server-Portal-ID: 38757 - Last modified: 17.02.2025 13:59:36 UTC - User: 1
public $loginUrl = "https://auth.monday.com/login";
public $baseUrl = "https://auth.monday.com/login";
public $username_selector = 'input#user_email';
public $alt_username_selector = 'div.email_first form.login-form input#email';
public $password_selector = 'input#user_password';
public $alt_password_selector = 'form.login-form input#user_password';
public $webaddress_selector = 'div.choose_slug form.login-form input#slug';
public $submit_button_selector = '.login_button-wrapper > button.submit_button';
public $alt_submit_button_selector = 'div.email_first form.login-form button.submit_button[type="submit"]';
public $slug_submit_button_selector = 'div.choose_slug form.login-form button.submit_button[type="submit"]';
public $login_tryout = 0;
public $restrictPages = 3;
public $account_webaddress = '';

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    if (isset($this->exts->config_array["login_with_google"])) {
        $this->login_with_google = (int)$this->exts->config_array["login_with_google"];
    }
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    // $this->account_webaddress = isset($this->exts->config_array["account_webaddress"]) ? trim($this->exts->config_array["account_webaddress"]) : "";
    // hardcoded assign account_webaddress for testing engine
    $this->account_webaddress = 'correct-printing';

    $this->exts->log('Account Web Address - ' . $this->account_webaddress);

    if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
        if (strpos($this->account_webaddress, 'http') === false) {
            $this->account_webaddress = 'https://' . $this->account_webaddress;
        }
        $this->exts->log('Account Web Address - ' . $this->account_webaddress);
    }

    if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
        $this->exts->openUrl($this->account_webaddress);
    } else {
        $this->exts->openUrl($this->baseUrl);
    }
    sleep(5);

    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
            $this->exts->openUrl($this->account_webaddress);
        } else {
            $this->exts->openUrl($this->baseUrl);
        }
        sleep(15);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->clearChrome();
            $this->exts->execute_javascript('window.sessionStorage.clear(); window.localStorage.clear();');
            sleep(1);
            if ($this->exts->exists('#disabled-account-component')) {
                $this->exts->account_not_ready();
            }
            if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
                $this->exts->openUrl($this->account_webaddress);
                sleep(5);
                if ($this->exts->exists('div#google_apps_login') || $this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                    if ($this->exts->exists('div#google_apps_login')) {
                        //This account required login with goole.
                        $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                        sleep(10);
                        $this->loginGoogleIfRequired();
                    } else if ($this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                        //This account required login with SSO.
                        $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                        sleep(10);
                        $this->fillFormWithLoginProvider();
                    }
                } else {
                    if (!$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector) && !$this->exts->exists($this->alt_username_selector)) {
                        $this->exts->openUrl($this->loginUrl);
                        sleep(5);
                    }
                }
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
            sleep(15);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
                    $this->exts->openUrl($this->account_webaddress);
                    sleep(5);
                    if (!$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector) && !$this->exts->exists($this->alt_username_selector)) {
                        $this->exts->openUrl($this->loginUrl);
                        sleep(5);
                    }
                } else {
                    $this->exts->openUrl($this->loginUrl);
                }
            }
        }
    } else {
        $this->clearChrome();
        if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
            $this->exts->openUrl($this->account_webaddress);
            sleep(5);
            if ($this->exts->exists('div#google_apps_login') || $this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                if ($this->exts->exists('div#google_apps_login')) {
                    //This account required login with goole.
                    $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                    sleep(10);
                    $this->loginGoogleIfRequired();
                } else if ($this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                    //This account required login with SSO.
                    $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                    sleep(10);
                    $this->fillFormWithLoginProvider();
                }
            } else {
                if (!$this->exts->exists($this->username_selector) && !$this->exts->exists($this->password_selector) && !$this->exts->exists($this->alt_username_selector)) {
                    $this->exts->openUrl($this->loginUrl);
                    sleep(5);
                }
            }
        } else {
            $this->exts->openUrl($this->loginUrl);
        }
    }

    if (!$isCookieLoginSuccess) {
        sleep(10);
        if ($this->login_with_google == 1) {
            $this->exts->moveToElementAndClick('.social-login-provider [src*="google"]');
            sleep(7);
            $this->loginGoogleIfRequired();
        } else if ($this->exts->exists('div#google_apps_login') || $this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
            if ($this->exts->exists('div#google_apps_login')) {
                //This account required login with goole.
                $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                sleep(10);
                $this->loginGoogleIfRequired();
            } else if ($this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                //This account required login with SSO.
                $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                sleep(10);
                $this->fillFormWithLoginProvider();
            }
        } else {
            $this->fillForm(0);
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->fillForm(1);
            sleep(5);
            if ($this->exts->exists('div#google_apps_login') || $this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                if ($this->exts->exists('div#google_apps_login')) {
                    //This account required login with goole.
                    $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                    sleep(10);
                    $this->loginGoogleIfRequired();
                } else if ($this->exts->allExists(['div.sso-component', 'div.login-with-provider-button-component button'])) {
                    //This account required login with SSO.
                    $this->exts->moveToElementAndClick('div.google_button, div.login-with-provider-button-component button');
                    sleep(10);
                    $this->fillFormWithLoginProvider();
                }
            }
            sleep(5);
            $this->checkFillTwoFactor();
            sleep(2);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
          
            $this->exts->moveToElementAndClick('.bz-close-btn[aria-label="Close Message"]');
            $this->invoicePage();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();

        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->log("LoginFailed " . $this->exts->getUrl());
            if ($this->exts->exists('#disabled-account-component') || strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'user is inactive') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div.email-not-found-component', null, 'innerText')), 't find this email') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'incorrect email or password') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'single sign-on is required for your account') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'unknown error, please try again') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->moveToElementAndClick('.bz-close-btn[aria-label="Close Message"]');
            if ($this->exts->exists('.nps-modal-portal button[class*="npsModalCloseButton"]')) {
                $this->exts->moveToElementAndClick('.nps-modal-portal button[class*="npsModalCloseButton"]');
                sleep(2);
            }
            $this->invoicePage();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->log("LoginFailed " . $this->exts->getUrl());
            if ($this->exts->exists('#disabled-account-component') || strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'user is inactive') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div.email-not-found-component', null, 'innerText')), 't find this email') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'incorrect email or password') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'single sign-on is required for your account') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.notice-component.warning', null, 'innerText')), 'unknown error, please try again') !== false) {
                $this->exts->loginFailure(1);
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
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login-1");

            $this->exts->log("Enter Username first condition");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->capture("1-pre-login-filled");

            if (!$this->isValidEmail($this->username)) {
                $this->exts->log('>>>>>>>>>>>>>>>>>>>Invalid email........');
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->loginFailure(1);
            }

            $this->exts->moveToElementAndClick('button.submit_button');
            sleep(3);

            $err_msg = $this->exts->extract('div.alert.alert-danger');
            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->loginFailure(1);
            } else {
                if ($this->exts->exists('input.parsley-error')) {
                    $this->exts->log("LoginFailed " . $this->exts->getUrl());
                    $this->exts->loginFailure(1);
                }
            }
        } else if ($this->exts->exists('div.email_first form.login-form input#email, div.email-first-component input.email-input')) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login-2");

            $this->exts->log("Enter Username second condition");
            $this->exts->moveToElementAndType('div.email_first form.login-form input#email, div.email-first-component input.email-input', $this->username);

            $this->exts->moveToElementAndClick('div.email_first form.login-form button.submit_button[type="submit"], div.email-first-component button.next-button');
            sleep(5);

            if (!$this->isValidEmail($this->username)) {
                $this->exts->log('>>>>>>>>>>>>>>>>>>>Invalid email........');
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->loginFailure(1);
            }

            if ($this->exts->urlContains('/sso') && $this->exts->exists('.login-with-credentials-button-component button')) {
                $this->exts->moveToElementAndClick('.login-with-credentials-button-component button');
                $this->exts->log('__________________Fill login with SSO choices:____________________');
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-pre-login-filled_password");
                $this->exts->moveToElementAndClick('.login_button-wrapper > button.submit_button');
                sleep(3);

                $err_msg = $this->exts->extract('div.alert.alert-danger');
                if ($err_msg != "" && $err_msg != null) {
                    $this->exts->log($err_msg);
                    $this->exts->log("LoginFailed " . $this->exts->getUrl());
                    $this->exts->loginFailure(1);
                } else {
                    if ($this->exts->exists('input.parsley-error')) {
                        $this->exts->log("LoginFailed " . $this->exts->getUrl());
                        $this->exts->loginFailure(1);
                    }
                }
            }

            if ($this->exts->exists('div.choose_slug form.login-form input#slug, input.enter-slug-input')) {
                if (!empty($this->account_webaddress) && trim($this->account_webaddress) != "") {
                    $account_webaddress = trim($this->account_webaddress);
                    if (stripos($account_webaddress, "http://") !== false || stripos($account_webaddress, "https://") !== false) {
                        $account_webaddress = str_replace('http://', '', $account_webaddress);
                        $account_webaddress = str_replace('https://', '', $account_webaddress);
                    }
                    $account_webaddress = str_replace('.monday.com', '', $account_webaddress);
                    $this->exts->log('Account Web Address - ' . $account_webaddress);
                    $this->exts->log("Enter WebAddress");
                    $this->exts->moveToElementAndType('div.choose_slug form.login-form input#slug, input.enter-slug-input', $account_webaddress);

                    $this->exts->moveToElementAndClick('div.choose_slug form.login-form button.submit_button[type="submit"], div.enter-slug-content button.next-button');
                    sleep(5);
                    if ($this->exts->exists('#enter-slug-error')) {
                        $this->exts->log("LoginFailed " . $this->exts->getUrl());
                        $this->exts->loginFailure(1);
                    }
                } else {
                    $this->exts->log('Account Web Address is null');
                    $this->exts->log("LoginFailed " . $this->exts->getUrl());
                    $this->exts->loginFailure(1);
                }
            }

            if ($this->exts->exists('div.email-password-component input.password-input')) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType('div.email-password-component input.password-input', $this->password);
                $this->exts->capture("1-pre-login-filled");

                $this->exts->moveToElementAndClick('div.email-password-component button.next-button');
                sleep(5);
            } else if ($this->exts->exists('input#user_password')) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType('input#user_password', $this->password);
                $this->exts->capture("1-pre-login-filled");

                $this->exts->moveToElementAndClick('.login_button-wrapper > button.submit_button');
                sleep(5);
            }

            $err_msg = $this->exts->extract('div.alert.alert-danger');
            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->loginFailure(1);
            } else {
                if ($this->exts->exists('input.parsley-error')) {
                    $this->exts->log("LoginFailed " . $this->exts->getUrl());
                    $this->exts->loginFailure(1);
                }
            }
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}
function fillFormWithLoginProvider()
{
    sleep(5);
    if ($this->exts->urlContains('treeti.my.idaptive.app/login')) {
        $this->exts->capture('login-with-treeti');
        $this->exts->moveToElementAndType('#usernameForm input[name="username"]', $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick('#usernameForm button[type="submit"]');
        sleep(3);
        if ($this->exts->exists('#errorForm div.error-message') && trim($this->exts->extract('#errorForm div.error-message')) != '') {
            $this->exts->log(trim($this->exts->extract('#errorForm div.error-message')));
            $this->exts->log($this->exts->getUrl());
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('form.login-form input#user_password')) {
            $this->exts->moveToElementAndType('form.login-form input#user_password', $this->password);
            sleep(1);
        }
        $this->exts->capture(__FUNCTION__ . '-filled');

        if ($this->exts->exists('.login_button-wrapper > button.submit_button')) {
            $this->exts->moveToElementAndClick('.login_button-wrapper > button.submit_button');
            sleep(3);
            $this->exts->capture(__FUNCTION__ . '-submitted');
        }
    } else if ($this->exts->urlContains('google.')) {
        $this->loginGoogleIfRequired();
    } else {
        $this->exts->capture(__FUNCTION__ . '-not-found');
        $this->exts->log(__FUNCTION__ . ' No login form found');
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[id*="user_otp"], .two-factor-content input.otp-input';
    $two_factor_message_selector = 'div[class*="otp_title"], .two-factor-content .two-factor-header';
    $two_factor_submit_selector = 'button[class*="submit_button"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->getElement($two_factor_selector) == null) {
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
    } else if ($this->exts->getElement('//div[contains(text(),"To verify this is you, check your email for a link to your account")]', null, 'xpath') != null) {

        $two_factor_message_selector = '//div[contains(text(),"To verify this is you, check your email for a link to your account")]';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
            $this->exts->two_factor_notif_msg_en = "";
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();

            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
            $this->exts->openUrl($two_factor_code);
            sleep(25);
            $this->exts->capture("after-open-url-two-factor");
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}
private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);


        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);


        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');


            $gcallbackFunction = $this->exts->execute_javascript('
                    (function() {
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
                    })();
                    ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            // try again if recaptcha expired
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

// -------------------- GOOGLE login

public $google_username_selector = 'input[name="identifier"]';
public $google_submit_username_selector = '#identifierNext';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #passwordNext button';
public $google_solved_rejected_browser = false;
private function loginGoogleIfRequired()
{
    if ($this->exts->urlContains('google.')) {
        $this->checkFillGoogleLogin();
        sleep(10);
        $this->check_solve_rejected_browser();

        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        // Click next if confirm form showed
        $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->checkGoogleTwoFactorMethod();
        sleep(10);
        if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
            $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->exts->exists('#tos_form input#accept')) {
            $this->exts->click_by_xdotool('#tos_form input#accept');
            sleep(10);
        }
        if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->click_by_xdotool('.action-button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->click_by_xdotool('input[name="later"]');
            sleep(7);
        }
        if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }
        if ($this->exts->urlContains('gds.google.com/web/chip')) {
            $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }


        $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
        $this->exts->capture("google-login-before-back-maintab");
        if (
            $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
        ) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}

private function checkFillGoogleLogin()
{
    if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
        $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
        sleep(10);
    } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
        $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
        sleep(10);
    }
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    }
    $this->exts->capture("2-google-login-page");
    if ($this->exts->querySelector($this->google_username_selector) != null) {
        // $this->fake_user_agent();
        // $this->exts->refresh();
        // sleep(5);

        $this->exts->log("Enter Google Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->querySelector($this->google_password_selector) != null) {
        $this->exts->log("Enter Google Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);

        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->capture("2-login-google-pageandcaptcha-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(10);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::google Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}

private function check_solve_rejected_browser()
{
    $this->exts->log(__FUNCTION__);
    $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
}

private function overwrite_user_agent($user_agent_string = 'DN')
{
    $userAgentScript = "
                        (function() {
                        if ('userAgentData' in navigator) {
                        navigator.userAgentData.getHighEntropyValues({}).then(() => {
                        Object.defineProperty(navigator, 'userAgent', {
                        value: '{$user_agent_string}',
                        configurable: true
                        });
                        });
                        } else {
                        Object.defineProperty(navigator, 'userAgent', {
                        value: '{$user_agent_string}',
                        configurable: true
                        });
                        }
                        })();
                        ";
    $this->exts->execute_javascript($userAgentScript);
}

private function checkFillLogin_undetected_mode($root_user_agent = '')
{
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
        $this->exts->capture("2-google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
        if (!empty($root_user_agent)) {
            $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
        }
        $this->exts->type_key_by_xdotool("F5");
        sleep(5);
        $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        $this->exts->log('current_useragent: ' . $current_useragent);
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);
        $this->exts->capture_by_chromedevtool("2-google-username-filled");
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
        }

        if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(6);
                $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
            }
        }

        // Which account do you want to use?
        if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}

private function checkGoogleTwoFactorMethod()
{
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
    $this->exts->capture("2.0-before-check-two-factor");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
        $this->exts->click_by_xdotool('#assistActionId');
        sleep(5);
    } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
        if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
        // CURRENTLY THIS CASE CAN NOT BE SOLVED
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb");
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2) div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
    } else if ($this->exts->exists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
    } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
        // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
        $supporting_languages = [
            "Try another way",
            "Andere Option w",
            "Essayer une autre m",
            "Probeer het op een andere manier",
            "Probar otra manera",
            "Prova un altro metodo"
        ];
        $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
        $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
        $back_button_xpath = $back_button_xpath . ']/..';
        $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
        if ($back_button != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
            } catch (\Exception $exception) {
                $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
            }
        }
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        $this->exts->capture("2.1-2FA-method-list");

        // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
        if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
            // Select enter your passowrd, if only option is passkey
            $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
            sleep(3);
            $this->checkFillGoogleLogin();
            sleep(3);
            $this->checkGoogleTwoFactorMethod();
        } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    }

    // STEP 2: (Optional)
    if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
        // If methos is recovery email, send 2FA to ask for email
        $this->exts->two_factor_attempts = 2;
        $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if (isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->exists('input#phoneNumberId')) {
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional) After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
        $this->exts->two_factor_attempts = 3;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionIdk
    } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}

private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Two factor page found.");
    $this->exts->capture("2.1-two-factor");

    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
    }

    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
            if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("fillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool('Return');
            }
            sleep(10);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Two factor solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                        // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                        $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                        sleep(2);
                        $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                        // }
                    }

                    $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Not found two factor input");
        }
    } else {
        $this->exts->log("Not received two factor code");
    }
}

// -------------------- GOOGLE login END

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists('div.surface-avatar-menu-component')) {

            $isLoggedIn = true;
            if ($this->exts->getElement('div.reset-trial-component-inner') != null) {
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->account_not_ready();
                $isLoggedIn = false;
            }
           
            $trial_expired_selector = $this->exts->getElementByText('div.pricing-message', ['free trial has expired', 'Ihre kostenlose Testversion ist abgelaufen'], null, false);
            if ($trial_expired_selector != null) {
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                $this->exts->account_not_ready();
                $isLoggedIn = false;
            }

            if($isLoggedIn){
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            }

            
           
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
function invoicePage()
{
    if ($this->exts->exists('a.fancybox-item.fancybox-close')) {
        $this->exts->moveToElementAndClick('a.fancybox-item.fancybox-close');
        sleep(5);
    }
    if ($this->exts->exists('.nps-modal-portal button[class*="npsModalCloseButton"]')) {
        $this->exts->moveToElementAndClick('.nps-modal-portal button[class*="npsModalCloseButton"]');
        sleep(2);
    }
    $this->exts->log("Invoice page");
    if ($this->exts->exists('div.payments-dialog-header-component .icon-dapulse-x-slim')) {
        $this->exts->moveToElementAndClick('div.payments-dialog-header-component .icon-dapulse-x-slim');
        sleep(5);
    }
    if ($this->exts->exists('.trial-read-only-container .ds-btn-secondary')) {
        $this->exts->moveToElementAndClick('.trial-read-only-container .ds-btn-secondary');
        sleep(5);
    }
    if ($this->exts->exists('div.ReactModalPortal div.ReactModal__Overlay')) {
        $this->exts->execute_javascript('document.querySelector("div.ReactModalPortal div.ReactModal__Overlay").style.display = "none";');
    }

    if ($this->exts->exists('.surface-avatar-menu-component .ds-menu-button-container')) {
        $this->exts->moveToElementAndClick('.surface-avatar-menu-component .ds-menu-button-container');
    } else {
        $this->exts->moveToElementAndClick('.surface-avatar-menu-component');
    }
    sleep(5);
    if ($this->exts->exists('a.fancybox-item.fancybox-close')) {
        $this->exts->moveToElementAndClick('a.fancybox-item.fancybox-close');
        sleep(5);
    }
    if ($this->exts->exists('.nps-modal-portal button[class*="npsModalCloseButton"]')) {
        $this->exts->moveToElementAndClick('.nps-modal-portal button[class*="npsModalCloseButton"]');
        sleep(2);
    }

    if (!$this->exts->exists('.ds-menu-section a[href*="/admin/general/profile"]')) {
        $this->exts->moveToElementAndClick('.surface-avatar-menu-component img');
        sleep(5);
    }
    $this->exts->moveToElementAndClick('.ds-menu-section a[href*="/admin/general/profile"] .ds-menu-item');
    sleep(5);

    if ($this->exts->exists('span[class*="admin-billing"], #admin_item_billing, div#billing')) {
        $this->exts->moveToElementAndClick('span[class*="admin-billing"], #admin_item_billing, div#billing');
        sleep(5);
    } else {
        $tab_buttons = $this->exts->getElements('div#new-admin div.left-nav ul > li > a');
        $this->exts->log('Finding Invoice button...');
        $noClickInvoiceButton = true;
        foreach ($tab_buttons as $key => $tab_button) {
            $tab_name = trim($tab_button->getAttribute('innerText'));
            if (stripos($tab_name, 'Invoices') !== false || stripos($tab_name, 'Rechnungen') !== false) {
                $this->exts->log('Completted trips button found');
                try {
                    $tab_button->click();
                } catch (Exception $e) {
                    $this->exts->execute_javascript('arguments[0].click()', [$tab_button]);
                }
                sleep(10);
                $noClickInvoiceButton = false;
                break;
            }
        }

        if ($noClickInvoiceButton) {
            $this->exts->moveToElementAndClick('div#new-admin div.left-nav ul > li:nth-child(6) > a, div#new-admin div.left-nav li#admin_item_billing, #admin_item_billing');
            sleep(5);
        }
    }

    // Click Completted trips tab
    // We can not indentify Completted trips button by selector, the only way is by label
    $tab_buttons = $this->exts->getElements('div#new-admin div.right-content ul > li > a');
    $this->exts->log('Finding Completted trips button...');
    $noClickTabButton = true;
    foreach ($tab_buttons as $key => $tab_button) {
        $tab_name = trim($tab_button->getAttribute('innerText'));
        if (stripos($tab_name, 'Invoices') !== false || stripos($tab_name, 'Rechnungen') !== false) {
            $this->exts->log('Completted trips button found');
            try {
                $tab_button->click();
            } catch (Exception $e) {
                $this->exts->execute_javascript('arguments[0].click()', [$tab_button]);
            }
            sleep(10);
            $noClickTabButton = false;
            break;
        }
    }
    if ($noClickTabButton) {
        $this->exts->moveToElementAndClick('div#new-admin div.right-content ul > li:nth-child(4) > a');
        sleep(5);
    }

    if ($this->exts->exists('div#upgrade_promotion_dialog') != null) {
        $this->exts->moveToElementAndClick('a.fancybox-close');
        sleep(5);
    }

    $this->downloadInvoice();

    if ($this->totalFiles == 0) {
        $this->exts->log("No invoice !!! ");
        $this->exts->no_invoice();
    }
    $this->exts->success();
}
public $totalFiles = 0;
function downloadInvoice($count = 1, $pageCount = 1)
{
    $this->exts->log("Begin download invoice");

    $this->exts->capture('4-List-invoice');

    try {
        if ($this->exts->getElement('div.invoices-component table tbody tr') != null) {
            $receipts = $this->exts->getElements('div.invoices-component table tbody tr');
            $invoicesCount = 0;
            foreach ($receipts as $i => $receipt) {
                $tags = $this->exts->getElements('td', $receipt);
                if (count($tags) >= 5) {

                    $receiptUrl = $this->exts->extract('td button', $receipt, 'href');
                    if (strpos($receiptUrl, 'http') === false) {
                        $receiptUrl = $this->exts->execute_javascript("return arguments[0].href;", [$this->exts->getElement('td button', $receipt)]);
                        if ($receiptUrl == null || $receiptUrl == '') {
                            $actions = $this->exts->getElement('td button', $receipt);
                            if ($actions != null) {
                                $receiptDate = trim($tags[0]->getText());
                                $parsed_date = $this->exts->parse_date($receiptDate);
                                $this->exts->log('Date parsed: ' . $parsed_date);
                                if ($parsed_date != '') {
                                    $receiptDate = $parsed_date;
                                }
                                $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';
                                $actions->click();
                                sleep(2);
                                if ($this->exts->exists('ul >li[role="menuitem"]:nth-child(1):not([class*="disabled"])')) {
                                    $this->donwloadSingleInvoice('ul >li[role="menuitem"]:nth-child(1):not([class*="disabled"])', $receiptDate, $receiptAmount, $i);
                                    $invoicesCount++;
                                } else {
                                    $tags[0]->click();
                                    sleep(2);
                                }
                            }
                        }
                    }
                }
            }

            $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . $invoicesCount);

            $this->totalFiles = $invoicesCount;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downloading invoice " . $exception->getMessage());
    }
}
function donwloadSingleInvoice($buttonSelector, $invoiceDate, $invoiceAmount, $index)
{
    if ($this->exts->exists($buttonSelector)) {
        $this->exts->moveToElementAndClick($buttonSelector);
        sleep(3);
        $this->exts->wait_and_check_download('pdf');
        $downloaded_file = $this->exts->find_saved_file('pdf');

        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $invoiceFileName = basename($downloaded_file);
            $invoiceName = explode('.pdf', $invoiceFileName)[0];
            $invoiceName = trim(explode('(', $invoiceName)[0]);
            $this->exts->log('Final invoice name: ' . $invoiceName);

            // Create new invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            }
        } else {
            $invoice_tab = $this->exts->findTabMatchedUrl(['invoice']);
            if ($invoice_tab != null) {
                $this->exts->switchToTab($invoice_tab);
            }

            $invoiceUrl = $this->exts->getUrl();
            $invoiceName = trim(end(explode('invoice/', $invoiceUrl)));
            $invoiceFileName = $invoiceName . '.pdf';
            $this->exts->log("_____________________" . ($index + 1) . "___________________________________________");
            $this->exts->log("Invoice Date: " . $invoiceDate);
            $this->exts->log("Invoice Name: " . $invoiceName);
            $this->exts->log("Invoice Amount: " . $invoiceAmount);
            $this->exts->log("Invoice Url: " . $invoiceUrl);
            $this->exts->log("Invoice FileName: " . $invoiceFileName);
            $this->exts->log("________________________________________________________________");

            if ($this->exts->exists('a[href*="/show_invoice.jsp?ref="]')) {
                $invoiceUrl = $this->exts->getElement('a[href*="/show_invoice.jsp?ref="]')->getAttribute('href');
                if (strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $this->exts->execute_javascript("return arguments[0].href;", [$this->exts->getElement('a[href*="/show_invoice.jsp?ref="]')]);
                }
                $this->exts->log("New Invoice URL - " . $invoiceUrl);
                $downloaded_file = $this->exts->download_capture($invoiceUrl, $invoiceFileName, 5);
                $this->exts->log("Download file: " . $downloaded_file);
            } else {
                if ($this->exts->urlContains('invoice/')) {
                    $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
                    $this->exts->log("Download file: " . $downloaded_file);
                }
            }

            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
            }

            $this->exts->switchToInitTab();
        }
    }
}