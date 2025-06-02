<?php // redue unneccasry sleep time from login code

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

    public $baseUrl = 'https://secure.business.bt.com/Account/LoginRedirect.aspx?tabId=1';
    public $username_selector = 'form[name="Login"] input#USER, input#signInName';
    public $password_selector = 'form[name="Login"] input#PASSWORD, input[name="Password"]';
    public $check_login_failed_selector = 'form[name="Login"] .error[style*="display: block"]';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLoginSuccess()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            $this->waitFor('.truste_popframe', 10);
            if ($this->exts->exists('.truste_popframe')) {
                $this->switchToFrame('.truste_popframe');
                sleep(2);
                if ($this->exts->exists('a.call')) {
                    $this->exts->moveToElementAndClick('a.call');
                    sleep(5);
                }
                $this->exts->switchToDefault();
            }
            $this->checkFillLogin();
            $this->waitFor('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode', 5);
            if ($this->exts->exists('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode')) {
                $this->exts->click_element('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode');
            }
            $this->checkFillTwoFactor();
            sleep(40);
            if (stripos($this->exts->extract('div.error-code'), 'ERR_TOO_MANY_REDIRECTS') !== false) {
                $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);
                $this->waitFor('.truste_popframe', 10);
                if ($this->exts->exists('.truste_popframe')) {
                    $this->switchToFrame('.truste_popframe');
                    sleep(2);
                    if ($this->exts->exists('a.call')) {
                        $this->exts->moveToElementAndClick('a.call');
                        sleep(5);
                    }
                    $this->exts->switchToDefault();
                }
                $this->checkFillLogin();
                $this->waitFor('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode', 5);
                if ($this->exts->exists('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode')) {
                    $this->exts->click_element('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode');
                }
                $this->checkFillTwoFactor();
                sleep(30);
                $this->exts->capture('2.2-Login after clear chrome');
                if (stripos($this->exts->extract('div.error-code'), 'ERR_TOO_MANY_REDIRECTS') !== false) {
                    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
                    exec("sudo docker exec " . $node_name . " bash -c 'sudo rm -rf /home/chrome-profile/*");
                    sleep(1);
                    $this->exts->restart();
                    sleep(20);
                    $this->exts->openUrl($this->baseUrl);
                    $this->waitFor('.truste_popframe', 20);
                    if ($this->exts->exists('.truste_popframe')) {
                        $this->switchToFrame('.truste_popframe');
                        sleep(2);
                        if ($this->exts->exists('a.call')) {
                            $this->exts->moveToElementAndClick('a.call');
                            sleep(5);
                        }
                        $this->exts->switchToDefault();
                    }
                    $this->checkFillLogin();
                    $this->waitFor('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode', 10);
                    if ($this->exts->exists('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode')) {
                        $this->exts->click_element('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode');
                    }
                    $this->checkFillTwoFactor();
                    sleep(40);
                }
            }
            if ($this->exts->exists('input#chkbxTermsAndConditions')) {
                $this->exts->moveToElementAndClick('input#chkbxTermsAndConditions');
                $this->exts->moveToElementAndClick('button#btnNxtTermsAndCondition');
                sleep(20);
            }
            sleep(5);

            if ($this->exts->exists('.truste_popframe')) {
                $this->switchToFrame('.truste_popframe');
                sleep(2);
                if ($this->exts->exists('a.call')) {
                    $this->exts->moveToElementAndClick('a.call');
                    sleep(10);
                }
                $this->exts->switchToDefault();
            }
            if ($this->exts->exists('[role="dialog"][style*="display: block"] .modal-close-btn')) {
                $this->exts->moveToElementAndClick('[role="dialog"][style*="display: block"] .modal-close-btn');
                sleep(10);
            }
            if ($this->exts->urlContains('/Intercept/UpdateContact')) { // If update infor form show, it have option to skip it
                $this->exts->moveToElementAndClick('.thankyou a.link-btn .link-arrow');
                sleep(5);
                if ($this->exts->exists('button.neb-form-close-btn')) {
                    $this->exts->moveToElementAndClick('button.neb-form-close-btn');
                    sleep(10);
                }
                $this->exts->moveToElementAndClick('#myModal button.bt-primary-btn');
                sleep(20);
            }
            if ($this->exts->getElements('//button//*[contains(text(), "Update later")]', null, 'xpath') != null) {
                $this->exts->click_element('//button//*[contains(text(), "Update later")]');
                sleep(5);
                $this->exts->click_element('//div[contains(@class, "contact-details-modal")]//button//*[contains(text(), "Update Later")]');
                sleep(20);
            }

            if ($this->exts->querySelector('div[class*="contact-details-modal"] > div[class*="contact-details-modal"]:nth-child(3) button[class="arc-Button"]') != null) {
                $this->exts->moveToElementAndClick('div[class*="contact-details-modal"] > div[class*="contact-details-modal"]:nth-child(3) button[class="arc-Button"]');
                sleep(10);
            }

            if (stripos($this->exts->extract('div[class*="security-popup__StyledHeader"]'), 'Set your security number') !== false && $this->exts->exists('input[name="pin-field-0"]')) {
                $this->exts->account_not_ready();
            }
        }
        // then check user logged in or not
        if ($this->checkLoginSuccess()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            if ($this->exts->exists('[role="dialog"][style*="display: block"] .modal-close-btn')) {
                $this->exts->moveToElementAndClick('[role="dialog"][style*="display: block"] .modal-close-btn');
                sleep(2);
            }
            $this->exts->capture("3-login-success");
            $this->doAfterLogin();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            if (stripos($this->exts->extract('.error.pageLevel:not([style*="display: none"])'), 'password') !== false) {
                $this->exts->loginFailure(1);
            } else if (
                strpos($this->exts->getUrl(), '/ChangePassword.do') !== false
                && strpos($this->exts->extract('div[ng-app="GenericErrorApp"]:not([style*="display: none"])'), 'something\'s gone wrong') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if (($this->exts->urlContains('/FirstTimeLoginSQnA.aspx') &&
                $this->exts->exists('.progress3Steps li[class*="progressStep"')) || $this->exts->urlContains('/VerifyEmail/EmailNotVerified')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->querySelector('.notification-message') != null && $this->exts->querySelector('button[data-ng-click="createNewPassword();"]') != null) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('business.bt.com/Registration') || $this->exts->urlContains('.business.bt.com/ErrorPage.aspx')) {
                $this->exts->account_not_ready();
            } elseif (stripos($this->exts->extract('div[class*="security-popup__StyledHeader"]'), 'Set your security number') !== false && $this->exts->exists('input[name="pin-field-0"]')) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->exists('a[href="https://secure.business.bt.com/ForgotUsernameandPassword"]') && $this->exts->exists('p#CompromisedAccountResponseMsg')) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->exists('p#ConditionalAccessResponseMsg[aria-label*="blocked"]')) {
                $this->exts->account_not_ready();
            } elseif (stripos(strtolower($this->exts->extract('p.textInParagraph')), strtolower('no longer secure')) !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[id="verificationCode"]';
        $two_factor_message_selector = 'div[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_success_message"]';
        $two_factor_submit_selector = 'button[class="verifyCode"]';

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
                $this->exts->moveToElementAndClick($two_factor_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($two_factor_code);

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
    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
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

    public function waitFor($selector, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        $this->exts->capture("2-login-page");
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->checkFillRecaptcha();
            $this->exts->capture("2-username-filled");
            $this->exts->moveToElementAndClick('button#next');
            sleep(10);
        }
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-password-filled");
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick('button#next');
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"], iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->waitFor($recaptcha_iframe_selector, 10);
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
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
    private function checkLoginSuccess()
    {
        return $this->exts->exists('a.arc-SiteHeader-loginLink[href*="logout"],a.arc-SiteHeader-loginLink[href*="LogoutCDE"], [data-automation-id="navigation-item-My Account"], a[href="/my-account/logout"], a.arc-SiteHeaderV2-loginLink[href*="LogoutCDE"], div[class*="welcome-banner"]');
    }

    private function doAfterLogin()
    {
        $this->exts->moveToElementAndClick('.nav-itm-bill a, li[data-testid="navItem"] a[href*="LoginRedirect"], li[data-testid="navItem-Billing"] a');
        sleep(10);
        $this->checkFillLogin();
        $this->waitFor('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode', 5);
        if ($this->exts->exists('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode')) {
            $this->exts->click_element('button[id="MFAVerifyPrimaryPhoneOrBackupEmailDisplayControl_but_send_code"], button.sendCode');
        }
        $this->checkFillTwoFactor();
        sleep(20);
        $this->exts->openUrl('https://secure.business.bt.com/eserve/cust/loggedin/previousBills.html');
        $this->processBusinessInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    private function processBusinessInvoices($pageCount = 1)
    {
        sleep(25);
        $this->exts->capture("4-business-bills");
        $accounts = [];
        if ($this->exts->exists('#multiAccountsDiv:not([style*="display: none"]) #dropdown')) {
            $this->exts->moveToElementAndClick('#multiAccountsDiv:not([style*="display: none"]) #dropdown');
            sleep(3);
            $this->exts->capture("4-multi-account-checking");
            $accounts = $this->exts->getElementsAttribute('ul.account-options li', 'innerText');
            print_r($accounts);

            $this->exts->moveToElementAndClick('#multiAccountsDiv #dropdown'); // close dropdown
            sleep(3);
        }

        if (count($accounts) > 1) {
            foreach ($accounts as $account_string) {
                if ($this->exts->exists('#MDigitalLightboxWrapper, .truste_overlay, .truste_box_overlay_inner')) {
                    $this->exts->executeSafeScript('
                var blocked_guis = document.querySelectorAll("#MDigitalLightboxWrapper, .truste_overlay, .truste_box_overlay_inner");
                for (index = 0; index < blocked_guis.length; index++) {
                    blocked_guis[index].remove();
                }
            ');
                    sleep(3);
                }

                $this->exts->moveToElementAndClick('#multiAccountsDiv #dropdown'); // close dropdown
                sleep(3);
                $options = $this->exts->getElements('ul.account-options li');
                foreach ($options as $option) {
                    $option_text = $option->getAttribute('innerText');
                    if (trim($option_text) == trim($account_string)) {
                        try {
                            $this->exts->log('Click option');
                            $option->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click option by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$option]);
                        }
                        sleep(7);
                        break;
                    }
                }

                if ($this->exts->exists('#MDigitalLightboxWrapper, .truste_overlay, .truste_box_overlay_inner')) {
                    $this->exts->executeSafeScript('
                var blocked_guis = document.querySelectorAll("#MDigitalLightboxWrapper, .truste_overlay, .truste_box_overlay_inner");
                for (index = 0; index < blocked_guis.length; index++) {
                    blocked_guis[index].remove();
                }
            ');
                    sleep(3);
                }

                // New download solution, click download all
                if ($this->exts->exists('.has-bills:not([style*="display: none;"]) a.download-all-btn')) {
                    $this->exts->moveToElementAndClick('.has-bills:not([style*="display: none;"]) a.download-all-btn');
                    sleep(40);
                    $this->exts->wait_and_check_download('zip');

                    $downloaded_file = $this->exts->find_saved_file('zip');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->extract_zip_save_pdf($downloaded_file);
                    } else {
                        sleep(60);
                        $this->exts->wait_and_check_download('zip');
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->extract_zip_save_pdf($downloaded_file);
                        }
                    }
                }
            }
        } else {
            // New download solution, click download all
            $this->exts->moveToElementAndClick('.has-bills:not([style*="display: none;"]) a.download-all-btn');
            sleep(40);
            $this->exts->wait_and_check_download('zip');

            $downloaded_file = $this->exts->find_saved_file('zip');
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->extract_zip_save_pdf($downloaded_file);
            } else {
                sleep(60);
                $this->exts->wait_and_check_download('zip');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->extract_zip_save_pdf($downloaded_file);
                }
            }
        }
    }
    private function extract_zip_save_pdf($zipfile)
    {
        $this->isNoInvoice = false;
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileInfo = pathinfo($zipPdfFile['name']);
                if ($fileInfo['extension'] === 'pdf') {
                    $invoice_name = basename($zipPdfFile['name'], '.pdf');
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($invoice_name, "", "", $saved_file);
                    sleep(1);
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
