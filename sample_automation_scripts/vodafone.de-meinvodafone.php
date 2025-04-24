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

    // Server-Portal-ID: 308 - Last modified: 14.04.2025 14:26:06 UTC - User: 1

    // Script here

    public $baseUrl = "https://www.vodafone.de/meinvodafone/account/login";
    public $username_selector = 'input#txtUsername';
    public $password_selector = 'input#txtPassword';
    public $submit_btn = '.login-onelogin [type=submit]';
    public $logout_btn = 'div.dashboard-module';
    public $totalInvoices = 0;
    public $itemized_bill = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        sleep(10);
        $this->clearChrome();

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        $this->cookieConsent();

        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->baseUrl);
            if ($this->exts->urlContains('/captcha')) {
                $this->waitForSelectors('img.captcha', 16, 1);
                for ($i = 0; $i < 5; $i++) {
                    if ($this->exts->exists('img.captcha')) {
                        $this->exts->capture('captcha-found-' . $i);
                        $this->exts->click_element('a[href*="captcha"].btn');
                        sleep(10);
                        $this->exts->refresh();
                        $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                        $this->exts->capture('captcha-filled');
                        $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                        if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                            $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                        }
                        sleep(15);
                    } else {
                        break;
                    }
                }
            }

            $captchaErrorText = strtolower($this->exts->extract('div[class="fm-formerror error-body"] p:nth-child(3)'));

            $this->exts->log(__FUNCTION__ . '::captcha Error text: ' . $captchaErrorText);
            if (stripos($captchaErrorText, strtolower('Bitte sieh Dir das Bild an und geben den Sicherheitscode erneut ein.')) !== false) {
                $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);

                $this->waitForSelectors('button#dip-consent-summary-accept-all', 5, 3);

                if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
                    $this->exts->moveToElementAndClick('button#dip-consent-summary-accept-all');
                    sleep(4);
                }

                $this->solveCaptcha();
            }

            $this->cookieConsent();
            $this->exts->capture("after-login-clicked");
            $this->checkFillTwoFactor();
            if (!$this->checkLogin() && !$this->exts->exists('div.login-onelogin div.error div.alert-content, .alert-old div.alert.error')) {
                $this->exts->refresh();

                $this->waitForSelectors($this->logout_btn, 10, 1);

                if ($this->exts->exists($this->password_selector)) {
                    $this->fillForm(0);
                    $this->waitForSelectors('form#totpWrapper input#totpcontrol, div.login-onelogin div.error div.alert-content, div.dashboard-module', 20, 1);
                    // $this->exts->waitTillAnyPresent([$this->logout_btn, 'form#totpWrapper input#totpcontrol', 'div.login-onelogin div.error div.alert-content']);
                }
                $this->checkFillTwoFactor();
            }

            $this->cookieConsent();

            $err_msg = $this->exts->extract('div.login-onelogin div.error div.alert-content');
            // if ($this->exts->querySelector("div.login-onelogin div.error div.alert-content") != null) {
            //  $err_msg = trim($this->exts->querySelectorAll("div.login-onelogin div.error div.alert-content")[0]->getAttribute('innerText'));
            // }

            if ($err_msg != "" && $err_msg != null && $this->exts->exists('div.login-onelogin div.error div.alert-content')) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }
        }


        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            if ($this->exts->urlContains('/captcha')) {
                $this->waitForSelectors('img.captcha', 16, 1);
                for ($i = 0; $i < 5; $i++) {
                    if ($this->exts->exists('img.captcha')) {
                        sleep(10);
                        $this->exts->refresh();
                        $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                        $this->exts->capture('captcha-filled');
                        $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                        if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                            $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                        }
                        sleep(15);
                    } else {
                        break;
                    }
                }
            }
            $this->exts->moveToElementAndClick('#ds-consent-modal button');
            sleep(3);
            $this->exts->moveToElementAndClick('#personalOfferModal button.btn--submit');
            sleep(3);
            $this->cookieConsent();

            for ($i = 0; $i < 3; $i++) {
                if ($this->exts->exists('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]')) {
                    $this->exts->moveToElementAndClick('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]');
                    sleep(2);
                }
            }

            if ($this->exts->exists('div#overlayId a.btn-alt')) {
                $this->exts->moveToElementAndClick('div#overlayId a.btn-alt');
                sleep(3);
            }

            if ($this->exts->exists('.notification-message-container [class*="icon-close"]')) {
                $this->exts->moveToElementAndClick('.notification-message-container [class*="icon-close"]');
                sleep(2);
            }
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('div.simple-accord form.standard-form button[type="submit"]')) {
                if ($this->exts->exists('[formcontrolname="privacyPermissionFlagField"]')) {
                    $this->exts->account_not_ready();
                } else {
                    $this->exts->moveToElementAndClick('div.simple-accord form.standard-form button[type="submit"]');
                    sleep(15);

                    if ($this->exts->exists('app-submit-security-questions input[id*="answer"]')) {
                        $this->exts->account_not_ready();
                    }
                }
            }
            $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/notifizierung/dokumente');
            $this->waitForSelectors('button#dip-consent-summary-accept-all', 10, 1);
            if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
                $this->exts->click_element('button#dip-consent-summary-accept-all');
                sleep(10);
            }
            $this->exts->capture('3.1-open-documents');
            if (!$this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
                $this->processMultiContractsForDocument();
            }
            //If can't get invoices from document page, try get it from service page.
            $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/');
            $this->waitForSelectors('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h', 5, 3);
            $this->exts->capture('3.2-open-services');
            if ($this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
                $this->exts->refresh();
                sleep(15);
                $this->exts->capture('3.2-re-open-services');
            }
            $this->processMultiContractsInServicePage();
            // finally check total invoices
            if ($this->totalInvoices == 0) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log("LoginFailed " . $this->exts->getUrl());
            if ($this->exts->exists('.alert.error') && $this->exts->exists('.alert.error') && $this->exts->exists($this->username_selector)) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('form p')), 'bestätige bitte deine e-mail-adresse oder nenn uns eine andere. nur so können wir dir helfen, wenn du deine zugangsdaten vergessen hast') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function solveCaptcha()
    {
        for ($i = 0; $i < 7; $i++) {

            if ($this->exts->exists('img.captcha') && $i != 0) {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }

            if ($this->exts->exists('img.captcha')) {
                $this->exts->capture('captcha-found-' . $i);
                $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                $this->exts->capture('captcha-filled');
                $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                    $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                }
                sleep(15);
            } else {
                break;
            }
        }


        $captchaErrorText = strtolower($this->exts->extract('div[class="fm-formerror error-body"] p:nth-child(3)'));

        $this->exts->log(__FUNCTION__ . '::captcha Error text: ' . $captchaErrorText);
        if (stripos($captchaErrorText, strtolower('Bitte sieh Dir das Bild an und')) !== false) {
            for ($i = 0; $i < 5; $i++) {
                $this->exts->moveToElementAndClick('a[href*="cprx/captcha"]');

                $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                $this->exts->capture('captcha-filled');
                $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                    $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                    sleep(15);
                }

                if (!$this->exts->exists('img.captcha')) {
                    break;
                }
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(3);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha();
                $this->checkFillRecaptcha();

                $this->exts->moveToElementAndClick($this->submit_btn);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form#totpWrapper input#totpcontrol';
        $two_factor_message_selector = 'p[automation-id="totpcodeTxt_tv"]';
        $two_factor_submit_selector = 'div[automation-id="SUBMITCODEBTN_btn"] button[type="submit"]';

        if ($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                $this->exts->click_by_xdotool($two_factor_selector);
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                $this->waitForSelectors($this->logout_btn, 5, 3);

                if ($this->exts->querySelector($two_factor_selector) == null) {
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
                $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
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
				        if(deep > 5 || found){ 
                            return;
                        }
                        console.log(prop);
				        try {
				            if(prop.indexOf(".callback") > -1){
                                result = prop; 
                                found = true; 
                                return;
				            } else { 
                                if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ 
                                    return;
                                }
                                deep++;
				                for (var p in cur) { 
                                    recurse(cur[p], prop ? prop + "." + p : p, deep);
                                }
				            }
				        } catch(ex) { 
                            console.log("ERROR in function: " + ex); 
                            return; 
                        }
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

    private function cookieConsent()
    {
        if ($this->exts->exists('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]')) {
            $this->exts->moveToElementAndClick('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]');
            sleep(3);
        }
    }

    private function waitForSelectors($selector, $max_attempt, $sec)
    {
        for (
            $wait = 0;
            $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for Selectors!!!!!!');
            sleep($sec);
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
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitForSelectors($this->logout_btn, 10, 3);
            if ($this->exts->exists($this->logout_btn) && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }
    private function processMultiContractsForDocument()
    {
        $this->waitForSelectors('div.mod-multi-select-tags li#option_child', 10, 3);
        $numberOfContract = count($this->exts->querySelectorAll('div.mod-multi-select-tags li#option_child'));
        $this->exts->log('Number of contracts: ' . $numberOfContract);
        if ($numberOfContract > 1) {
            for ($i = 1; $i < $numberOfContract + 1; $i++) {
                $this->exts->update_process_lock();
                $this->exts->moveToElementAndClick('div.mod-multi-select-tags button.select-toggle');
                sleep(2);
                $contractNumber = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.mod-multi-select-tags li#option_child:nth-child(' . $i . ')', null, 'innerText')));
                $this->exts->log('Process contract number: ' . $contractNumber);
                $this->exts->moveToElementAndClick('div.mod-multi-select-tags li#option_child:nth-child(' . $i . ')');
                $this->waitForSelectors('div.filter_doc ul', 25, 1);
                $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector('div.filter_doc ul')]);
                // $this->exts->moveToElementAndClick('div.filter_doc ul');
                sleep(1);
                if ($this->exts->exists('select#category option[value="Rechnung"]')) {
                    $this->exts->execute_javascript("{let selectElement = document.querySelector('#category');
                    selectElement.value = 'Rechnung';
                    selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                    selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                    sleep(2);
                    $this->exts->execute_javascript("{let selectElement = document.querySelector('#subCategory');
                    selectElement.value = 'Rechnung';
                    selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                    selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                    sleep(2);
                    $this->exts->moveToElementAndClick('a[automation-id="documentsInboxes_applyFilterBtn_btn"]');
                    sleep(10);
                    $this->processInvoiceInbox();
                } elseif (!$this->exts->exists('select#category')) {
                    $this->processInvoiceInbox();
                }
                $this->exts->execute_javascript('window.scrollTo(0, 0);');
                sleep(1);
            }
        } else {
            $this->waitForSelectors('div.filter_doc ul', 15, 3);
            $this->exts->moveToElementAndClick('div.filter_doc ul');
            sleep(1);
            if ($this->exts->exists('select#category option[value="Rechnung"]')) {
                $this->exts->execute_javascript("{let selectElement = document.querySelector('#category');
                selectElement.value = 'Rechnung';
                selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                sleep(2);
                $this->exts->execute_javascript("{let selectElement = document.querySelector('#subCategory');
                selectElement.value = 'Rechnung';
                selectElement.dispatchEvent(new Event('input', { bubbles: true }));
                selectElement.dispatchEvent(new Event('change', { bubbles: true }));
                selectElement.dispatchEvent(new MouseEvent('click', { bubbles: true }));}");
                sleep(2);
                $this->exts->moveToElementAndClick('a[automation-id="documentsInboxes_applyFilterBtn_btn"]');
                sleep(10);
                $this->processInvoiceInbox();
            } elseif (!$this->exts->exists('select#category')) {
                $this->processInvoiceInbox();
            }
        }
    }
    private function processMultiContractsInServicePage()
    {
        $this->waitForSelectors("li[ng-repeat*='sortedContracts'], li[automation-id*='contract']", 5, 3);
        $contract_len = count($this->exts->querySelectorAll("li[ng-repeat*='sortedContracts'], li[automation-id*='contract']"));
        for ($i = 0; $i < $contract_len; $i++) {
            $contract = $this->exts->querySelectorAll("li[ng-repeat*='sortedContracts'], li[automation-id*='contract']")[$i];
            if ($contract != null) {
                $this->exts->update_process_lock();
                $contract_button = $this->exts->querySelector('a[ng-click*="openContract"], a.ac-head.accordion-anchor', $contract);

                $contract_name = $this->exts->extract('h2.contractName', $contract_button, 'innerText');
                $this->exts->log('======================== Get Contract ========================');
                $this->exts->log('contract name:' . $contract_name);
                $contract_num = trim(end(explode('Nr.', $this->exts->extract('div.contract-info', $contract_button, 'innerText'))));
                $this->exts->log('contract num:' . $contract_num);

                if ($contract_len != 1) {
                    $this->exts->click_element($contract_button);
                    sleep(5);
                }
                // continue if contract ended
                $mes = strtolower($contract_button->getAttribute('innerText'));
                if (strpos($mes, 'ist beendet') !== false) {
                    continue;
                }
                $invoiceLink = $this->exts->querySelector('a[href*="ihre-rechnungen/rechnungen"], a[href*="rechnungen/ihre-rechnungen"], a[automation-id="meineRechnungen_Link"]', $contract);
                if ($invoiceLink != null) {
                    $this->exts->click_element($invoiceLink);
                    $this->processInvoices($contract_num);
                }

                $this->exts->openUrl('https://www.vodafone.de/meinvodafone/services/');
                $this->waitForSelectors("li[ng-repeat*='sortedContracts'], li[automation-id*='contract']", 10, 2);
                $this->exts->capture('3.2-open-services');
                if ($this->exts->exists('div:not(.ng-hide) > .alert-old .error h4, div.doc-inbox-container .error h4')) {
                    $this->exts->refresh();
                    $this->waitForSelectors("li[ng-repeat*='sortedContracts'], li[automation-id*='contract']", 10, 2);
                    $this->exts->capture('3.2-re-open-services');
                }
            }
        }
    }

    private function processInvoiceInbox($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $this->waitForSelectors('ul.documents-inbox-container div.box', 15, 3);
        $rows = $this->exts->querySelectorAll('ul.documents-inbox-container div.box');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('button[automation-id="documentsInboxes_download_btn"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceAmount = '';
                $invoiceDate = $this->exts->extract('span[automation-id="documentsInboxes_date_tv"]', $row);

                $downloadBtn = $this->exts->querySelector('button[automation-id="documentsInboxes_download_btn"]', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);


            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)') != null
        ) {
            $paging_count++;
            $this->exts->click_by_xdotool('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)');
            sleep(5);
            $this->processInvoiceInbox($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)') != null) {
            $paging_count++;
            $this->exts->click_by_xdotool('div.pagination li.pagecounter + li a:not(.fm-inactive):nth-child(1)');
            sleep(5);
            $this->processInvoiceInbox($paging_count);
        }
    }

    private function processInvoices($contractNum)
    {
        $this->waitForSelectors('table > tbody > tr', 10, 3);
        $this->cookieConsent();
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $maxAttempts = 50;
        } else {
            $maxAttempts = $restrictPages;
        }
        for ($paging_count = 0; ($paging_count < $maxAttempts && $this->exts->exists('[automation-id="see_more_btn"]')); $paging_count++) {
            $this->exts->click_by_xdotool('[automation-id="see_more_btn"]');
            sleep(10);
        }

        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        $this->exts->log("==== Rows Count: " . count($rows));

        for ($i = 0; $i < count($rows); $i++) {
            $tags = $this->exts->querySelectorAll('td', $rows[$i]);
            if (count($tags) >= 4 && $this->exts->querySelector('svg[automation-id="table_2_svg"]', $tags[3]) != null) {
                $invoiceSelector = $this->exts->querySelector('svg[automation-id="table_2_svg"]', $tags[3]);
                $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-button-" . $i . "');", [$invoiceSelector]);
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'F Y', 'Y-m-01', 'de');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

                $this->totalInvoices++;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . '');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                if ($this->exts->exists('.invitationDesignMain button[id*="DeclineButton"]')) {
                    $this->exts->moveToElementAndClick('.invitationDesignMain button[id*="DeclineButton"]');
                    sleep(3);
                }
                if ($this->exts->exists('.invitationDesignMain button[id*="DeclineButton"]')) {
                    $this->exts->moveToElementAndClick('.invitationDesignMain button[id*="DeclineButton"]');
                    sleep(3);
                }
                if ($this->exts->exists('.nsm-content button[class*=btn-close]')) {
                    $this->exts->refresh();
                    $i--;
                    sleep(3);
                    continue;
                }
                // click and download invoice
                $download_button_selector = 'svg#custom-pdf-button-' . $i;
                if ($this->exts->exists($download_button_selector)) {
                    try {
                        $this->exts->log("---Click invoice download button ");
                        $this->exts->moveToElementAndClick($download_button_selector);
                    } catch (\Exception $e) {
                        $this->exts->log("---Click invoice download button by javascript ");
                        $this->exts->execute_javascript("document.querySelector(arguments[0]).dispatchEvent(new Event('click'));", [$download_button_selector]);
                    }
                }
                $this->exts->wait_and_check_download('pdf', 10);
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $this->exts->log('Final invoice name: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
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
