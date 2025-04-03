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

    // Server-Portal-ID: 26981 - Last modified: 18.03.2025 13:48:01 UTC - User: 1

    // Script here
    public $baseUrl = "https://www.orange.fr/portail";
    public $loginUrl = "https://login.orange.fr/?return_url=https://www.orange.fr/portail";
    public $homePageUrl = "https://espaceclientv3.orange.fr/?page=factures-accueil";
    public $username_selector = "input#login";
    public $password_selector = "input#password";
    public $submit_button_selector = "button#btnSubmit";
    public $login_tryout = 0;
    public $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
    public $captcha_form_selector = 'div[class*="captcha_images"]'; //'[id="captchaRow"] form[name="captcha-form"]';
    public $captcha_image_selector = 'ul.uya65w-4 li';
    public $captcha_image_selector_1 =  'ul#captcha-images li';
    public $captcha_submit_btn_selector = 'button.sc-gKsewC, button#login-submit-button';
    public $captcha_indications_selector = 'ul.uya65w-0.eCJhHZ li';
    public $lang_code = 'fr';
    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        $user_agent = $this->exts->executeSafeScript('return navigator.userAgent;');
        $this->exts->log('user_agent: ' . $user_agent);

        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        //This is not needed because profile get loaded without calling any function

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->clearChrome();
            // sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                sleep(10);
            }

            for ($i = 0; $i < 3; $i++) {
                if ($this->exts->exists($this->captcha_form_selector)) {
                    $this->solveClickCaptcha();
                    sleep(10);
                } else {
                    break;
                }
            }

            if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                sleep(10);
            }
        }

        $this->exts->capture('before-fill-form');

        if (!$isCookieLoginSuccess) {
            for ($i = 0; $i < 3; $i++) {
                if ($this->exts->exists($this->captcha_form_selector)) {
                    $this->solveClickCaptcha();
                    sleep(10);
                } else {
                    break;
                }
            }

            if (!$this->exts->exists($this->username_selector)) {
                $this->exts->openUrl($this->loginUrl);
                sleep(10);

                if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                    $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                    sleep(10);
                }

                for ($i = 0; $i < 3; $i++) {
                    if ($this->exts->exists($this->captcha_form_selector)) {
                        $this->solveClickCaptcha();
                        sleep(10);
                    } else {
                        break;
                    }
                }
            }

            // $this->exts->capture("after-login-clicked");

            if (!$this->exts->exists($this->username_selector) && $this->exts->exists('div p#accountLogin') && $this->exts->exists('a.link-action-password')) {
                $this->exts->moveToElementAndClick("a.link-action-password");
                sleep(15);
            }

            if ($this->exts->exists('a[id*="choose-account"]')) {
                $this->exts->moveToElementAndClick('a[id*="choose-account"]');
                sleep(15);
            }

            $this->fillForm(0);
            sleep(10);
            for ($i = 0; $i < 3 && $this->exts->exists('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]'); $i++) {
                $this->exts->moveToElementAndClick('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]');
                sleep(10);
                $this->fillForm(0);
                sleep(10);
            }

            if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                sleep(10);
            }

            if ($this->exts->getElement("button#o-cookie-ok") != null) {
                $this->exts->moveToElementAndClick('button#o-cookie-ok');
            }

            if ($this->exts->getElement("#o-cookie-consent-ok") != null) {
                $this->exts->moveToElementAndClick('#o-cookie-consent-ok');
            }

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);


            $this->checkFillTwoFactor();

            if ($this->exts->exists('button[data-testid="link-mc-later')) {
                $this->exts->moveToElementAndClick('button[data-testid="link-mc-later');
                sleep(20);
            }

            if ($this->exts->exists('button[data-oevent-action="clic_lien_plus_tard"]')) {
                $this->exts->moveToElementAndClick('button[data-oevent-action="clic_lien_plus_tard"]');
                sleep(20);
            }

            $err_txt1 = "";
            if ($this->exts->getElement("h6#error-msg-box") != null) {
                $err_txt1 = $this->exts->getElement("h6#error-msg-box")->getAttribute('innerText');
            }

            $err_txt2 = "";
            if ($this->exts->getElement("span#default_password_error, label#password-invalid-feedback") != null) {
                $err_txt1 = $this->exts->getElement("span#default_password_error, label#password-invalid-feedback")->getAttribute('innerText');
            }

            if (($err_txt1 != "" && $err_txt1 != null) || ($err_txt2 != "" && $err_txt2 != null)) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            }

            if (strpos($this->exts->getUrl(), '/changePassword') !== false) {
                $this->exts->log('Your current password is not secure enough and needs to be strengthened. ');
                $this->exts->capture('new_password!');
                $this->exts->account_not_ready();
            }

            if ($this->exts->getElement('input#new-password') != null && $this->exts->getElement('input#new-password') != null) {
                $this->exts->log('User must update new password');
                $this->exts->capture('User must update new password');
                $this->exts->account_not_ready();
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                if ($this->exts->urlContains('recovery/error')) {
                    $this->exts->account_not_ready();
                }
                if ($this->exts->exists('p#password-error-title-error')) {
                    $this->exts->loginFailure(1);
                } else if ($this->exts->urlContains('/renforcer-mot-de-passe')) {
                    $this->exts->account_not_ready();
                } elseif ($this->exts->urlContains('mdp/choice/default')) {
                    $this->exts->account_not_ready();
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
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
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
        sleep(1);
        $this->exts->capture("clear-page");
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    public $isLoginByCookie = false;
    function fillForm($count)
    {
        $this->exts->capture("1-pre-login");
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;

                if ($this->exts->getElement($this->username_selector) != null) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);

                if ($this->exts->getElementByText('div#login-error', ['cette adresse e-mail', 'Cette adresse mail ou ce numéro de mobile n’est pas valide. Vérifiez votre saisie'], null, false) != null) {
                    $this->exts->loginFailure(1);
                }

                if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]')) {
                    $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]');
                    sleep(16);
                }

                if ($this->exts->exists('button[data-testid="submit-mc"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="submit-mc"]');
                    sleep(3);
                    $this->checkFillTwoFactorForMobileAcc();
                }

                $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
                sleep(10);

                $this->checkFillTwoFactor();

                if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                    $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                    sleep(15);
                }

                if ($this->exts->getElement($this->password_selector) != null) {
                    if ($this->exts->getElement($this->password_selector) != null && $this->exts->getElement($this->password_selector) != null) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(5);
                    }

                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);

                    if ($this->exts->exists($this->password_selector)) {
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(2);

                        $this->exts->moveToElementAndClick($this->submit_button_selector);

                        sleep(10);
                    }
                } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
                    $this->isLoginByCookie = true;
                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);
                } else {
                    $temp = "";
                    if ($this->exts->getElement("h6#error-msg-box-login") != null) {
                        $temp = $this->exts->getElement("h6#error-msg-box-login")->getAttribute('innerText');
                    }

                    if ($temp != "" && $temp != null) {
                        $this->exts->capture("LoginFailed");
                        $this->exts->loginFailure(1);
                    }
                }
            } else if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);

                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);
                }
            } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
                $this->isLoginByCookie = true;
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            } else if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"]')) {
                $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"]');
                sleep(15);

                $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
                sleep(10);

                $this->checkFillTwoFactor();

                if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                    $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                    sleep(15);
                }

                $this->exts->log('enter password');
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);

                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);
                }
            } else {
                $temp = $this->exts->extract('h6#error-msg-box-login', null, 'innerText');
                if ($temp != "" && $temp != null) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                }
            }

            sleep(10);

            if ($this->exts->exists('div.promoteMC-container a#btnLater')) {
                $this->exts->moveToElementAndClick('div.promoteMC-container a#btnLater');
                sleep(15);
            }

            if ($this->exts->exists('a[data-oevent-action="clic_lien_plus_tard"]')) {
                $this->exts->moveToElementAndClick('a[data-oevent-action="clic_lien_plus_tard"]');
                sleep(14);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#otc-input, input#otc';
        $two_factor_message_selector = '#otc-form #otcLabel, #otc-form #helpCard, form h3 + p';
        $two_factor_submit_selector = '#otc-form #btnSubmit, button[data-testid="submit-otc"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

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
        }
    }

    private function checkFillTwoFactorForMobileAcc()
    {
        $this->exts->log('start checkFillTwoFactorForMobileAcc');
        $two_factor_selector = '';
        $two_factor_message_selector = 'span.icon-Internet-security-mobile + div';
        $two_factor_submit_selector = '';

        if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
            if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
                $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(15);
                if ($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('button[data-testid="btn-mc-error"]')) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    if ($this->exts->exists('button[data-testid="btn-mc-error"]')) {
                        $this->exts->moveToElementAndClick('button[data-testid="btn-mc-error"]');
                        sleep(3);
                    }
                    $this->checkFillTwoFactorForMobileAcc();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    function processTFA_SMS()
    {
        try {
            $this->exts->log("Current URL - " . $this->exts->getUrl());

            if ($this->exts->getElement($this->twofa_form_selector) != null) {
                $this->handleTwoFactorCode($this->twofa_form_selector, "form#otpForm button[type=\"submit\"]");
                sleep(5);
            }
            if ($this->exts->getElement($this->twofa_form_selector) != null) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception process TFA " . $exception->getMessage());
        }
    }

    function processTFA_NUM($contractId)
    {
        try {
            $this->exts->log("Current URL - " . $this->exts->getUrl());

            $two_factor_selector = "div.contratCalcule_" . $contractId . " input[name=\"clientReference\"]";
            $submit_btn_selector = "div[class=\"buttons contratCalcule contratCalcule_" . $contractId . "\"] button[type=\"submit\"]";
            if ($this->exts->getElement($two_factor_selector) != null) {
                $this->handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId);
                sleep(5);
            }
            if ($this->exts->getElement($two_factor_selector) != null) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception process TFA " . $exception->getMessage());
        }
    }

    function handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId)
    {
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
        }

        if ($this->exts->two_factor_attempts == 1) {
            if ($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]") != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
                $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
            }
        }

        $this->exts->log($this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            try {
                $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                if ($this->exts->getElement($submit_btn_selector) != null && $this->exts->getElement($submit_btn_selector)->isEnabled()) {
                    $this->exts->getElement($submit_btn_selector)->click();
                    sleep(10);
                } else {
                    sleep(5);
                    $this->exts->getElement($submit_btn_selector)->click();
                    sleep(10);
                }

                if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
                }
            } catch (\Exception $exception) {
                $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            }
        }
    }

    function handleTwoFactorCode($two_factor_selector, $submit_btn_selector)
    {
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
        }

        if ($this->exts->two_factor_attempts == 1) {
            if ($this->exts->getElement("div.addContractFormContent p.ec_form_line + div") != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
                $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
            }

            if ($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]") != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
            }
        }

        $this->exts->log($this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            try {
                $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                if ($this->exts->getElement($submit_btn_selector) != null && $this->exts->getElement($submit_btn_selector)->isEnabled()) {
                    $this->exts->getElement($submit_btn_selector)->click();
                    sleep(10);
                } else {
                    sleep(5);
                    $this->exts->getElement($submit_btn_selector)->click();
                    sleep(10);
                }

                if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
                }
            } catch (\Exception $exception) {
                $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            }
        }
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        sleep(10);
        try {
            if ($this->exts->execute_javascript("document.querySelector('header#o-header elcos-header').shadowRoot.querySelector('button[data-oevent-action=\"espaceclient\"]') != null") == true) {
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public $captca_solution_tried = 0;
    function solveClickCaptcha()
    {
        $this->exts->log("Start solving click captcha:");
        if ($this->exts->exists($this->captcha_form_selector)) {
            $this->exts->capture("solveClickCaptcha");
            $retry_count = 0;
            while ($retry_count < 5) {
                // $indications = str_replace("?", " ", $this->exts->extract($this->captcha_indications_selector, null, 'innerText'));
                $indicationsArray = array();
                $indications_sel = $this->exts->getElements('ol[class*="timeline-captcha"] li');
                foreach ($indications_sel as $key => $indication_sel) {
                    $temp = $indication_sel->getAttribute('innerText');
                    $temp = trim($temp);
                    $this->exts->log($temp);
                    array_push($indicationsArray, $temp);
                }
                $hcaptcha_challenger_wraper_selector = 'div[class*="captcha_images"]';
                $translatedIndication = "";
                foreach ($indicationsArray as $key => $indication) {
                    $translatedIndication = $translatedIndication . ($key + 1) . '-' . $this->getTranslatedClickCaptchaInstruction($indication) . '.';
                }
                $this->exts->log("translatedIndications " . $translatedIndication);
                $captcha_instruction = "Click on the image in this order." . $translatedIndication;
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
                $call_2captcha_retry = 0;
                while (($coordinates == '' || count($coordinates) != 6) && $call_2captcha_retry < 5) {
                    $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
                    $call_2captcha_retry++;
                }
                if ($coordinates != '') {
                    foreach ($coordinates as $coordinate) {
                        $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }
                    $this->exts->capture("After captcha clicked.");
                }
                $retry_count++;
                $this->captca_solution_tried++;

                $this->exts->capture('after-click-all-images');

                if ($this->exts->exists('div.justify-content-sm-start button[type="button"]')) {
                    $this->exts->moveToElementAndClick('div.justify-content-sm-start button[type="button"]');
                    sleep(15);
                }

                $this->exts->capture('after-solve-clickcaptcha');
                if (!$this->exts->exists($this->captcha_form_selector)) {
                    $this->exts->log("Captcha solved!!!!!! About to continue process...");
                    break;
                } else {
                    $this->exts->log("Captcha not solved!!!!!! Refresh to retry...");
                    $this->exts->refresh();
                    sleep(10);
                    if (!$this->exts->exists($this->captcha_form_selector)) {
                        break;
                    }
                }
            }
        } else {
            $this->exts->log("Captcha not found!!!!!!");
        }
    }
    private function processClickCaptcha(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
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
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }

    function getTranslatedClickCaptchaInstruction($originalInstruction)
    {
        $result = null;
        try {
            $this->exts->openNewTab();
            sleep(1);
            $originalInstruction = preg_replace("/\r\n|\r|\n/", '%0A', $originalInstruction);
            $this->exts->log('originalInstruction: ' . $originalInstruction);
            $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
            sleep(3);

            $acceptBtn = $this->exts->getElementByText('button', ['Agree to the use of cookies', 'Accept all'], null, false);
            if ($acceptBtn != null) {
                $acceptBtn->click();
                sleep(12);
                $this->exts->switchToInitTab();
                $this->exts->closeAllTabsButThis();
                $this->exts->openNewTab();
                sleep(1);
                $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
                sleep(3);
            }
            // sleep(10);
            $result = $this->exts->extract('div c-wiz:nth-child(2) span[lang="en"] > span > span', null, 'innerText');
            $result = str_replace('%0A', "\n", $result);
            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();
        } catch (\Exception $ex) {
            $this->exts->log("Failed to get translated instruction");
        }

        return $result;
    }

    function invoicePage()
    {
        $this->exts->log("invoice Page");
        $this->exts->config_array['lang_code'] = 'fr';

        $currentURL = $this->exts->getUrl();

        $this->exts->openUrl('https://espaceclientv3.orange.fr/maf.php?urlOk=https%3A%2F%2Fespaceclientv3.orange.fr%2F%3Fpage%3Dfactures-accueil&applicationUnivers=n/a&cd=U&idContrat=&lineNumber=&bodyLineNumber=');
        sleep(15);
        $this->exts->capture("Contract-page");

        $contracts = array();
        if ($this->exts->getElement(".nec-content.container div.ec-panelAuthFrontMod .ec-contractPanel") != null) {
            $accs = $this->exts->getElements(".nec-content.container div.ec-panelAuthFrontMod .ec-contractPanel a[href*=\"contract=\"]");
            foreach ($accs as $acc) {
                $contract = $acc->getAttribute('href');
                $contract = trim(explode("&", end(explode("contract=", $contract)))[0]);
                $url = 'https://espaceclientv3.orange.fr/?page=factures-historique&idContrat=' . $contract;
                $con = array(
                    'url' => $url,
                );

                array_push($contracts, $con);
            }

            foreach ($contracts as $acc) {
                $this->exts->log($acc['url']);
            }

            if (count($contracts) == 0) {
                $myInvoiceUrl = "";
                if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                    $myInvoiceUrl = $this->exts->getElement("a[href*=\"page=factures-historique\"]")->getAttribute("href");
                }

                if ($myInvoiceUrl != null && $myInvoiceUrl != "") {
                    $this->exts->openUrl($myInvoiceUrl);
                    sleep(15);
                    $this->downloadInvoice();
                } else {
                    $this->downloadInvoice();
                }
            } else {
                foreach ($contracts as $acc) {
                    $this->exts->openUrl($acc['url']);
                    sleep(15);
                    $this->downloadInvoice();
                }
            }
        } else {
            if (strpos($this->exts->getUrl(), "/contract/") !== false) {
                $this->selectYear();
            }
            if ($this->exts->exists('a[href*="/historique-des-factures"]')) {
                $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                sleep(15);
                $this->downloadInvoice();
            } else {
                $this->exts->openUrl("https://espaceclientv3.orange.fr");
                sleep(15);

                if ($this->exts->getElement("a.espace-client-left") != null) {
                    $this->exts->moveToElementAndClick("a.espace-client-left");
                    sleep(15);
                }
                $this->invoicePage1();
            }
        }

        if ($this->exts->exists('a[href*="/factures-paiement"]')) {
            $this->exts->moveToElementAndClick('a[href*="/factures-paiement"]');
            sleep(15);
        } else if ($this->exts->exists('ul#localNav1 a[href*="/factures-paiement"]')) {
            $this->exts->moveToElementAndClick('ul#localNav1 a[href*="/factures-paiement"]');
            sleep(15);

            $this->exts->moveToElementAndClick('a[href="/factures"]');
            sleep(15);
        }

        if ($this->exts->exists('ecm-carrousel-nav li a[href*="facture-paiement"]')) {
            $this->exts->moveToElementAndClick('ecm-carrousel-nav li a[href*="facture-paiement"]');
            sleep(3);
            $this->exts->waitTillPresent('a[data-e2e*="-historic"]');
            $this->exts->moveToElementAndClick('a[data-e2e*="-historic"]');
            sleep(3);

            $this->processFacturePaiement();
        }

        $this->exts->openUrl('https://espaceclientpro.orange.fr/contracts');
        sleep(3);
        $this->exts->waitTillPresent('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker, a#access-bills');
        $contracts_len = count($this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker'));
        $this->exts->log('Totoal Contracts - ' . $contracts_len);
        if ($contracts_len > 0) {
            $contract_url = $this->exts->getUrl();
            for ($i = 0; $i < $contracts_len; $i++) {
                $contractBtn = $this->exts->getElements('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker')[$i];
                // if ($contractBtn == null) continue;
                try {
                    $contractBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click()', [$contractBtn]);
                }
                sleep(3);
                $this->exts->waitTillPresent('a#access-bills');
                $this->exts->moveToElementAndClick('a#access-bills');

                $this->processProAccLatestInvoice();

                $this->selectTabInvoiceYears();
                $this->exts->openUrl($contract_url);
                sleep(3);
                $this->exts->waitTillPresent('.contracts-list ul.items-list li a#item-list-button-linker, .contracts-list ul.items-list li a.item-list-button-linker');
            }
        } else {
            $this->exts->moveToElementAndClick('a#access-bills');

            $this->processProAccLatestInvoice();

            $this->selectTabInvoiceYears();
        }

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice!!!!");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    function invoicePage1()
    {
        $this->exts->log("invoice Page");

        $contracts = array();
        if ($this->exts->getElement("#contractContainer > .listeItem > a") != null) {
            $accs =  $this->exts->getElements("#contractContainer > .listeItem > a");
            if (count($accs) == 0) {
                $accs =  $this->exts->getElements("a[href*=\"contract=\"]");
            }
            foreach ($accs as $acc) {
                $contract = $acc->getAttribute('id');
                $contract = trim(end(explode("-", $contract)));
                $this->exts->log("Id contract: " . $contract);
                $url = 'https://espaceclientv3.orange.fr/?page=factures-historique&idContrat=' . $contract;
                $con = array(
                    'url' => $url,
                );

                array_push($contracts, $con);
            }

            foreach ($contracts as $acc) {
                $this->exts->log($acc['url']);
            }

            if (count($contracts) == 0) {
                $myInvoiceUrl = "";
                if ($this->exts->getElement("a[href*=\"page=factures-historique\"]") != null) {
                    $myInvoiceUrl = $this->exts->getElement("a[href*=\"page=factures-historique\"]")->getAttribute("href");
                }

                if ($myInvoiceUrl != null && $myInvoiceUrl != "") {
                    $this->exts->openUrl($myInvoiceUrl);
                    sleep(15);
                    $this->downloadInvoice();
                } else {
                    $this->downloadInvoice();
                }
            } else {
                foreach ($contracts as $acc) {
                    $this->exts->openUrl($acc['url']);
                    sleep(15);
                    $this->downloadInvoice();
                }
            }

            if ($this->totalFiles == 0) {
                $this->exts->log("No invoices");
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (strpos($this->exts->getUrl(), "/contract/") !== false) {
                $this->selectYear();
            }
        }
    }

    public function translate_date_abbr($date_str)
    {
        for ($i = 0; $i < count($this->month_names_fr); $i++) {
            if (stripos($date_str, $this->month_names_fr[$i]) !== FALSE) {
                $date_str = str_replace($this->month_names_fr[$i], $this->exts->month_abbr_en[$i], $date_str);
                break;
            }
        }
        return $date_str;
    }

    function downloadInvoice()
    {
        $this->exts->log("Begin download invoice");
        $this->exts->capture('4-1-List-invoices');

        try {
            if ($this->exts->getElement('div.ec-tableBillHistory table tbody tr') != null) {
                $receipts = $this->exts->getElements('div.ec-tableBillHistory table tbody tr');
                $invoices = array();
                foreach ($receipts as $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 4 && $this->exts->getElement('td a[href*="&idFacture="]', $receipt) != null) {
                        $receiptDate = $tags[0]->getAttribute('innerText');
                        $receiptDate = $this->translate_date_abbr(strtolower($receiptDate));
                        $receiptUrl = $this->exts->extract('td a[href*="&idFacture="]', $receipt, 'href');
                        $idContrat = trim(explode("&", end(explode("idContrat=", $receiptUrl)))[0]);
                        $receiptName = $this->exts->extract('td a[href*="&idFacture="] span.ec_visually_hidden', $receipt);
                        $receiptName = trim(explode("(", end(explode(" du ", $receiptName)))[0]);
                        $receiptName = $idContrat . '_' . str_replace('/', '', $receiptName);
                        $receiptFileName = $receiptName . '.pdf';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j M Y', 'Y-m-d');
                        $receiptAmount = $tags[1]->getAttribute('innerText');
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                        $this->exts->log($receiptAmount);

                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice URL: " . $receiptUrl);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice parsed_date: " . $parsed_date);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));

                foreach ($invoices as $invoice) {
                    $this->totalFiles += 1;
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            } else {
                if ($this->exts->getElement("div#historical-bills-container > div") != null) {
                    $this->selectYear();
                } else {
                    $this->downloadInvoiceNewStyle();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
    function downloadInvoiceNewStyle()
    {
        $rows = count($this->exts->getElements('bills-history table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('bills-history table > tbody > tr')[$i];
            if ($row == null) continue;
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 3 && $this->exts->getElement('a[data-e2e="bh-iconPdf"], [data-e2e*="bh-bill-iconPdf"]', $row) != null) {
                $download_button = $this->exts->getElement('a[data-e2e="bh-iconPdf"], [data-e2e*="bh-bill-iconPdf"]', $row);
                if ($download_button == null) continue;
                $this->totalFiles += 1;
                $invoiceName = '';
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->config_array["lang_code"] = 'fr';
                $parsed_date = $this->exts->parse_date($invoiceDate, 'j F Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                try {
                    $this->exts->log('Click PDF button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(3);
                if ($this->exts->exists('button[data-e2e="download-link"]')) {
                    $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
                    sleep(5);
                }
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = explode('.pdf', $invoiceFileName)[0];
                    $invoiceName = explode('(', $invoiceName)[0];
                    $invoiceName = preg_replace("/[^\w\-]/", '', $invoiceName);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = $invoiceName . '.pdf';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    }
                } else {
                    $this->exts->log('Timeout when download ');
                }

                if ($this->exts->exists('pdf-display-modal button.close')) {
                    $this->exts->moveToElementAndClick('pdf-display-modal button.close');
                    sleep(1);
                }
                if ($this->exts->exists('a[href*="/historique-des-factures"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/historique-des-factures"]');
                    sleep(3);
                }
            }
        }
    }
    public $totalFiles = 0;
    function selectYear()
    {
        $this->exts->log("select Year");

        if ($this->exts->getElement("div#bill-archive ul.nav-tabs li span") != null) {
            $count_years = count($this->exts->getElements("div#bill-archive ul.nav-tabs li span"));

            for ($i = 0; $i < $count_years; $i++) {
                $sel_y = "div#bill-archive ul.nav-tabs li:nth-child(" . ($i + 1) . ") span";

                $this->exts->moveToElementAndClick($sel_y);
                sleep(5);
                $this->downloadInvoiceV1();
            }
        } else {
            $this->downloadInvoiceV1();
        }
    }
    function downloadInvoiceV1()
    {
        $this->exts->log("Begin downlaod invoice 1");
        $currentURL = $this->exts->getUrl();

        try {
            if ($this->exts->getElement("div#bill-archive div#historical-bills-container div.bill-separation") != null) {
                $invoices = array();
                $receipts = $this->exts->getElements('div#bill-archive div#historical-bills-container div.bill-separation.row');
                $this->exts->log(count($receipts));
                foreach ($receipts as $i => $receipt) {
                    $this->exts->log("each record");
                    if ($this->exts->getElement('div a.bill-link', $receipt) != null) {
                        $receiptDate = $this->exts->extract('span.capitalize', $receipt);
                        $receiptDate = $this->translate_date_abbr(strtolower($receiptDate));
                        $this->exts->log($receiptDate);
                        $receiptUrl = $this->exts->getElement('div a.bill-link', $receipt);
                        $this->exts->executeSafeScript(
                            "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                            array($receiptUrl, $i)
                        );

                        $receiptUrl = "div#bill-archive div#historical-bills-container div.bill-separation.row div a.bill-link#invoice" . $i;
                        $idContrat = trim(explode("/", end(explode("/contract/", $currentURL)))[0]);
                        $receiptName = $idContrat . "_" . str_replace(" ", "", $receiptDate);
                        $receiptFileName = $receiptName . '.pdf';
                        $this->exts->log($receiptName);
                        $this->exts->log($receiptFileName);
                        $this->exts->log($receiptUrl);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'M Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptAmount = $this->exts->extract('span.bill-amount', $receipt);
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount) . 'EUR';
                        $this->exts->log($receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName,
                            'receiptUrl' => $receiptUrl,
                        );

                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));

                foreach ($invoices as $invoice) {
                    $this->totalFiles += 1;
                    $this->pDownloadInvoiceV1($invoice, 1);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
    function pDownloadInvoiceV1($invoice, $count)
    {
        $downloaded_file = $this->exts->click_and_print($invoice['receiptUrl'], $invoice['receiptFileName']);
        $this->exts->log("downloaded file");
        sleep(10);
        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
            $this->exts->log("create file");
            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
            sleep(5);
        } else {
            $count++;
            if ($count < 5) {
                $this->pDownloadInvoiceV1($invoice, $count);
            }
        }
    }
    private function processFacturePaiement()
    {
        $this->exts->waitTillPresent('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr');
        $this->exts->capture("4-invoices-page-FacturePaiement");
        $invoices = [];

        $current_url = $this->exts->getUrl();

        $rows_len = count($this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);

            if (count($tags) >= 4 && $this->exts->getElement('a[class*="downloadIcon"]', $row) != null) {
                $download_button = $this->exts->getElement('a[class*="downloadIcon"]', $row);
                $invoiceName = '';
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd F Y', 'Y-m-d', 'fr');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                $this->exts->waitTillPresent('button[data-e2e="download-link"]', 10);

                if ($this->exts->exists('button[data-e2e="download-link"]')) {
                    $this->exts->moveToElementAndClick('button[data-e2e="download-link"]');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceFileName = basename($downloaded_file);
                        $invoiceName = explode('.pdf', $invoiceFileName)[0];
                        $invoiceName = explode('(', $invoiceName)[0];
                        $invoiceName = str_replace(' ', '', $invoiceName);
                        $this->exts->log('Final invoice name: ' . $invoiceName);
                        $invoiceFileName = $invoiceName . '.pdf';
                        @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                }

                if ($this->exts->exists('button[data-e2e="pdf-cancel-popup"]')) {
                    $this->exts->moveToElementAndClick('button[data-e2e="pdf-cancel-popup"]');
                    sleep(5);
                }

                $this->exts->executeSafeScript('history.back();');
                sleep(3);
                $this->exts->waitTillPresent('[id*="billsHistoryTitle"] ~ table tbody tr, [id*="billsHistoryTitle"] ~ * table tbody tr, [aria-labelledby*="billsHistoryTitle"] table tbody tr');

                if (strpos($this->exts->getUrl(), 'voir-la-facture/true') !== false) {
                    $this->exts->openUrl($current_url);
                }
            }
        }
    }
    private function selectTabInvoiceYears()
    {
        $this->exts->capture('3-tab-year');
        $year_buttons = $this->exts->getElements('div#bill-archive nav ul li a');
        if ($this->restrictPages == 0) {
            foreach ($year_buttons as $key => $year_button) {
                $this->exts->click_element($year_button);
                $this->processProAccInvoice();
            }
        } else {
            $this->processProAccInvoice();
        }
    }

    private function processProAccLatestInvoice()
    {
        $this->exts->waitTillAnyPresent(['div.latest-bill span.icon-pdf-file', 'a[href*="facture-paiement/"]']);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        if ($this->exts->exists('div.latest-bill span.icon-pdf-file')) {
            $this->isNoInvoice = false;
            if ($this->exts->exists('div.latest-bill span.bill-date')) {
                $invoiceDate = trim($this->exts->extract('div.latest-bill span.bill-date', null, 'innerText'));
            } else {
                $invoiceDate = trim($this->exts->extract('div.latest-bill .item-container span:first-child,div.latest-bill .item-text span', null, 'innerText'));
            }
            $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
            if (trim($invoiceDate) == '' || $invoiceDate == null) {
                $invoiceDate = date('F Y');
            }
            $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.latest-bill span.bill-price', null, 'innerText'))) . ' EUR';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);

            $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            if (!$this->exts->invoice_exists($invoiceName)) {
                $this->exts->moveToElementAndClick('div.latest-bill span.icon-pdf-file');

                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
            }
        } else if ($this->exts->exists('a[href*="facture-paiement/"]')) {
            $invoices_url = $this->exts->getElementsAttribute('a[href*="facture-paiement/"]', 'href');
            foreach ($invoices_url as $invoice_url_index => $invoice_url) {
                $this->exts->openUrl($invoice_url);
                sleep(15);
                if ($this->exts->exists('li[data-e2e="bp-linkPDF"] a')) {
                    $this->isNoInvoice = false;
                    $invoiceDate = trim($this->exts->extract('#last-bill-date', null, 'innerText'));
                    $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
                    $invoiceName = trim(explode('/', end(explode('/facture-paiement/', $invoice_url)))[0]) . str_replace(' ', '', $invoiceDate);
                    $invoiceAmount = '';
                    $invoiceFileName = $invoiceName . '.pdf';
                    $this->isNoInvoice = false;

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'd m Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    if (!$this->exts->invoice_exists($invoiceName)) {
                        $this->exts->moveToElementAndClick('li[data-e2e="bp-linkPDF"] a');

                        $this->exts->wait_and_check_download('pdf');
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            if ($this->exts->invoice_exists($invoiceName)) {
                                $this->exts->log('Invoice existed ' . $invoiceFileName);
                            } else {
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                sleep(1);
                            }
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
                    }
                }
            }
        }
    }

    private function processProAccInvoice()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page-ProAccInvoice");
        $invoices = [];

        $rows_len = count($this->exts->getElements('#historical-bills-container div.row'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('#historical-bills-container div.row')[$i];
            if ($this->exts->getElement('a.bill-link', $row) != null) {
                $download_button = $this->exts->getElement('a.bill-link', $row);
                $invoiceDate = trim($this->exts->extract('span.capitalize:not(.bill-amount)', $row, 'innerText'));
                $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
                if ($this->exts->urlContains('/contracts/closed/')) {
                    $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                } else {
                    $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.bill-amount', $row, 'innerText'))) . ' EUR';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if (!$this->exts->invoice_exists($invoiceName)) {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }

                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
                }
            }
        }

        $rows_len = count($this->exts->getElements('div.historical-bills-container #bill-archive ul.items-list li'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('div.historical-bills-container #bill-archive ul.items-list li')[$i];
            if ($this->exts->getElement('span.icon-pdf-file', $row) != null) {
                $download_button = $this->exts->getElement('span.icon-pdf-file', $row);
                $invoiceDate = trim($this->exts->extract('.item-list-button-label', $row, 'innerText'));
                $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
                if ($this->exts->urlContains('/contracts/closed/')) {
                    $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                } else {
                    $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.ht-numb', $row, 'innerText'))) . ' EUR';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if (!$this->exts->invoice_exists($invoiceName)) {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }

                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::Already Exists ' . $invoiceName);
                }
            }
        }

        $rows_len = count($this->exts->getElements('#bill-archive ul.items-list li a div.item-container div.item-text span'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('#bill-archive ul.items-list li')[$i];
            if ($this->exts->getElement('a', $row) != null) {
                $download_button = $this->exts->getElement('a', $row);
                $invoiceDate = trim($this->exts->extract('a div.item-container div.item-text span', $row, 'innerText'));
                $invoiceDate = $this->exts->translate_date_abbr(strtolower($invoiceDate));
                if ($this->exts->urlContains('/contracts/closed/')) {
                    $invoiceName = trim(explode('/', end(explode('/contracts/closed/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                } else {
                    $invoiceName = trim(explode('/', end(explode('/contracts/', $this->exts->getUrl())))[0]) . str_replace(' ', '', $invoiceDate);
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('a div.item-container div.amount', $row, 'innerText'))) . ' EUR';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }

                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
