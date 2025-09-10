<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }
    // Server-Portal-ID: 6459 - Last modified: 19.05.2025 14:25:48 UTC - User: 1

    // Script here
    public $baseUrl = "https://stripe.com/de";
    public $loginUrl = "https://dashboard.stripe.com/login";
    public $invoiceUrl = "https://dashboard.stripe.com/account/documents";
    public $payoutUrl = "https://dashboard.stripe.com/payouts";
    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me = 'input[name="remember"]';
    public $submit_btn = 'form button.button[type="submit"], button[type="submit"]';
    public $logout_selector = 'button[class*="db-Notifications-button"], a[href*="/dashboard"], a[href*="/settings"]';

    public $download_payout = 0;
    public $no_invoice = true;
    public $restrictPages = 3;
    public $login_with_google = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : $this->restrictPages;
        $this->download_payout = isset($this->exts->config_array["download_payout"]) ? (int)@$this->exts->config_array["download_payout"] : $this->download_payout;
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : 0;

        $this->exts->openUrl($this->invoiceUrl);
        sleep(15);
        $this->exts->capture("init-page");

        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->invoiceUrl);
            sleep(15);
            if ((int)@$this->login_with_google == 1) {
                $this->exts->moveToElementAndClick('a#continue_with_google');
                sleep(15);
                $this->loginGoogleIfRequired();
            } else {
                $this->fill_credential();
                $this->checkFillRecaptcha();
                $this->exts->type_key_by_xdotool("Return");
                sleep(10);
                $this->solve_login_hcaptcha();

                if ($this->exts->exists('div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]')) {
                    $this->clearChrome();
                    $this->exts->openUrl($this->invoiceUrl);
                    sleep(15);
                    $this->fill_credential();
                    $this->checkFillRecaptcha();
                    $this->exts->type_key_by_xdotool("Return");
                    sleep(10);
                    $this->solve_login_hcaptcha();
                }
                sleep(10);

                $this->checkFillTwoFactor();
                if ($this->exts->exists('//button//*[contains(text(), "Send verification")]')) {
                    $this->exts->click_element('//button//*[contains(text(), "Send verification")]');
                    sleep(5);
                    $this->checkFillTwoFactorVerificationLink();
                }
            }

            if ($this->exts->exists('.db-Login-root a[data-name="skip"]')) {
                // SKIP 2fa set up
                $this->exts->moveToElementAndClick('.db-Login-root a[data-name="skip"]');
                sleep(5);
            }
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            sleep(3);
            $this->exts->capture("LoginSuccess");
            if ($this->exts->getElement('.auth-modal #old-password[name="password"]') != null && $this->exts->getElement('.auth-modal [data-db-analytics-name="reauth_submit_button"]') != null) {
                $this->exts->moveToElementAndType('.auth-modal #old-password[name="password"]', $this->password);
                $this->exts->capture("re-authen");
                $this->exts->moveToElementAndClick('.auth-modal [data-db-analytics-name="reauth_submit_button"]');
                sleep(10);
                $this->exts->capture("after-re-authen");
            }

            $this->exts->moveToElementAndClick('div.db-AccountSwitcher-button, button .db-AccountSwitcher-activeImage, div[data-testid="account-picker-v2-menu-button"] button');
            $this->exts->capture("accounts-checking");

            $accounts = $this->exts->getElements('ul#accountSwitcher li button.db-AccountSwitcherItem-button');
            $this->exts->log("Total Accounts - " . count($accounts));

            $alt_accounts = $this->exts->getElements('ul li button.db-AccountSwitcherItem-button, div[data-testid="account-switcher-menu-content"]');
            $this->exts->log("Total ALT Accounts - " . count($alt_accounts));
            if (count($accounts) > 2) {
                foreach ($accounts as $key => $account) {
                    $this->exts->openUrl($this->invoiceUrl);
                    sleep(10);
                    $this->downloadInvoice(0);

                    if ((int)$this->download_payout == 1) {
                        $this->exts->openUrl($this->payoutUrl);
                        sleep(10);
                        $this->downloadPayout(0, 1);
                    }
                    if ($this->exts->config_array['sales_invoice'] == '1') {
                        $this->exts->openUrl('https://dashboard.stripe.com/invoices?status=paid');
                        sleep(10);
                        $this->download_sales_invoice();
                    }
                    if ($key == count($accounts) - 1) break;

                    $this->exts->openUrl($this->invoiceUrl);
                    sleep(10);

                    $this->exts->moveToElementAndClick('div.db-AccountSwitcher-button, button .db-AccountSwitcher-activeImage');
                    sleep(1);

                    $this->exts->moveToElementAndClick('ul#accountSwitcher li:nth-child(' . ($key + 2) . ') button.db-AccountSwitcherItem-button');
                    sleep(15);
                }
            } else if (count($alt_accounts) >= 1) {
                $account_labels = [];
                foreach ($alt_accounts as $account) {
                    $account_label = $account->getAttribute('innerText');
                    if (stripos($account_label, 'new account') === false && stripos($account_label, 'Neues Konto einrichten') === false) {
                        $this->exts->log($account_label);
                        array_push($account_labels, $account_label);
                    }
                }
                //Download current account:
                $this->exts->openUrl($this->invoiceUrl);
                sleep(10);
                $this->downloadInvoice(0);

                if ((int)$this->download_payout == 1) {
                    $this->exts->openUrl($this->payoutUrl);
                    sleep(10);
                    $this->downloadPayout(0, 1);
                }

                if ($this->exts->config_array['sales_invoice'] == '1') {
                    $this->exts->openUrl('https://dashboard.stripe.com/invoices?status=paid');
                    sleep(3);
                }
                //Process other accounts
                $this->exts->log("Total ALT Accounts - " . count($account_labels));
                foreach ($account_labels as $key => $account_label) {
                    $account_xpath = '//div[contains(@data-testid, "account-switcher-menu-content")]//*[text()="' . $account_label . '"]';
                    $this->exts->log("PROCESSING Account: " . $account_label);
                    if ($this->exts->getElement($account_xpath, null, 'xpath') == null) {
                        $this->exts->moveToElementAndClick('div.db-AccountSwitcher-button, button .db-AccountSwitcher-activeImage, div[data-testid="account-picker-v2-menu-button"] button');
                        sleep(2);
                    }
                    $this->exts->click_element($account_xpath);
                    sleep(10);

                    $this->exts->openUrl($this->invoiceUrl);
                    sleep(10);
                    $this->downloadInvoice(0);

                    if ((int)$this->download_payout == 1) {
                        $this->exts->openUrl($this->payoutUrl);
                        sleep(10);
                        $this->downloadPayout(0, 1);
                    }

                    if ($this->exts->config_array['sales_invoice'] == '1') {
                        $this->exts->openUrl('https://dashboard.stripe.com/invoices?status=paid');
                        sleep(3);
                        $this->download_sales_invoice();
                    }
                }
            } else {
                $this->exts->openUrl($this->invoiceUrl);
                sleep(10);
                $this->downloadInvoice(0);

                if ((int)$this->download_payout == 1) {
                    $this->exts->openUrl($this->payoutUrl);
                    sleep(10);
                    $this->downloadPayout(0, 1);
                }

                if ($this->exts->config_array['sales_invoice'] == '1') {
                    $this->exts->openUrl('https://dashboard.stripe.com/invoices?status=paid');
                    sleep(3);
                    $this->download_sales_invoice();
                }
            }

            if ($this->no_invoice) {
                $this->exts->no_invoice();
                $this->exts->success();
            }
            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            if ($this->exts->getElementByText('span', ['Set up two-step authentication'], null, true) != null || $this->exts->urlContains('/two_step_optout')) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->exists('a[href*="how-do-i-complete-the-email-link-verification"')) {
                $this->exts->account_not_ready();
            }
            $error_text = $this->exts->extract('form .Text-color--red');
            if (stripos($error_text, 'Incorrect email or password') !== false || stripos($error_text, 'invalid') !== false || stripos($error_text, 'falsche') !== false) {
                // $this->exts->loginFailure(1);
                // DON NOT call login failed comfirm because this site return "Incorrect credential" but the credential is valid.
            } else if ($this->exts->exists('[role="main"]>div:not([aria-hidden="true"]) >div>div > div:not([aria-hidden="true"]) .ContentState  .Spinner')) {
                $this->exts->capture("2.1-2fa-usb");
                // If user is required plug in security usb, we can not solve this case,
                // Currently, USB promt will block browser from selenium, so we return account not ready (leader confirmed this)
                $this->exts->account_not_ready();
            } else {
                $error_text = strtolower($this->exts->extract('[role="main"]>div:not([aria-hidden="true"]) >div>div > div:not([aria-hidden="true"])  p a'));
                if (strpos($error_text, 'resend') !== false || strpos($error_text, 'erneut senden') !== false) {
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
        sleep(10);
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
        sleep(1);
        $this->exts->capture("clear-page");
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
        sleep(15);
        $this->exts->capture("after-clear");
    }
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->logout_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->exists("button.db-UserMenu[type=\"button\"]")) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->exists("button.db-AccountSwitcher-button")) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->exists("li a[href*=\"/billing\"]")) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if (stripos($this->exts->getUrl(), "/account/documents") !== false) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }
    function fill_credential()
    {
        $this->exts->capture("2-login-page");
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(3);

            if ($this->exts->exists('a#toggle_password_mode')) {
                $this->exts->click_by_xdotool('a#toggle_password_mode');
                sleep(2);
            }

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    function checkFillTwoFactor()
    {
        $is_security_key_method = false;
        if ($this->exts->exists('[role="main"]>div:not([aria-hidden="true"]) >div>div > div:not([aria-hidden="true"]) .ContentState  .Spinner')) {
            $this->exts->capture("2.1-2fa-usb");
            $this->exts->type_key_by_xdotool('Return');
            sleep(3);
            $this->exts->capture("2.0-canceled-security-usb");

            // If user is required plug in security usb, we can not solve this case, so try to click "Sign in difference way"
            $try_other_method_button = $this->exts->getElement('//button//*[contains(text(), "another way") or contains(text(), "Sie eine andere Anmeldemethode") or contains(text(), "eine andere Art der Anmeldung")]', null, 'xpath');
            if ($try_other_method_button != null) {
                $this->exts->executeSafeScript('arguments[0].click();', [$try_other_method_button]);
                sleep(3);
                $this->exts->capture("2.1-2fa-usb-skipped");
                if ($this->exts->exists('a[name="totp"]')) {
                    $this->exts->moveToElementAndClick('a[name="totp"]');
                } else if ($this->exts->exists('a[name="phone"]')) {
                    $this->exts->moveToElementAndClick('a[name="phone"]');
                }
                sleep(6);
                $this->exts->capture("2.1-2fa-method-changed");
            }
        }
        $use_authenticator_checkbox = $this->exts->getElement('//label//*[contains(text(), "authenticator app")]', null, 'xpath');
        if ($use_authenticator_checkbox != null) {
            $this->exts->capture("2.1-select-2fa-method");
            $this->exts->executeSafeScript('arguments[0].click();', [$use_authenticator_checkbox]);
            sleep(3);
            $this->exts->capture("2.1-2fa-authenticator-selected");
            $this->exts->moveToElementAndClick('button[data-db-analytics-name="webauthn_interstitial_continue_button"]');
            sleep(5);
        }
        $use_sms_checkbox = $this->exts->getElement('//label//*[contains(text(), "code to your phone")]', null, 'xpath');
        if ($use_sms_checkbox != null) {
            $this->exts->capture("2.1-select-2fa-method");
            $this->exts->executeSafeScript('arguments[0].click();', [$use_sms_checkbox]);
            sleep(3);
            $this->exts->capture("2.1-2fa-sms-selected");
            $this->exts->moveToElementAndClick('button[data-db-analytics-name="webauthn_interstitial_continue_button"]');
            sleep(5);
        }

        if ($this->exts->exists('.CodePuncher-controlInput[type="tel"]') && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $two_factor_selector = '.CodePuncher-controlInput[type="tel"]';
            $two_factor_message_selector = '[data-testid="login-challenge-header"]';
            $two_factor_submit_selector = 'form button[type="submit"], div.db-Login-fields span.Button-label';

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $two_factor_code =  trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $two_factor_code = str_replace('-', '', $two_factor_code);
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if (!$is_security_key_method) {
                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                }

                sleep(5);
                $this->checkFillRecaptcha();
                $this->checkFillRecaptcha();
                $this->checkFillRecaptcha();

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
        }
    }
    function checkFillTwoFactorVerificationLink()
    {
        $two_factor_message_selector = 'div.Dialog-content p';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute("innerText") . "\n";
            }
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . 'Pls copy that link then paste here. Remember not to click on the link.';
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
            $this->exts->openNewTab();
            $this->exts->openUrl($two_factor_code);
            sleep(10);
            $this->exts->capture("after-open-url-two-factor");
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    private function process_recaptcha_by_clicking()
    {
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise/anchor"]';
        $recaptcha_challenger_wraper_selector = 'div:not([style*="hidden"])> div > iframe[src*="bframe"][src*="/recaptcha/"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $this->exts->capture("recaptcha");
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->exists($recaptcha_challenger_wraper_selector)) {
                $this->exts->switchToFrame($recaptcha_iframe_selector);
                $this->exts->moveToElementAndClick('.recaptcha-checkbox');
                sleep(3);
                if ($this->exts->exists('.recaptcha-checkbox[aria-checked="true"]')) {
                    $this->exts->log('SOLVED');
                    $this->exts->switchToDefault();
                    return;
                }
                $this->exts->switchToDefault();
            }
            $this->exts->switchToDefault();

            $this->exts->switchToFrame($recaptcha_challenger_wraper_selector);

            $captcha_instruction = $this->exts->extract('.challenge-header .prompt-text');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);

            $this->exts->switchToDefault();
            $coordinates = $this->exts->processClickCaptcha($recaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
            if ($coordinates == '') {
                $coordinates = $this->exts->processClickCaptcha($recaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
            }
            if ($coordinates != '') {
                $challenge_wraper = $this->exts->getElement($recaptcha_challenger_wraper_selector);
                foreach ($coordinates as $coordinate) {
                    $actions = $this->exts->webdriver->action();
                    $this->exts->log('Clicking X/Y: ' . $coordinate['x'] . '/' . $coordinate['y']);
                    sleep(1);
                    $actions->moveToElement($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']))->click()->perform();
                }
                sleep(1);
                $this->exts->switchToFrame($recaptcha_challenger_wraper_selector);
                $this->exts->moveToElementAndClick('button#recaptcha-verify-button');
                sleep(3);
                $this->exts->switchToDefault();
            }
            $this->exts->switchToDefault();
            $this->exts->switchToFrame($recaptcha_iframe_selector);
            if ($this->exts->exists('.recaptcha-checkbox[aria-checked="true"]')) {
                $this->exts->log('SOLVED');
            }

            $this->exts->switchToDefault();
            return true;
        }
        $this->exts->switchToDefault();
        return false;
    }
    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"], iframe[src*="/recaptcha/enterprise/anchor"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';

        $recaptcha_exist = $this->exts->execute_javascript('
			var elements = document.querySelectorAll(\'' . $recaptcha_iframe_selector . '\');
			if(elements.length > 0){
				true;
			} else {
				false;
			}
		');
        if ($recaptcha_exist) {
            $data_siteKey =  $this->exts->execute_javascript('
				var recaptchaiframe = document.querySelector(\'' . $recaptcha_iframe_selector . '\');
				var iframeUrl = recaptchaiframe.getAttribute("src");
				var google_key= iframeUrl.split("k=").pop().split("&")[0];
				google_key;
			');
            $this->exts->log("SiteKey - " . $data_siteKey);

            $cmd = $this->exts->config_array['recaptcha_shell_script'] .
                " --PROCESS_UID::" . $this->exts->process_uid .
                " --GOOGLE_KEY::" . $data_siteKey .
                " --BASE_URL::" . $this->exts->getUrl();
            $this->exts->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->exts->log('Command Result : ' . print_r($output, true));
            if (empty($output)) {
                exec($cmd, $output, $return_var);
                $this->exts->log('Command Result : ' . print_r($output, true));
            }

            if (!empty($output)) {
                $this->exts->recaptcha_answer = '';
                foreach ($output as $line) {
                    if (stripos($line, "RECAPTCHA_ANSWER") !== false) {
                        $result_codes = explode("RECAPTCHA_ANSWER:", $line);
                        $this->exts->recaptcha_answer = $result_codes[1];
                        break;
                    }
                }

                // Step 1 fill answer to textarea
                // $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
                // $this->exts->execute_javascript(('
                // 	var areas = document.querySelectorAll(\''.$recaptcha_textarea_selector.'\');
                // 	for(var i=0; i < areas.length; i++){
                // 	    areas[i].innerHTML = "'.$this->exts->recaptcha_answer.'";
                // 	}
                // ');
                // sleep(2);
                // $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $finding_callback = '
					if(document.querySelector("[data-callback]") != null){
						var cb = document.querySelector("[data-callback]").getAttribute("data-callback");
						cb;
					} 
				';
                $gcallbackFunction = $this->exts->execute_javascript($finding_callback);
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                $gcallbackFunction = $gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");';
                $this->exts->log($gcallbackFunction);
                $this->exts->execute_javascript($gcallbackFunction);
                sleep(7);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function process_hcaptcha_by_clicking()
    {
        $unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
        if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
            $this->exts->capture("hcaptcha");
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
                sleep(5);
            }
            // $this->exts->switchToDefault();
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
                $captcha_instruction = '';
                $old_height = $this->exts->execute_javascript('
					var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
					var old_height = wrapper.style.height;
					wrapper.style.height = "600px";
					old_height
				');
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
                if ($coordinates == '') {
                    $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
                }
                if ($coordinates != '') {
                    if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                        if (!empty($old_height)) {
                            $this->exts->execute_javascript('
								document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
							');
                        }

                        foreach ($coordinates as $coordinate) {
                            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }
                            $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                            // sleep(1);
                            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                                $this->exts->log('Error');
                                return;
                            }
                        }
                        $marked_time = time();
                        $this->exts->capture("hcaptcha-selected-" . $marked_time);

                        $wraper_side = $this->exts->execute_javascript('
							var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
							coo.width + "|" + coo.height;
						');
                        $wraper_side = explode('|', $wraper_side);
                        $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$wraper_side[0] - 50, (int)$wraper_side[1] - 30);

                        sleep(5);
                        $this->exts->capture("hcaptcha-submitted-" . $marked_time);
                        // $this->exts->switchToDefault();
                    }
                }
                // $this->exts->switchToDefault();
            }
            return true;
        }
        // $this->exts->switchToDefault();
        return false;
    }
    private function solve_login_hcaptcha()
    {
        sleep(5);
        $unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]';
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
        if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
                sleep(5);
            }

            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // Select language English always
                $wraper_side = $this->exts->execute_javascript('
					window.lastMousePosition = null;
					window.addEventListener("mousemove", function(e){
						window.lastMousePosition = e.clientX +"|" + e.clientY;
					});
					var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
					coo.width + "|" + coo.height;
				');

                $this->exts->log('Select English language ' . $wraper_side);
                $wraper_side = explode('|', $wraper_side);
                $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 20, (int)$wraper_side[1] - 71);
                sleep(1);
                $this->exts->type_key_by_xdotool('e');
                sleep(1);
                $this->exts->type_key_by_xdotool('Return');
                sleep(2);
            }

            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                sleep(5);
            }
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                sleep(5);
            }
            if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                $this->process_hcaptcha_by_clicking();
                $this->process_hcaptcha_by_clicking();
                sleep(5);
            }
            sleep(10);
            $this->exts->capture("2-after-solving-hcaptcha");
        }
    }
    // utils function solve bot detecting
    private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
        $selector = base64_encode($selector);
        $element_coo = $this->exts->execute_javascript('
			var x_on_element = ' . $x_on_element . '; 
			var y_on_element = ' . $y_on_element . ';
			var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
			// Default get center point in element, if offset inputted, out put them
			if(x_on_element > 0 || y_on_element > 0) {
				Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
			} else {
				Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
			}
			
		');
        // sleep(1);
        $this->exts->log("Browser clicking position: $element_coo");
        $element_coo = explode('|', $element_coo);

        $root_position = $this->exts->get_brower_root_position();
        $this->exts->log("Browser root position");
        print_r($root_position);

        $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
        $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
        $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        // move randomly
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
    }
    private function processClickCaptcha(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 90
    ) {
        $this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
        $response = '';
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $response = trim(end(explode("coordinates:", $output)));
                }
            }
        }
        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }
    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            if ($this->exts->urlContains('/webreauth')) {
                $this->exts->moveToElementAndClick('#identifierNext');
                sleep(6);
            }
            $this->googleCheckFillLogin();
            sleep(5);
            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }

            // Click next if confirm form showed
            $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->googleCheckTwoFactorMethod();

            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            $this->exts->capture("3-google-before-back-to-main-tab");
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function googleCheckFillLogin()
    {
        if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->exists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
            } else if ($this->exts->urlContains('/challenge/recaptcha')) {
                $this->googlecheckFillRecaptcha();
                $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->urlContains('challenge/pk')) {
            // $this->exts->type_key_by_xdotool('Return');
            // sleep(3);
            $this->exts->capture("2.0-google-cancel-security-usb");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-google-backed-methods-list");
            $login_with_pass_button = $this->exts->getElement('//div[contains(text(), "Enter your password")]', null, 'xpath');
            if ($login_with_pass_button != null) {
                try {
                    $this->exts->log('Click login_with_pass_button button');
                    $login_with_pass_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click login_with_pass_button button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$login_with_pass_button]);
                }
                sleep(10);
            }
        }

        if ($this->exts->exists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
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
            $this->exts->log(__FUNCTION__ . '::Google password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function googleCheckTwoFactorMethod()
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
        $this->exts->capture("2.0-before-check-two-factor-google");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->moveToElementAndClick('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn')) {
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Google two factor page found.");
        $this->exts->capture("2.1-two-factor-google");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
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
                $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                    $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                    sleep(1);
                }
                $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool("Return");
                }
                sleep(10);
                $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Google two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
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
    private function googlecheckFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);
            $url = reset(explode('?', $this->exts->getUrl()));
            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
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
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    // End GOOGLE login

   public function downloadInvoice($count = 0)
    {
        sleep(15);
        $this->exts->log("Begin download invoice ");
        try {
            $this->exts->waitTillPresent('tr a[href*=download]', 60);
        } catch (TypeError $e) {
            sleep(60);
            $this->exts->log('TypeError: ' . $e->getMessage());
        }

        $this->exts->capture("2-download-invoice");

        $invoices = array();

        $receipts = $this->exts->getElements("tbody > tr");
        foreach ($receipts as $receipt) {

            if ($this->exts->getElement('a[href*=download]', $receipt) != null) {
                $receiptDate = '';

                $receiptAmount = "";
                $receiptUrl = $this->exts->extract('a[href*=download]', $receipt, 'href');

                $receiptName = explode('?', $receiptUrl)[0];
                $receiptName = trim($receiptName, '/');
                $receiptName = end(explode('/', $receiptName));
                $receiptFileName = $receiptName . '.pdf';

                $this->exts->log($receiptUrl);

                $invoice = array(
                    'receiptName' => $receiptName,
                    'parsed_date' => '',
                    'receiptAmount' => $receiptAmount,
                    'receiptFileName' => $receiptFileName,
                    'receiptUrl' => $receiptUrl,
                );
                array_push($invoices, $invoice);
            }
        }

        if (count($invoices) == 0) {
            $this->exts->log(__FUNCTION__ . " No downloadable invoices found ");
            $this->exts->success();
        }
        foreach ($invoices as $i => $invoice) {
            $this->exts->log(print_r($invoice, true));
            $this->no_invoice = false;
            $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                if (trim($invoice['receiptName']) == '') $invoice['receiptName'] = basename($downloaded_file, '.pdf');
                $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
            }

            if ($this->restrictPages != 0 && $i >= 4) {
                // If restrictpages != 0 than don't download documents or payouts more than 10 if date is not possible in payout filter is there but in documents don't download more than 5 if restrictpages != 0	
                break;
            }
        }
    }
    function downloadPayout($count = 0, $page = 1)
    {
        $this->exts->log("Begin download payout ");
        sleep(15);
        $this->exts->capture("2-download-payout");

        $invoices = array();

        $receipts = $this->exts->getElements("tbody > tr");
        foreach ($receipts as $receipt) {
            $cells = $this->exts->getElements('td', $receipt);
            try {
                $receiptDate = trim($cells[4]->getAttribute('innerText'));
            } catch (\Exception $exception) {
                $receiptDate = null;
            }

            $receiptAmount = "";
            $receiptUrl = $this->exts->getElement('a[href*="/payouts"]', $cells[4])->getAttribute('href');

            $receiptNames = explode("/payouts/", $receiptUrl);
            $receiptName = end($receiptNames);
            $receiptFileName = $receiptName . '.pdf';

            $this->exts->log($receiptDate);
            $this->exts->log($receiptFileName);

            $this->exts->log($receiptUrl);
            $parsed_date = $this->exts->parse_date($receiptDate);
            $this->exts->log($parsed_date);

            $invoice = array(
                'receiptName'     => $receiptName,
                'parsed_date'     => $parsed_date,
                'receiptAmount'   => $receiptAmount,
                'receiptFileName' => $receiptFileName,
                'receiptUrl'      => $receiptUrl,
            );

            array_push($invoices, $invoice);
        }


        $newTab = $this->exts->openNewTab();
        foreach ($invoices as $i => $invoice) {
            $this->no_invoice = false;
            $this->exts->openUrl($invoice['receiptUrl']);
            sleep(5);
            if ($this->exts->exists('div[class*="ObjectList-more"] button')) {
                $this->exts->moveToElementAndClick('div[class*="ObjectList-more"] button');
                sleep(5);
            }
            $downloaded_file = $this->exts->download_current($invoice['receiptFileName'], 2);

            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
            }

            if ($this->restrictPages != 0 && $i >= 9) {
                // If restrictpages != 0 than don't download documents or payouts more than 10 if date is not possible in payout filter is there but in documents don't download more than 5 if restrictpages != 0	
                break;
            }
        }
        // close new tab too avoid too much tabs
        $this->exts->closeTab($newTab);

        if ((int)@$this->restrictPages == 0 && $this->exts->exists('div.Box-root div.Flex-direction--row a[href*="/payouts?starting_after="]')) {
            $this->exts->log("Download finish for Page - " . $page);
            $this->exts->moveToElementAndClick('div.Box-root div.Flex-direction--row a[href*="/payouts?starting_after="]');
            sleep(10);
            $page++;
            $this->downloadPayout(0, $page);
        }
    }
    function download_sales_invoice($page_count = 1)
    {
        sleep(10);
        $this->exts->capture("4-sale-invoices-page");

        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            $invoice_link = $this->exts->getElement('a[href*="/invoices/"]', $row);
            if ($invoice_link != null) {
                $invoiceUrl = $invoice_link->getAttribute("href");
                $invoiceName = explode(
                    '/',
                    array_pop(explode('/invoices/', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[0]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        $openNewTab = $this->exts->openNewTab();
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(2);
                // Click "Invoice as PDF"
                $this->exts->click_element('//button//*[contains(text(), "Rechnung")][contains(text(), "PDF")]|//button//*[contains(text(), "Invoice")][contains(text(), "PDF")]');
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
        $this->exts->closeTab($newTab);

        if ((int)@$this->restrictPages == 0 && $this->exts->exists('a[href*="/invoices?"][href*="starting_after="]') && $page_count < 20) {
            $this->exts->moveToElementAndClick('a[href*="/invoices?"][href*="starting_after="]');
            $page_count++;
            $this->download_sales_invoice($page_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
