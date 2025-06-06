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
    // Server-Portal-ID: 109160 - Last modified: 09.10.2024 13:10:26 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.americanexpress.com';
    public $username_selector = 'input[id*="eliloUserID"]';
    public $password_selector = 'input[id*="eliloPassword"]';
    public $submit_login_btn = 'button#loginSubmit';

    public $checkLoginFailedSelector = 'div#alertMsg, #ssoErrors';
    public $checkLoggedinSelector = 'a#iNavLogOutButton, a[href$="logout"], a[class*="axp-global-header__GlobalHeader__closedLogout___"], #iNavLogin a[href*="logout"], a[href*="/logOff"]';
    public $total_invoices = 0;

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
        sleep(5);
        $this->exts->moveToElementAndClick("input[id*='sprite-ContinueButton']");
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        if (
            $this->exts->exists($this->checkLoggedinSelector) || ($this->exts->getElement("a#gnav_logout") != null && $this->exts->getElement("a#gnav_logout")->isDisplayed())
        ) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            // Standard way is open https://www.americanexpress.com and click login
            // But login via https://global.americanexpress.com/login/fr-CA?inav=ca_fr_utility_login we can avoid 2FA, so at this time, we use this url
            // $this->exts->openUrl('https://global.americanexpress.com/login/fr-CA?inav=ca_fr_utility_login');
            // UPDATED 24-Jul-2020 Above link didn't work any more, now we use https://www.americanexpress.com/en-ca/account/login?inav=iNavLnkLog
            $this->exts->openUrl('https://www.americanexpress.com/en-ca/account/login?inav=iNavLnkLog');
            sleep(15);
            if (!$this->exts->exists('select#eliloSelect')) {
                $this->exts->moveToElementAndClick('a#gnav_login');
                sleep(10);
            }
            $this->closePopups();
            $this->checkFillLogin();
            sleep(10);
            $this->checkFillRecaptcha();
            $this->closePopups();
            sleep(10);
            if ($this->exts->exists('div[data-module-name="identity-components-otp"]')) {
                if ($this->exts->exists('[name="otp-channel"] input:nth-child(1)')) {
                    $this->exts->moveToElementAndClick('[name="otp-channel"] input:nth-child(1)');
                    $this->exts->moveToElementAndClick('button[type="submit"]');
                    sleep(3);
                }
            }
            $this->checkFillTwoFactor();
            $this->checkFillOTP();
        }
        $this->processAfterLogin();
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('select#eliloSelect')) {
                $this->changeSelectbox('select#eliloSelect', 'merchant');
            } else if ($this->exts->exists('select[aria-labelledby="eliloAccountType-label"]')) {
                $this->changeSelectbox('select[aria-labelledby="eliloAccountType-label"]', 'merchant');
            }
            if ($this->exts->exists('input#rememberMe:not(:checked)'))
                $this->exts->moveToElementAndClick('label[for="rememberMe"]');
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function changeSelectbox($select, $value)
    {
        $this->exts->execute_javascript('
            let selectBox = document.querySelector("' . addslashes($select) . '");
            selectBox.value = "' . addslashes($value) . '";
            selectBox.dispatchEvent(new Event("change"));
        ');
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form[action*="/auth/secqa"] input#ssoSecretAnwserTxt';
        $two_factor_message_selector = '#mfa-login-block > p';
        $two_factor_submit_selector = 'form[action*="/auth/secqa"] input#ssoGoBtn';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $this->exts->moveToElementAndClick('select#ssoSecretQuestionTxt');
            sleep(2);
            $this->exts->moveToElementAndClick('select#ssoSecretQuestionTxt option:not([value="0"])');
            sleep(2);

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('select#ssoSecretQuestionTxt option:checked', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
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

    private function checkFillOTP()
    {
        // This is for Merchant OTP send to Email
        $two_factor_selector = '.questions > .pad-2:not([hidden]) input[name="answer"], input[name="question-input"]';
        $two_factor_message_selector = '.questions > .pad-2:not([hidden]) > div:first-child, div.pad:not([hidden]) > p';
        $two_factor_submit_selector = '.questions + .group-primary.right button.btn-primary, button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor OTP page found.");
            $this->exts->capture("2.1-two-factor-otp");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillOTP: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillOTP: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillOTP();
                } else {
                    $this->exts->log("checkFillOTP: Two factor can not solved");
                }
            } else {
                $this->exts->log("checkFillOTP: Not received two factor code");
            }
        }
    }

    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
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
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
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
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function processAfterLogin()
    {
        $this->closePopups();
        sleep(2);
        if (
            $this->exts->waitTillPresent($this->checkLoggedinSelector) || $this->exts->exists('a[href*="&linknav=de-merchsite-menutoolbar-payments"]') || $this->exts->exists('a[href$="logout"]') || $this->exts->exists('a#de_utility_login[href*="logout"]') || ($this->exts->exists("a#gnav_logout") && $this->exts->exists("a#gnav_logout")) || $this->exts->exists("a.axp-global-header__GlobalHeader__closedLogout___3PWnS") || $this->exts->exists('a[href*="/logOff"]') ||
            ($this->exts->urlContains('ssoremove=yes') && $this->exts->urlContains('/auth/servlet/'))
        ) {
            $this->exts->log(__FUNCTION__ . 'User logged in.');
            $this->exts->log(__FUNCTION__ . 'URL after successfully login: ' . $this->exts->getUrl());
            $this->exts->capture("2-post-login");
            sleep(10);
            $this->closePopups();
            $this->exts->openUrl('https://merchant-payments.americanexpress.com/?locale=de_DE&linknav=de-merchsite-menutoolbar-payments');
            sleep(10);
            $this->downloadMerchantStatement();

            if ($this->total_invoices == 0) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            // .alert-warn.in
            if ($this->exts->urlContains('/registration') || $this->exts->urlContains('/REG/ATWORK/')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('/password/manage')) {
                $this->exts->account_not_ready();
            } else if (stripos($this->exts->extract('div[role="alert"]'), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('/enroll')) {
                $this->exts->no_permission();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function downloadMerchantStatement()
    {
        sleep(15);
        // click Show all
        if ($this->exts->exists('a[tag-name="Click_DepSumEstatements"], a[tag-name="Click_Estatements"]')) {
            $this->exts->moveToElementAndClick('a[tag-name="Click_DepSumEstatements"], a[tag-name="Click_Estatements"]');
            sleep(10);

            // select Invoice from statement type
            $optionSelTypeEle = "select[name=\"estatementType\"] option[value=\"1\"]";
            $selectStatTypeElement = $this->exts->getElement($optionSelTypeEle);
            if ($selectStatTypeElement != null) {
                $this->exts->moveToElementAndClick($optionSelTypeEle);
            }
            sleep(5);

            $this->exts->capture("merchant-statement");
            $rows = count($this->exts->getElements('.row[ng-repeat*="estatement"]'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('.row[ng-repeat*="estatement"]')[$i];
                if ($this->exts->getElement('a[ng-click*="estatement.pdfDownloadUrl"]', $row) != null) {
                    $this->total_invoices++;
                    $download_button = $this->exts->getElement('a[ng-click*="estatement.pdfDownloadUrl"]', $row);
                    $invoiceDate = $this->exts->extract('.ng-binding.one:first-child', $row);
                    $invoiceName = 'merchant_estmt_' . preg_replace("/[^\w]/", '_', trim($invoiceDate));
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceAmount = '';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'j.n.Y', 'Y-m-d');
                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($invoiceDate, 'j/n/Y', 'Y-m-d');
                    }
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Statement button not found');
        }
    }

    function closePopups()
    {
        $this->exts->log(__FUNCTION__ . " Begin");
        if ($this->exts->exists('.consentOverlay .acceptWidth_euc [type="button"]')) {
            $this->exts->moveToElementAndClick('.consentOverlay .acceptWidth_euc [type="button"]');
            sleep(3);
        }
        try {
            $popup_btn = $this->exts->getElement("//span[contains(text(),'Nein, danke')]/../../../..", null, 'xpath');
            if ($popup_btn != null) {
                $this->exts->log(__FUNCTION__ . " : Found popup button, click on close button ");
                $popup_btn->click();
            }
        } catch (\Exception $ex) {
            $this->exts->log(__FUNCTION__ . " : exception finding popup one : " . $ex);
        }

        if ($this->exts->exists('div[id*="user-consent-management"] button[id*="accept-all"]')) {
            $this->exts->moveToElementAndClick('div[id*="user-consent-management"] button[id*="accept-all"]');
            sleep(3);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
