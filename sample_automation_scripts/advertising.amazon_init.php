public $baseUrl = "https://advertising.amazon.es";
public $orderPageUrl = "https://advertising.amazon.es/billing/history?ref_=ams_head_billing";
public $loginLinkPrim = "div#topNavLinks div.menuClose span.topNavLink a";
public $loginLinkSec = "a[href*=\"/ap/signin\"]";
public $loginLinkThird = "a[href*=\"/sign-in?ref_=\"]";
public $username_selector = "input#ap_email";
public $password_selector = "input#ap_password";
public $submit_button_selector = "#signInSubmit";
public $continue_button_selector = "#continue";
public $logout_link = "div#signOut a[href*=\"/ap/signin?openid.return_to=\"], a[data-e2e-id=\"aac-sign-out-link\"]";
public $remember_me = "input[name=\"rememberMe\"]";
public $login_tryout = 0;
public $isNoInvoice = true;
public $restrictPages = 3;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    // Set custom timeout for portal
    $this->exts->two_factor_timeout = 15;

    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(2);

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-with-cookie");

        $this->exts->openUrl($this->orderPageUrl);
        sleep(2);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->openUrl($this->orderPageUrl);
        $this->exts->waitTillPresent($this->password_selector);
        $this->exts->capture("page-with-login");
        if ($this->exts->querySelector($this->password_selector) == null && $this->exts->querySelector($this->username_selector) == null) {
            if ($this->exts->querySelector($this->loginLinkPrim) != null) {
                $this->exts->log("Found Primary Login Link!!");
                $this->exts->querySelector($this->loginLinkPrim)->click();
            } else if ($this->exts->querySelector($this->loginLinkSec) != null) {
                $this->exts->log("Found Secondry Login Link!!");
                $this->exts->querySelector($this->loginLinkSec)->click();
            } else if ($this->exts->querySelector($this->loginLinkThird) != null) {
                $this->exts->log("Found Third Login Link!!");
                $this->exts->querySelector($this->loginLinkThird)->click();
                sleep(5);

                if ($this->exts->querySelector("form input[name=\"option_choiceGroupName[]\"][id*=\"return_to=https%3A%2F%2Fadvertising.amazon.es\"]") != null) {
                    $checkboxElements = $this->exts->querySelectorAll("form input[name=\"option_choiceGroupName[]\"][id*=\"return_to=https%3A%2F%2Fadvertising.amazon.es\"]");
                    if (count($checkboxElements) > 0) {
                        $bValue = false;
                        $this->exts->log("Check remeber me");
                        $this->exts->click_element($checkboxElements[0]);
                    }

                    $this->exts->querySelector("form button.form-button")->click();
                } else if ($this->exts->querySelector("form input[name=\"option_choiceGroupName[]\"][id*=\"advertising.amazon.es\"]") != null) {
                    $checkboxElements = $this->exts->querySelectorAll("form input[name=\"option_choiceGroupName[]\"][id*=\"advertising.amazon.es\"]");
                    if (count($checkboxElements) > 0) {
                        $bValue = false;
                        $this->exts->log("Check remeber me");
                        $this->exts->click_element($checkboxElements[0]);
                    }

                    $this->exts->querySelector("form button.form-button")->click();
                } else if ($this->exts->querySelector('a[href*="https://www.amazon.es/ap/signin?"]') != null) {
                    $this->exts->moveToElementAndClick('a[href*="https://www.amazon.es/ap/signin?"]');
                } else if ($this->exts->exists('div.header-nav__inner a[href*="/es-es/sign-in"]')) {
                    $this->exts->moveToElementAndClick('div.header-nav__inner a[href*="/es-es/sign-in"]');
                    sleep(15);
                } else if ($this->exts->exists($this->loginLinkSec)) {
                    $this->exts->log("Found Secondry Login Link!!");
                    $this->exts->querySelector($this->loginLinkSec)->click();
                } else {
                    $this->exts->openUrl("https://www.amazon.es/ap/signin?openid.pape.max_auth_age=28800&openid.return_to=https%3A%2F%2Fadvertising.amazon.es%2Fmn%2F%3Fsource%3Dams%26ref_%3Da20m_uk_sgnn_advcnsl&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_ams_gb&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&pageId=ap-ams&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&&suppressSignInRadioButtons=1");
                }
            } else if ($this->exts->exists('div.header-nav__inner a[href*="/sign-in"]')) {
                $this->exts->moveToElementAndClick('div.header-nav__inner a[href*="/sign-in"]');
                sleep(10);
            } else {
                $this->exts->openUrl("https://www.amazon.es/ap/signin?openid.pape.max_auth_age=28800&openid.return_to=https%3A%2F%2Fadvertising.amazon.es%2Fmn%2F%3Fsource%3Dams%26ref_%3Da20m_uk_sgnn_advcnsl&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_ams_gb&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&pageId=ap-ams&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&&suppressSignInRadioButtons=1");
            }
            sleep(10);
            if (strpos(strtolower($this->exts->extract('div#ap_error_page_message')), 'the web address you entered is not a functioning') !== false) {
                $this->exts->openUrl("https://www.amazon.es/ap/signin?openid.pape.max_auth_age=28800&openid.return_to=https%3A%2F%2Fadvertising.amazon.es%2Fbilling%2Fhistory%3Fref_%3Dams_head_billing&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_bt_desktop_es&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&ssoResponse=eyJ6aXAiOiJERUYiLCJlbmMiOiJBMjU2R0NNIiwiYWxnIjoiQTI1NktXIn0.Udw7vEMHmLzecET6gTkLk2ZNB4Dq1fAhuw-l4Yejpr5tOkEdraGsBg.D2gXC6i7uSAJTS_q.qlHTvW2ZrFt0J6PqJLOAensPRkQ9Qfwy1zXdJah3AFRVqo1gJ2juOO9oLMFms4LhNJ2zxj86LUsS59pyrU-BvYrwIv2ZpHoeYAadT_HRzXFgEKsvLxYOuu9fFSP33r4au45-eQDyTU5IA8b9DVKgBW5xbGHoRBuzXIIUpsxTrfzZMIQJdlHEwCuIOz8i_wMnFHa6AWZlvhMd744Bsr4FMTrPPrIYpghnfa9lv0At9rOlwQo-hx-2MU9At-jGeGYSjBgS.u39VpGdJRR4FWhC44wFalw");
            }
        }

        $this->fillForm(0);
        sleep(5);
        // check if login failed
        if ($this->exts->querySelector('#auth-error-message-box')) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } else if ($this->exts->querySelector('#selectAccountType')) {
            $this->exts->account_not_ready();
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin();
        } else {
            //Captcha and Two Factor Check
            if ($this->checkCaptcha() || stripos($this->exts->getUrl(), "/ap/cvf/request") !== false) {
                if ($this->exts->querySelector("form[name=\"claimspicker\"]") != null) {
                    $this->exts->querySelector("form[name=\"claimspicker\"] input#continue[type=\"submit\"]")->click();
                    $this->processTwoFactorAuth();
                } else {
                    $this->processImageCaptcha();
                }
            } else if ($this->checkMultiFactorAuth() && stripos($this->exts->getUrl(), "/ap/mfa?") !== false) {
                $this->processTwoFactorAuth();
            } else if ($this->exts->querySelector("form.cvf-widget-form[action=\"verify\"] input[name=\"code\"]") != null && stripos($this->exts->getUrl(), "/ap/cvf/verify") !== false) {
                $this->processTwoFactorAuth();
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            }

            sleep(2);
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                $this->processAfterLogin();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->processAfterLogin();
    }
}


/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        $this->exts->capture("account-switcher");
        $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details-after-account-removed");
        if (count($account_switcher_elements) > 0) {
            $this->exts->log("click account-switcher");
            $account_switcher_elements[0]->click();
            sleep(2);
        } else {
            $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details");
            if (count($account_switcher_elements) > 0) {
                $this->exts->log("click account-switcher");
                $account_switcher_elements[0]->click();
                sleep(2);
            }
        }

        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $formType = $this->exts->querySelector($this->password_selector);
            if ($formType == null) {
                $this->exts->log("Form with Username Only");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Username form button click");
                $this->exts->querySelector($this->continue_button_selector)->click();
                sleep(2);

                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);

                    sleep(3);

                    if ($this->exts->querySelector($this->remember_me) != null) {
                        $checkboxElements = $this->exts->querySelector($this->remember_me);
                        $this->exts->click_element($checkboxElements);
                    }
                    $this->exts->capture("1-filled-login");
                    $this->exts->querySelector($this->submit_button_selector)->click();
                } else {
                    $this->exts->capture("login-failed");
                    $this->exts->exitFailure();
                }
            } else {
                if ($this->exts->querySelector($this->remember_me) != null) {
                    $checkboxElements = $this->exts->querySelector($this->remember_me);
                    $this->exts->click_element($checkboxElements);
                }

                if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector("input#ap_email[type=\"hidden\"]") == null) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(1);
                }

                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);
                }
                $this->exts->capture("2-filled-login");
                $this->exts->querySelector($this->submit_button_selector)->click();
            }
            sleep(3);
        }
        sleep(15);

        $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details-after-account-removed");
        if (count($account_switcher_elements) > 0) {
            $this->exts->log("click account-switcher");
            $account_switcher_elements[0]->click();
            sleep(2);
        } else {
            $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details");
            if (count($account_switcher_elements) > 0) {
                $this->exts->log("click account-switcher");
                $account_switcher_elements[0]->click();
                sleep(2);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**
    * Method to check captcha form
    * return boolean true/false
    */
function checkCaptcha()
{
    $this->exts->capture("check-captcha");

    $isCaptchaFound = false;
    if ($this->exts->querySelector("input#ap_captcha_guess") != null || $this->exts->querySelector("input#auth-captcha-guess") != null) {
        $isCaptchaFound = true;
    }

    return $isCaptchaFound;
}

/**
    * Method to check Two Factor form
    * return boolean true/false
    */
function checkMultiFactorAuth()
{
    $this->exts->capture("check-two-factor");

    $isTwoFactorFound = false;
    if ($this->exts->querySelector("form#auth-mfa-form") != null) {
        $isTwoFactorFound = true;
    } else if ($this->exts->querySelector("form.cvf-widget-form[action=\"verify\"]") != null) {
        $isTwoFactorFound = true;
    }

    return $isTwoFactorFound;
}

private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
{
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
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
        if ($this->exts->querySelector($this->logout_link) != null) {
            $isLoggedIn = true;
        } else if ($this->exts->querySelector('#signOut') != null) {
            $isLoggedIn = true;
        } else if ($this->exts->exists('button[data-ccx-e2e-id="aac-user-name-dropdown"]')) {
            $this->exts->moveToElementAndClick('button[data-ccx-e2e-id="aac-user-name-dropdown"]');
            sleep(5);
            if ($this->exts->querySelector('a[data-ccx-e2e-id="aac-sign-out-link"]') != null) {
                $isLoggedIn = true;
            }
        } else if ($this->exts->querySelector('div#billing-history-ui') != null) {
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }

    if ($isLoggedIn) {
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
    }

    return $isLoggedIn;
}

/**
    * Method to Process Image Catcha and Password field if present
    */
function processImageCaptcha()
{
    $this->exts->log("Processing Image Captcha");
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
    }
    $this->exts->processCaptcha('form[name="signIn"], img[src*="captcha"]', 'form[name="signIn"] input[name="guess"], input[id="captchacharacters"]');
    sleep(2);

    $this->exts->capture("filled-captcha");
    $this->exts->querySelector($this->submit_button_selector)->click();
    sleep(2);
}

/**
    * Method to Process Two-Factor Authentication
    */
function processTwoFactorAuth()
{
    $this->exts->log("Processing Two-Factor Authentication");

    if ($this->exts->querySelector("form#auth-mfa-form") != null) {

        if ($this->exts->querySelector("input[name=\"rememberDevice\"]") != null) {
            $checkboxElements = $this->exts->querySelector("input[name=\"rememberDevice\"]");
            $this->exts->click_element($checkboxElements);
        }

        //$this->exts->processTwoFactorAuth("input[name=\"otpCode\"]", "form#auth-mfa-form input#auth-signin-button");
        $this->handleTwoFactorCode("input[name=\"otpCode\"]", "form#auth-mfa-form input#auth-signin-button");
    } else if ($this->exts->querySelector("form.cvf-widget-form[action=\"verify\"]") != null) {
        $cpText = $this->exts->querySelector("form.cvf-widget-form[action=\"verify\"] div.a-row:nth-child(1) span");
        $isTwoFactorText = "";

        $isTwoFactorText .= $cpText->getText();

        if (trim($isTwoFactorText) != "" && !empty(trim($isTwoFactorText))) {
            $this->exts->two_factor_notif_msg_en = trim($isTwoFactorText);
            $this->exts->two_factor_notif_msg_de = trim($isTwoFactorText);
        }

        if ($this->exts->querySelector("input[name=\"rememberDevice\"]") != null) {
            $checkboxElements = $this->exts->querySelector("input[name=\"rememberDevice\"]");
            $this->exts->click_element($checkboxElements);
        }
        $this->handleTwoFactorCode("input[name=\"code\"]", "form.cvf-widget-form.fwcim-form input.a-button-input[type=\"submit\"]");
    }
}

function handleTwoFactorCode($two_factor_selector, $submit_btn_selector)
{
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
            //$this->webdriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
            $this->exts->querySelector($submit_btn_selector)->click();
            sleep(10);

            if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

function  processAfterLogin()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}


