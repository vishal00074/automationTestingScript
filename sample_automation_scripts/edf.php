<?php // updated download code.

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

    // Server-Portal-ID: 110957 - Last modified: 13.02.2025 06:48:19 UTC - User: 1

    public $baseUrl = "https://entreprises-collectivites.edf.fr/espaceclient/s/";
    public $loginUrl = "https://entreprises-collectivites.edf.fr/espaceclient/s/";
    public $username_selector = 'input#email';
    public $password_selector = 'input[type="password"],input#password-input,input#password2-password-field';
    public $submit_button_selector = 'button#authentification-button';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;

    public $accountNo = "";
    public $totalFiles = 0;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(12);
        $this->exts->capture("Home-page-without-cookie");

        for ($i = 0; $i < 3
            && ($this->exts->exists('div#error-information-popup-content div.error-code')
                && (strpos($this->exts->extract('div#error-information-popup-content div.error-code'), 'ERR_TUNNEL_CONNECTION_FAILED') !== false || strpos($this->exts->extract('div#error-information-popup-content div.error-code'), 'ERR_HTTP2_PROTOCOL_ERROR') !== false)); $i++) {
            $this->exts->log('========== The webpage might be temporarily down or it may have moved permanently to a new web address. =====');
            $this->exts->capture('site-down-or-moved-permanently');
            $this->clearChrome();
            sleep(3);
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
            $this->exts->capture("Home-page-without-cookie-1");
        }

        for ($i = 0; $i < 3 && strpos($this->exts->extract('body'), '404 - Not Found') !== false; $i++) {
            $this->exts->refresh();
            sleep(20);
        }

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(10);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
        } else {
            $this->exts->openUrl($this->loginUrl);
        }

        if (!$isCookieLoginSuccess) {

            $this->exts->openUrl($this->loginUrl);

            $this->exts->waitTillPresent('#header_tc_privacy_button', 10);
            if ($this->exts->exists('#header_tc_privacy_button')) {
                $this->exts->moveToElementAndClick('#header_tc_privacy_button');
                sleep(3);
            }
            if ($this->exts->exists('.header-menu-user-account  button')) {
                $this->exts->moveToElementAndClick('.header-menu-user-account button');
                sleep(5);
            }
            if ($this->exts->exists('.account-entreprise a')) {
                $this->exts->moveToElementAndClick('.account-entreprise a');
                sleep(5);
            }

            $this->fillForm(0);

            $this->exts->waitTillPresent('input#code-seizure__field', 10);
            if ($this->exts->exists('input#code-seizure__field')) {
                $this->checkFillTwoFactor();
                sleep(1);
            }



            sleep(10);
            // checkAndSelectAccount
            if ($this->exts->exists('.modal-dialog .modal-body #ice-carousel .ice-carousel-content-sites > div')) {
                $this->exts->moveToElementAndClick('.modal-dialog .modal-body #ice-carousel .ice-carousel-content-sites > div');
                sleep(10);
            }
            if ($this->exts->exists('button#popin_tc_privacy_button_3')) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_3');
                sleep(3);
            }

            // accept term of service
            if ($this->exts->exists('.siteforceContentArea')) {
                $this->exts->execute_javascript('
				document.querySelector(".siteforceContentArea [data-region-name=mainContent] > div > *").shadowRoot.querySelector("div > *").shadowRoot.querySelector(".parent-accept c-edf-checkbox").shadowRoot.querySelector("span.checkbox").click();
				document.querySelector(".siteforceContentArea [data-region-name=mainContent] > div > *").shadowRoot.querySelector("div > *").shadowRoot.querySelector(".siteforceContentArea .parent-accept .edf-button-primary").click();
			');
                sleep(3);
                $this->exts->execute_javascript('
				document.querySelector(".siteforceContentArea [data-region-name=mainContent] > div > *").shadowRoot.querySelector("div > *").shadowRoot.querySelector(".edf-modal-background c-edf-button").shadowRoot.querySelector(".edf-button-primary").click();
			');

                sleep(5);
            }

            if ($this->exts->getElement('//*[contains(@class, "slds-fade-in-open")]//*[@id="checkbox"]//label[@lightning-input_input]', null, 'xpath') != null) {
                // select check box
                $this->exts->getElement('//*[contains(@class, "slds-fade-in-open")]//*[@id="checkbox"]//label[@lightning-input_input]', null, 'xpath')->click();
                sleep(1);
                $this->exts->moveToElementAndClick('footer button.slds-button_brand');
                sleep(5);
            }
            if ($this->exts->exists('input[ng-model="cgu_checked"]')) {
                $this->exts->moveToElementAndClick('input[ng-model="cgu_checked"]');
                sleep(2);
                $this->exts->moveToElementAndClick('button#btnAccepterCgu');
                sleep(15);
            }
            if ($this->checkLogin()) {
                if ($this->exts->exists('button#popin_tc_privacy_button_3')) {
                    $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_3');
                }
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->selectPoints();
            } else {
                $this->exts->log("LoginFailed " . $this->exts->getUrl());
                if (strpos($this->exts->extract('[class*="-text-heading_large"]'), 'LECTIONNEZ UN POINT') !== false) {
                    $this->exts->no_permission();
                } else if ($this->exts->exists('.ice-homepage-bloc-info')) {
                    // You have just activated access to your EDF Entreprises customer area. It is now in the process of being created.
                    $this->exts->account_not_ready();
                }
                $this->exts->loginFailure();
            }
        } else {
            // checkAndSelectAccount
            if ($this->exts->exists('.modal-dialog .modal-body #ice-carousel .ice-carousel-content-sites > div')) {
                $this->exts->moveToElementAndClick('.modal-dialog .modal-body #ice-carousel .ice-carousel-content-sites > div');
                sleep(10);
            }
            sleep(10);

            // accept term of service; HOW TO ACCESS DOM ELEMENT IN SALESFORCE?????? document.querySelector not working
            if ($this->exts->getElement('//*[contains(@class, "slds-fade-in-open")]//*[@id="checkbox"]//label[@lightning-input_input]', null, 'xpath') != null) {
                // select check box
                $this->exts->getElement('//*[contains(@class, "slds-fade-in-open")]//*[@id="checkbox"]//label[@lightning-input_input]', null, 'xpath')->click();
                sleep(1);
                $this->exts->moveToElementAndClick('footer button.slds-button_brand');
                sleep(5);
            }
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            if ($this->exts->exists('button#popin_tc_privacy_button_3')) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_3');
            }
            $this->exts->capture("LoginSuccess");
            $this->selectPoints();
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

    function checkFillRecaptcha()
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
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
			if(document.querySelector("[data-callback]") != null){
				return document.querySelector("[data-callback]").getAttribute("data-callback");
			}

			var result = ""; var found = false;
			function recurse (cur, prop, deep) {
					if(deep > 5 || found){
						return;
					}
					console.log(prop);
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
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    function fillForm($count = 0)
    {
        sleep(10);
        $this->exts->log("Begin fillForm " . $count);
        $count++;
        try {
            if ($this->exts->exists('button#popin_tc_privacy_button_3')) {
                $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_3');
            }

            if ($this->exts->exists($this->username_selector)) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");

                $this->exts->moveToElementAndClick($this->username_selector);
                sleep(1);
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                if ($this->exts->exists('button#username-next-button')) {
                    $this->exts->moveToElementAndClick('button#username-next-button');
                }

                $this->exts->waitTillPresent('button#HOTTPROAuth2-next-button', 10);
                if ($this->exts->exists('button#HOTTPROAuth2-next-button')) {

                    $this->exts->moveToElementAndClick('button#HOTTPROAuth2-next-button');
                    $this->checkFillTwoFactor();
                }

                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->log("Enter Password");

                    $this->exts->moveToElementAndClick($this->password_selector);
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    $this->exts->moveToElementAndClick('button#password2-next-button');

                    $this->exts->waitTillPresent('button#HOTTPROAuth2-next-button', 10);
                    if ($this->exts->exists('button#HOTTPROAuth2-next-button')) {

                        $this->exts->moveToElementAndClick('button#HOTTPROAuth2-next-button');
                        $this->checkFillTwoFactor();
                    }

                    $this->exts->capture('1-pre-login-filled');
                    $this->exts->type_key_by_xdotool('Return');
                }

                if ($this->exts->waitTillPresent('div#messages div.alert span.message, span#errorMessage')) {
                    $err_msg = $this->exts->extract('div#messages div.alert span.message, span#errorMessage');
                    if ($err_msg != "" && $err_msg != null) {
                        $this->exts->log($err_msg);
                        $this->exts->log("LoginFailed" . $this->exts->getUrl());
                        $this->exts->loginFailure(1);
                    } else {
                        sleep(15);
                    }
                }


                $err_msg = $this->exts->extract('div#messages div.alert span.message, span#errorMessage');
                if ($err_msg != "" && $err_msg != null) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }

                if ($this->exts->exists('div#main-frame-error div#control-buttons button#reload-button')) {
                    if ($count > 5) {
                        $this->exts->log("LoginFailed" . $this->exts->getUrl());
                        $this->exts->log("Max login attempts " . $this->exts->getUrl());
                        $this->exts->loginFailure();
                    } else {
                        $this->exts->log('======= Site took too long to response. Try refresh and relogin ========');
                        $this->exts->capture('Site-took-too-long');
                        $this->exts->refresh();
                        $this->exts->openUrl($this->loginUrl);
                        sleep(20);
                        $this->fillForm($count);
                    }
                }

                if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                    $this->exts->moveToElementAndClick($this->password_selector);
                    sleep(1);
                    $this->exts->type_key_by_xdotool('Return');
                }
            }
            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#code-seizure__field';
        $two_factor_message_selector = 'p#msgSaisie';
        $two_factor_submit_selector = 'button#HOTPPROAuth3-next-button';

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
        }
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('a[ng-click="deconnexion()"], div.contentRegion table tr button[class*="button_brand"], #div-user-name, li.edf-nav__profile-deconnexion  a')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->urlContains('/espaceclientpremium/s/aiguillage')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else {
                $this->exts->moveToElementAndClick('button.profile-menuTrigger');
                sleep(5);

                if ($this->exts->exists('li.logOut, div[class*="profile-item"]')) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    function selectPoints()
    {
        $this->exts->log('start function selectPoints');
        $this->exts->capture('selectPoints');
        $site_button_xselector = '//*[contains(@class, "contentRegion")]//table//tr//button[contains(@class, "button_brand")]';

        if ($this->exts->getElement($site_button_xselector, null, 'xpath') != null) {
            $row_len = count($this->exts->getElements($site_button_xselector, null, 'xpath'));
            for ($i = 0; $i < $row_len; $i++) {
                $row = $this->exts->getElements($site_button_xselector, null, 'xpath')[$i];
                if ($row !== null) {
                    try {
                        $this->exts->log('Click site address button');
                        $row->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click site address button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$row]);
                    }
                    sleep(15);
                    $this->invoicePage();
                    $this->exts->openUrl('https://entreprises-collectivites.edf.fr/espaceclient/s/aiguillage');
                    sleep(18);
                }
            }
        } else if ($this->exts->exists('div.contentRegion table tr button[class*="button_brand"]')) {
            $row_len = count($this->exts->getElements('div.contentRegion table tr button[class*="button_brand"]'));
            for ($i = 0; $i < $row_len; $i++) {
                $row = $this->exts->getElements('div.contentRegion table tr button[class*="button_brand"]')[$i];
                if ($row !== null) {
                    try {
                        $this->exts->log('Click site address button');
                        $row->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click site address button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$row]);
                    }
                    sleep(15);
                    $this->invoicePage();
                    $this->exts->openUrl('https://entreprises-collectivites.edf.fr/espaceclient/s/aiguillage');
                    sleep(18);
                    // $this->exts->moveToElementAndClick('div[class*="HomeLink"] button.buttonBack');
                    // sleep(10);
                }
            }
        } else if ($this->exts->getElement($site_button_xselector, null, 'xpath') != null) {
            $site_address = $this->exts->getElement($site_button_xselector, null, 'xpath');
            try {
                $this->exts->log('Click site_address button');
                $site_address->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click site_address button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$site_address]);
            }
            sleep(10);
            $this->invoicePage();
        } else {
            $this->invoicePage();
        }

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    function invoicePage()
    {
        $this->exts->log("Invoice page");
        if ($this->exts->exists('#dialog-cnil  #cnil-icone-croix-blanche')) {
            $this->exts->moveToElementAndClick('#dialog-cnil  #cnil-icone-croix-blanche');
            sleep(2);
        }
        if ($this->exts->exists('button#poursuivrepopin')) {
            $this->exts->moveToElementAndClick('button#poursuivrepopin');
            sleep(5);
        }
        if ($this->exts->exists('div#carousel-site-0')) {
            $this->exts->moveToElementAndClick('div#carousel-site-0');
            sleep(15);
        }
        if ($this->exts->exists('li#Factures')) {
            $this->exts->moveToElementAndClick('li#Factures');
            sleep(25);
            if ($this->exts->exists('div[class*="blocFactures"] button')) {
                $this->exts->moveToElementAndClick('div[class*="blocFactures"] button');
                sleep(15);
                $this->processInvoices();
            } else if ($this->exts->getElement('//table[contains(@class, "datatable")]//tr//span[contains(@class, "styleStatusActif")]', null, 'xpath') != null) {
                $site_address = $this->exts->getElement('//table[contains(@class, "datatable")]//tr//span[contains(@class, "styleStatusActif")]', null, 'xpath');
                try {
                    $this->exts->log('Click site_address button');
                    $site_address->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click site_address button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$site_address]);
                }
                sleep(30);
                $this->processInvoices();
            }
        }

        if ($this->exts->exists('a[href*="/vos-factures"], button[class*="visualiserfacture"]')) {
            $this->exts->moveToElementAndClick('a[href*="/vos-factures"], button[class*="visualiserfacture"]');
            sleep(15);

            $expand_buttons = $this->exts->getElements('button[aria-controls="factures"]');
            if (count($expand_buttons) > 0) {
                foreach ($expand_buttons as $expand_button) {
                    try {
                        $this->exts->log('Click expand button');
                        $expand_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click expand button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$expand_button]);
                    }
                    sleep(15);
                    $this->processVosFactures();
                }
            } else {
                $this->processVosFactures();
            }
        } else {
            $this->exts->moveToElementAndClick('ul#ice-header-menu a[title="Bill"]');
            sleep(5);
            $this->exts->moveToElementAndClick('.right-menu-item > #service-souscrit- > div:last-child > a');
            sleep(5);

            if ($this->exts->exists('ul.dropdown-menu-comptesFactu li')) {
                $accounts_len = count($this->exts->getElements('ul.dropdown-menu-comptesFactu li'));
                for ($i = 0; $i < $accounts_len; $i++) {
                    $acc_row = $this->exts->getElements('ul.dropdown-menu-comptesFactu li')[$i];

                    $this->exts->moveToElementAndClick('div[on-select*="compteFactuHistoSelectdHandler"] div.dropdown-toggle');
                    sleep(5);

                    try {
                        $this->exts->log('Click account button');
                        $acc_row->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click account button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$acc_row]);
                    }
                    sleep(15);

                    if ($this->exts->exists('div#histobutton button')) {
                        $this->exts->moveToElementAndClick('div#histobutton button');
                        sleep(15);
                    }

                    $this->exts->moveToElementAndType('input#dateDebut', Date('d/m/Y', strtotime("-1 year")));
                    $this->exts->moveToElementAndType('input#dateFin', Date('d/m/Y', strtotime("-0 year")));

                    $this->exts->moveToElementAndClick('div#ice-btn-histo');
                    sleep(15);

                    $this->downloadInvoice();

                    $this->downloadInvoice1();

                    $this->accountNo = "";
                }
            } else {
                if ($this->exts->exists('div#histobutton button')) {
                    $this->exts->moveToElementAndClick('div#histobutton button');
                    sleep(15);
                }

                if ($this->exts->exists('#btnToutesFactures')) {
                    $this->exts->moveToElementAndClick('#btnToutesFactures');
                    sleep(15);
                    if ($this->exts->exists('a[id*="histoFacture"], a[track-data="trackDataShowHistorique"]')) {
                        $this->exts->moveToElementAndClick('a[id*="histoFacture"], a[track-data="trackDataShowHistorique"]');
                        sleep(10);
                    }
                }

                $this->exts->moveToElementAndType('input#dateDebut', Date('d/m/Y', strtotime("-1 year")));
                $this->exts->moveToElementAndType('input#dateFin', Date('d/m/Y', strtotime("-0 year")));

                $this->exts->moveToElementAndClick('div#ice-btn-histo');
                sleep(15);
                $this->downloadInvoice();
            }
        }
        if ($this->exts->getElement('li#Facturation') != null) {
            $this->exts->moveToElementAndClick('li#Facturation');
            sleep(15);
            $billing_accounts = count($this->exts->getElements('//*[text()[contains(.,"Compte de facturation")]]', null, 'xpath'));
            for ($i = 0; $i < $billing_accounts; $i++) {
                $this->exts->moveToElementAndClick('li#Facturation');
                sleep(15);
                $billing_account = $this->exts->getElements('//*[text()[contains(.,"Compte de facturation")]]', null, 'xpath')[$i];
                try {
                    $this->exts->log('Click billing_account button');
                    $billing_account->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click billing_account button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$billing_account]);
                }
                $this->processFacturations();
            }
        }
    }

    function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice-downloadInvoice');

        try {
            if ($this->exts->exists('#dialog-cnil  #cnil-icone-croix-blanche')) {
                $this->exts->moveToElementAndClick('#dialog-cnil  #cnil-icone-croix-blanche');
                sleep(2);
            }

            $rows_len = count($this->exts->getElements('table#tableauFactures tbody tr'));
            for ($i = 0; $i < $rows_len; $i++) {
                $row = $this->exts->getElements('table#tableauFactures tbody tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if ($this->exts->getElement('td.ice-historique-download > div[ng-click]', $row) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('td.ice-historique-download > div[ng-click]', $row);
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    if ($this->exts->document_exists($invoiceFileName)) {
                        continue;
                    }

                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }

                    sleep(4);
                    $this->exts->wait_and_check_download('pdf');

                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    sleep(1);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            $paginations = $this->exts->getElements('table > tfoot ul.pagination-custom > li');
            if (count($paginations) > 0 && $restrictPages == 0) {
                if ($this->exts->exists('table > tfoot ul.pagination-custom > li:nth-child(' . (count($paginations) - 1) . ').ng-scope:not(.active)')) {
                    $pageCount++;
                    $this->exts->moveToElementAndClick('a#lastChildPagination');
                    sleep(5);
                    $this->downloadInvoice(1, $pageCount);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    function downloadInvoice1()
    {
        $this->exts->log("Begin download invoice 1");

        $this->exts->capture('4-List-invoice-downloadInvoice1');

        try {
            if ($this->exts->getElement('tr[class*="InvoiceRow"]') != null) {
                $receipts = $this->exts->getElements('tr[class*="InvoiceRow"]');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('div', $receipt);
                    if (count($tags) >= 5 && $this->exts->getElement('button', $tags[count($tags) - 1]) != null) {
                        $receiptDate = $tags[0]->getText();
                        $receiptUrl = $this->exts->getElement('button', $tags[count($tags) - 1]);
                        $this->exts->webdriver->executeScript(
                            "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                            array($receiptUrl, $i)
                        );

                        $receiptUrl = "button#invoice" . $i;
                        $receiptName = $tags[1]->getText();
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                        $receiptAmount = $tags[2]->getText();
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

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
                    $this->isNoInvoice = false;
                    $this->totalFiles += 1;

                    if ($this->exts->document_exists($invoice['receiptFileName'])) {
                        continue;
                    }

                    if ($this->exts->getElement($invoice['receiptUrl']) != null) {
                        $this->exts->moveToElementAndClick($invoice['receiptUrl']);

                        $this->exts->wait_and_check_download('pdf');
                        $this->exts->wait_and_check_download('pdf');
                        $this->exts->wait_and_check_download('pdf');
                        $this->exts->wait_and_check_download('pdf');
                        $this->exts->wait_and_check_download('pdf');

                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                        sleep(1);

                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->log("create file");
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    private function processVosFactures()
    {
        $this->exts->capture("4-invoices-page-processVosFactures");
        $invoices = [];

        $rows_len = count($this->exts->getElements('table tbody tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('table tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('button[id]', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button[id]', $tags[4]);
                $invoiceName = trim($this->exts->extract('button[id]', $tags[4], 'id'));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

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
                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                sleep(1);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page-processInvoices");
        $invoices = [];
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $date_from = $restrictPages == 0 ? strtotime('-2 years') : strtotime('-6 months');
        $this->exts->log("Download invoices from Date:" . date('m', $date_from) . '/' . date('Y', $date_from));
        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('button[class*="button"]', $tags[4]) != null) {
                $download_button = $this->exts->getElement('button[class*="button"]', $tags[4]);
                if ($download_button == null) continue;

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceDate: ' . $invoiceName);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }

        $rows_new = count($this->exts->getElements('//table[contains(@id, "billisTable")]//tbody//tr', null, 'xpath'));
        for ($k = 0; $k < $rows_new; $k++) {
            $row = $this->exts->getElements('//table[contains(@id, "billisTable")]//tbody//tr', null, 'xpath')[$k];
            $tags = $this->exts->getElements('td', $row);

            if (count($tags) >= 5 && $this->exts->getElement('.btndownloadlable', $tags[4]) != null) {
                $download_button = $this->exts->getElement('.btndownloadlable', $tags[4]);
                if ($download_button == null) continue;

                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');

                $this->exts->log('Date parsed: ' . $parsed_date);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }
    }

    private function processFacturations($paging_count = 1)
    {
        sleep(15);
        $this->exts->moveToElementAndClick('div[data-item="factures"]');
        sleep(2);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('//div[././div[././span[contains(text(),"Facture")]]]', null, 'xpath'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('//div[././div[././span[contains(text(),"Facture")]]]', null, 'xpath')[$i];
            $tags = $this->exts->getElements('div', $row);
            if (count($tags) >= 6) {
                $this->isNoInvoice = false;
                $download_button = $tags[5];
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
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
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) == '' && !file_exists($downloaded_file)) {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    }
                    if (trim($downloaded_file) == '' && !file_exists($downloaded_file)) {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    }
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('div.next button:not([disabled])') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('div.next button:not([disabled])');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
