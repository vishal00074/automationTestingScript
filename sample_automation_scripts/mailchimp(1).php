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

    // Server-Portal-ID: 411 - Last modified: 07.03.2025 14:41:40 UTC - User: 1

    public $homepage = 'https://www.mailchimp.com/';
    public $baseUrl = 'https://admin.mailchimp.com/';
    public $login_url = 'https://login.mailchimp.com/';
    public $last_init_url = "";
    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->last_init_url = isset($this->exts->config_array["last_init_url"]) ? trim($this->exts->config_array["last_init_url"]) : "";

        if (trim($this->last_init_url) != "" && !empty($this->last_init_url)) {
            $this->baseUrl = $this->last_init_url;
        }
        $this->exts->log('URL - ' . $this->baseUrl);

        $this->exts->openUrl($this->homepage);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // accept cookie
        if ($this->isExists('#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
            sleep(3);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedin()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->login_url);
            sleep(10);
            $this->checkFillLogin(1);
            sleep(10);
            // We Need Some Security Information => I’ll do this later
            if ($this->isExists('form[action="/account/profile/post-sec"] a[href*="/?referrer=%2F"]')) {
                $this->exts->moveToElementAndClick('form[action="/account/profile/post-sec"] a[href*="/?referrer=%2F"]');
                sleep(5);
            }

            $this->checkFillLogin(2);
            sleep(10);

            if ($this->isExists('form[action="/account/profile/post-sec"] a[href*="/?referrer=%2F"]')) {
                $this->exts->moveToElementAndClick('form[action="/account/profile/post-sec"] a[href*="/?referrer=%2F"]');
                sleep(5);
            }

            if ($this->isExists('a#onward')) {
                // Chat support is experiencing higher than normal wait times. We appreciate your patience.
                // Onward --- Remind me later
                $this->exts->moveToElementAndClick('a#onward');
                sleep(3);
                $this->exts->capture('after-click-ONWARD');
            }

            if ($this->isExists('button[data-mc-el="dismissMistierButton"]')) {
                $this->exts->moveToElementAndClick('button[data-mc-el="dismissMistierButton"]');
                sleep(10);
            }
            if ($this->isExists('button#confirm-button')) {
                // Is your account information current?
                // If you ever lose access to your account, we'll use this information to verify your identity.
                $this->exts->moveToElementAndClick('button#confirm-button');
                sleep(3);
                $this->exts->capture('after-click-confirm-info');
            }
            if ($this->isExists('nav ul[class*="rightNav-"] li nav > button')) {
                $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav > button');
                sleep(1);
            }
            if ($this->isExists('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')) {
                $labelText = trim($this->exts->getElement('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')->getAttribute('innerText'));
                if (stripos(strtolower($labelText), 'switch') !== false) {
                    $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)');
                    sleep(1);
                }
            }

            if ($this->isExists('ul.accountSelectBox li.account[data-account-name]') || $this->isExists('nav ul[class*="rightNav-"] li nav [class*="open-"] ul li a[class*="subPaneLink-"]')) {
                if ($this->isExists('nav ul[class*="rightNav-"] li nav [class*="open-"] ul li a[class*="subPaneLink-"]')) {
                    $account_selector = 'nav ul[class*="rightNav-"] li nav [class*="open-"] ul li:nth-child(1) a[class*="subPaneLink-"]';
                    if (!$this->isExists($account_selector) && $this->isExists('nav ul[class*="rightNav-"] li nav > button')) {
                        $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav > button');
                        sleep(1);

                        if ($this->isExists('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')) {
                            $labelText = trim($this->exts->getElement('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')->getAttribute('innerText'));
                            if (stripos(strtolower($labelText), 'switch') !== false) {
                                $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)');
                                sleep(1);
                            }
                        }
                        $this->exts->capture('3-multi-accounts-checking-');
                    }
                    $this->exts->moveToElementAndClick($account_selector);
                    sleep(10);
                    $this->checkFillLogin();
                    sleep(7);
                    $this->checkFillTwoFactor();
                } else {
                    $account_name = $this->exts->getElementsAttribute('ul.accountSelectBox li.account[data-account-name]', 'data-account-name')[0];
                    $this->exts->moveToElementAndClick('ul.accountSelectBox li.account[data-account-name="' . $account_name . '"]');
                    sleep(10);
                    $this->checkFillTwoFactor();
                }
            }

            $this->checkFillTwoFactor();
            sleep(5);
        }

        $this->processAfterlogin();
    }

    // Custom Exists function to check element found or not
    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function isLoggedin()
    {
        return $this->isExists('button [class*="avatar"]');
    }
    private function checkFillLogin($attempt_count = 1)
    {
        $username_selector = 'input[name="username"]';
        $password_selector = 'input[name="password"]';
        $submit_login_selector = '#login-form button[type="submit"]';
        if ($this->isExists($password_selector)) {
            sleep(3);
            $this->exts->capture("2-login-page-" . $attempt_count);

            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($username_selector);
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($password_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            if ($this->isExists('input[name="stay-signed-in"]:not(:checked)')) {
                $this->exts->click_by_xdotool('label[for="stay-signed-in"]');
            }
            sleep(4);
            $this->checkFillRecaptcha();

            $this->exts->capture("2-login-page-filled-" . $attempt_count);
            // $this->exts->click_element($submit_login_selector);
            $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($submit_login_selector)]);

            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div[class=\"error-container\"]');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if (strpos(strtolower($this->exts->extract("div[class='error-container']", null, 'innerText')), 'problem') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract("div[class='error-container']", null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found-" . $attempt_count);
        }
    }
    private function checkFillTwoFactor($attempt_count = 1)
    {
        $this->exts->capture("two-factor-checking-" . $attempt_count);
        if ($this->isExists('[data-mc-el="sms-request"] a.button[data-mc-el="sendTfaSms"]')) {
            $this->exts->moveToElementAndClick('[data-mc-el="sms-request"] a.button[data-mc-el="sendTfaSms"]');
            sleep(2);
        }
        if ($this->isExists('#login-verify-form .recover-email input[name="verification_type"]:checked')) {
            $this->exts->capture("two-factor-email-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('#login-verify-form #send-email-code-step1 div', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->moveToElementAndClick('#login-verify-form #send-email-code-step1 .send-email-code-button');
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->capture("two-factor-email-sent-clicked-" . $attempt_count);
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                $this->exts->moveToElementAndType('#login-verify-form .recover-email-input input[name="email_code"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('button.submit-verification-button');
                sleep(15);

                if (!$this->isExists('input[name="email_code"]')) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('#login-verify-form .recover-sms input[name="verification_type"]:checked')) {
            $this->exts->capture("two-factor-sms-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('#login-verify-form #send-sms-code-step1 div', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->moveToElementAndClick('#login-verify-form #send-sms-code-step1 .send-sms-code-button');
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->capture("two-factor-send-clicked-" . $attempt_count);
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType('#login-verify-form  .sms-input input[name="sms_code"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('button.submit-verification-button');
                sleep(15);

                if ($this->exts->getElement('input[name="sms_code"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('#login-verify-form .recover-question input[name="verification_type"]:checked')) {
            $this->exts->capture("two-factor-security-question-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('#login-verify-form .question-input:not(.hide) p', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor " . $two_factor_code);
                $this->exts->moveToElementAndType('#login-verify-form input[name="question_answer"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('button.submit-verification-button');
                sleep(15);

                if ($this->exts->getElement('input[name="question_answer"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('#login-verify-form .recover-totp input[name="verification_type"]:checked')) {
            $this->exts->capture("two-factor-authen-app-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim(join(' ', $this->exts->getElementsAttribute('#login-verify-form .recover-totp, .totp-input p', 'innerText')));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType('#login-verify-form input[name="totp_code"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('button.submit-verification-button');
                sleep(15);

                if ($this->exts->getElement('input[name="totp_code"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('form[action*="/tfa-post"] input[name="totp-token"]')) {
            $this->exts->capture("two-factor-extra-authen-app-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim(join($this->exts->extract('form[action*="/tfa-post"] fieldset:not(.hide) p', null, 'innerText'), $this->exts->getElementsAttribute('//form[contains(@action, "/tfa-post")]/preceding-sibling::p', 'innerText', null, 'xpath')));
            $this->exts->two_factor_notif_msg_en = str_replace('You can also use your backup code', '', $this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType('form[action*="/tfa-post"] input[name="totp-token"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('form[action*="/tfa-post"] [type="submit"]');
                sleep(15);

                if ($this->exts->getElement('form[action*="/tfa-post"] input[name="totp-token"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('form[action*="/tfa-post"] input[name="sms-code"]')) {
            $this->exts->capture("two-factor-extra-sms-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim(join($this->exts->extract('form[action*="/tfa-post"] fieldset:not(.hide) p', null, 'innerText'), $this->exts->getElementsAttribute('//form[contains(@action, "/tfa-post")]/preceding-sibling::p', 'innerText', null, 'xpath')));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType('form[action*="/tfa-post"] input[name="sms-code"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('form[action*="/tfa-post"] [type="submit"]');
                sleep(15);

                if ($this->exts->getElement('form[action*="/tfa-post"] input[name="sms-code"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        } else if ($this->isExists('form[action*="/account/profile/verify-sms-post"] input[name="sec_answer"]')) {
            $this->exts->capture("verify-sms-post-" . $attempt_count);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('form[action*="/account/profile/verify-sms-post"] label[for="sec-answer"] ', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            sleep(3);
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType('form[action*="/account/profile/verify-sms-post"] input[name="sec_answer"]', $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("two-factor-filled-" . $this->exts->two_factor_attempts . "-" . $attempt_count);

                $this->exts->moveToElementAndClick('form[action*="/account/profile/verify-sms-post"] input[type="submit"]');
                sleep(15);

                if ($this->exts->getElement('form[action*="/account/profile/verify-sms-post"] input[name="sec_answer"]') == null) {
                    $this->exts->log("Two factor solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }

        $this->exts->capture('after-checking-2FA-' . $attempt_count);
        if ($this->isExists('#av-flash-errors') && $attempt_count < 3) {
            // wait some times before request resend code ortherwise click resend won't work
            sleep(60);
            // 2FA code is wrong/expired => request resend 2FA code
            if ($this->isExists('.resend-email-code-link')) {
                $this->exts->moveToElementAndClick('.resend-email-code-link');
            } else if ($this->isExists('.resend-sms-code-link')) {
                $this->exts->moveToElementAndClick('.resend-sms-code-link');
            }

            sleep(3);
            $this->exts->capture('after-click-reend-2FA-' . $attempt_count);
            $attempt_count++;
            $this->checkFillTwoFactor($attempt_count);
        }
    }
    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha(trim($this->exts->getUrl()), $data_siteKey, false);
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
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ }console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ }
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; 
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                found ? "___grecaptcha_cfg.clients[0]." + result : null;
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

    private function processAfterlogin()
    {
        sleep(3);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        if ($this->isExists('button[data-mc-el="dismissMistierButton"]')) {
            $this->exts->moveToElementAndClick('button[data-mc-el="dismissMistierButton"]');
            sleep(10);
        }
        if ($this->isExists('button#confirm-button')) {
            // Is your account information current?
            // If you ever lose access to your account, we'll use this information to verify your identity.
            $this->exts->moveToElementAndClick('button#confirm-button');
            sleep(5);
            $this->exts->capture('after-click-confirm-info');
            $this->checkFillTwoFactor();
        }
        if ($this->isExists('nav ul[class*="rightNav-"] li nav > button')) {
            $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav > button');
            sleep(1);
        }
        if ($this->isExists('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')) {
            $labelText = trim($this->exts->getElement('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)')->getAttribute('innerText'));
            if (stripos(strtolower($labelText), 'switch') !== false) {
                $this->exts->moveToElementAndClick('nav ul[class*="rightNav-"] li nav button[class*="item--desktop"]:nth-child(2)');
                sleep(1);
            }
        }
        // Mailchimp has updated our Terms, effective 11/23/2020. If you continue to use Mailchimp, you agree to Accept the updated Terms.
        if ($this->isExists('.roadblock a#onward, .roadblock.lastUnit a[href*="/roadblock/continue"]')) {
            $this->exts->moveToElementAndClick('.roadblock a#onward, .roadblock.lastUnit a[href*="/roadblock/continue"]');
            sleep(3);
        }
        $this->exts->moveToElementAndClick('button#account-settings-btn');
        sleep(3);
        if ($this->isExists('li[id="switch account"]')) {
            $this->exts->moveToElementAndClick('li[id="switch account"]');
            sleep(3);
        }
        $this->exts->capture('3-multi-accounts-checking');
        if ($this->isExists('li[class*="accountItem"]')) {
            $accounts = $this->exts->getElementsAttribute('li[class*="accountItem"]', 'innerText');
            foreach ($accounts as $key => $account_name) {
                $this->exts->log("SWITCH ACCOUNT " . $account_name);
                $account_selector = 'li[class*="accountItem"]:nth-child(' . ($key + 1) . ')';
                if (!$this->isExists($account_selector) && $this->isExists('button#account-settings-btn')) {
                    $this->exts->moveToElementAndClick('button#account-settings-btn');
                    sleep(3);
                    if ($this->isExists('li[id="switch account"]')) {
                        $this->exts->moveToElementAndClick('li[id="switch account"]');
                        sleep(3);
                    }
                    $this->exts->capture('3-multi-accounts-checking-' . $key);
                }
                $this->exts->moveToElementAndClick($account_selector);
                sleep(10);

                // Open invoices url
                $paths = explode('/', $this->exts->getUrl());
                $currentDomainUrl = $paths[0] . '//' . $paths[2];
                $invoicePageUrl = $currentDomainUrl . "/account/billing-history/";
                $this->exts->openUrl($invoicePageUrl);

                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else if ($this->isLoggedin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url
            $paths = explode('/', $this->exts->getUrl());
            $currentDomainUrl = $paths[0] . '//' . $paths[2];
            $invoicePageUrl = $currentDomainUrl . "/account/billing-history/";
            $this->exts->openUrl($invoicePageUrl);

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->exts->getElement('.feedback-block.error.section, #login-form .feedback-block.error p a[href*="forgot"]') != null) {
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->getUrl(), '/login/sec-update') !== false || strpos($this->exts->getUrl(), '/account/billing-adjust-plan/?source=suggestPlan') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($pageCount = 1)
    {
        sleep(15);
        if ($this->isExists('div#onetrust-close-btn-container button.onetrust-close-btn-handler')) {
            $this->exts->moveToElementAndClick('div#onetrust-close-btn-container button.onetrust-close-btn-handler');
            sleep(5);
        }
        $this->exts->capture("4-invoice-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) < 2 || $this->exts->getElement('a[href*="billing-receipt"]', $row) == null) {
                continue;
            }

            $invoiceUrl = $this->exts->getElement('a[href*="billing-receipt"]', $row)->getAttribute("href");
            $invoiceName = explode(
                '&',
                array_pop(explode('id=', $invoiceUrl))
            )[0];

            $invoiceDate = '';

            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText')));
            if (stripos($tags[0]->getAttribute('innerText'), 'A$') !== false) {
                $invoiceAmount = $invoiceAmount . ' AUD';
            } else if (stripos($tags[0]->getAttribute('innerText'), '$') !== false) {
                $invoiceAmount = $invoiceAmount . ' USD';
            } else if (stripos(urlencode($tags[0]->getAttribute('innerText')), '%C2%A3') !== false) {
                $invoiceAmount = $invoiceAmount . ' GBP';
            } else {
                $invoiceAmount = $invoiceAmount . ' EUR';
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

        $newTab = $this->exts->openNewTab();
        foreach ($invoices as $invoice) {
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->log('Dowloading invoice ' . $count . '/' . $totalFiles);

                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(3);
                $this->exts->waitTillPresent('div[class*="printButton"]');

                $downloaded_file = $this->exts->download_current($invoiceFileName);

                // sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                    $count++;
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        }

        // close tab
        $this->exts->closeTab($newTab);

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $pageCount < 50 && $this->exts->getElement('div[data-testid="pagination"] button:nth-child(3) svg:not([class*="disable"])') != null) {
            $pageCount++;
            $this->exts->moveToElementAndClick('div[data-testid="pagination"] button:nth-child(3) svg:not([class*="disable"])');
            sleep(3);
            $this->processInvoices($pageCount);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
