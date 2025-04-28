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

    // Server-Portal-ID: 1188 - Last modified: 18.04.2025 13:58:31 UTC - User: 1

    public $baseUrl = "https://www.lufthansa.com/online/portal/lh/de/homepage?l=";
    public $username_selector = "input#id-loginStepOne-textfield";
    public $id_selector = "input#id-mamLoginStepOne-textfield";
    public $password_selector = "input#id-loginStepTwoPassword-textfield";
    public $pin_selector = "input#id-mamLoginStepTwoPin-textfield";
    public $submit_btn = "button.travelid-login__loginButton";
    public $continue_btn = 'button.travelid-login__continueButton';
    public $id_submit_btn = "form.travelid-login__form--mamLogin button.travelid-login__loginButton";
    public $id_continue_btn = 'form.travelid-login__form--mamLogin button.travelid-login__continueButton';
    public $logout_btn = "#lid-loginlayer";
    public $logout_btn_1 = "button.btn-profile, span#lh-loginModule-name";
    public $login_failed_selector = "div.travelid-form__errorBoxContent";

    public $lufthansa_id = 0;
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {

        $this->exts->temp_keep_useragent = $this->exts->send_websocket_event(
            $this->exts->current_context->webSocketDebuggerUrl,
            "Network.setUserAgentOverride",
            '',
            ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"]
        );

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);

        sleep(10);

        if ($this->exts->exists('maui-link-button[variant="close"]')) {
            $this->exts->click_by_xdotool('maui-link-button[variant="close"]');
            sleep(5);
        }

        if ($this->exts->exists('button#cm-acceptAll')) {
            $this->exts->click_by_xdotool('button#cm-acceptAll');
            sleep(5);
        }

        if ($this->exts->exists('.consent-manager-inner button#cm-selectSpecific')) {
            $this->exts->click_by_xdotool('.consent-manager-inner button#cm-selectSpecific');
            sleep(10);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('Not Logged in : : ');

            $this->exts->openUrl($this->baseUrl);

            $this->waitForSelectors("maui-link-button[variant='close']", 10, 2);

            if ($this->exts->exists('maui-link-button[variant="close"]')) {
                $this->exts->click_by_xdotool('maui-link-button[variant="close"]');
                sleep(5);
            }

            if ($this->exts->exists('button#cm-acceptAll')) {
                $this->exts->click_by_xdotool('button#cm-acceptAll');
                sleep(5);
            }
            if ($this->exts->exists('.consent-manager-inner button#cm-selectSpecific')) {
                $this->exts->click_by_xdotool('.consent-manager-inner button#cm-selectSpecific');
                sleep(5);
            }
            if ($this->exts->exists('button[data-identifier-track="login-flag-modal_click_login-flag-modal-close"]')) {
                $this->exts->click_by_xdotool('button[data-identifier-track="login-flag-modal_click_login-flag-modal-close"]');
                sleep(5);
            }
            if ($this->exts->exists('div.one-id-login a')) {
                $this->exts->click_by_xdotool('div.one-id-login a');
            }

            sleep(10);
            $this->fillForm($count);

            $this->waitForSelectors("a[name='welcome_skipMigration']", 10, 2);

            if ($this->exts->exists('a[name="welcome_skipMigration"]')) {
                $this->exts->click_by_xdotool('a[name="welcome_skipMigration"]');
                sleep(20);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(5);

            $this->exts->openUrl('https://www.lufthansa.com/de/en/bookinglist');

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log($this->exts->getUrl());
            if ($this->exts->getElementByText($this->login_failed_selector, ['password', 'Passwort', 'passwort', 'card number', 'email address'], null, false) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
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

    private function splitName($name)
    {
        $name = trim($name);
        $last_name = (strpos($name, ' ') === false) ? '' : array_pop(explode(' ', $name));
        $first_name = (strpos($name, ' ') === false) ? $name : explode(' ', $name)[0];
        return array($first_name, $last_name);
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->exts->type_key_by_xdotool("ctrl+l");
            $this->exts->type_key_by_xdotool("Return");
            sleep(15);
            if ($this->exts->exists($this->username_selector)) {
                sleep(2);

                $this->exts->log("Enter Username");

                $this->exts->click_by_xdotool($this->username_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($this->username);
                sleep(2);

                if ($this->exts->exists($this->continue_btn)) {
                    $this->exts->click_by_xdotool($this->continue_btn);
                    sleep(7);
                }

                $this->exts->log("Enter Password");

                $this->exts->click_by_xdotool($this->password_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($this->password);
                sleep(2);

                if ($this->exts->exists($this->continue_btn)) {
                    $this->exts->click_by_xdotool($this->submit_btn);
                }
                sleep(10);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function fillFormId($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->id_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->id_selector);
                $this->exts->type_text_by_xdotool($this->username);
                sleep(2);

                if ($this->exts->exists($this->id_continue_btn)) {
                    $this->exts->click_by_xdotool($this->id_continue_btn);
                    sleep(5);
                }

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->pin_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);

                $this->exts->capture("2-login-page-filled");

                if ($this->exts->exists($this->id_submit_btn)) {
                    $this->exts->click_by_xdotool($this->id_submit_btn);
                }
                sleep(10);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillLoginSecondary($count)
    {
        $this->exts->log(__FUNCTION__ . "  Begin " . $count);
        try {
            sleep(3);
            if ((int)@$this->lufthansa_id == 0) {
                if ($this->exts->exists("a[id*=milesMore-toggle]")) {
                    $this->exts->click_element("a[id*=milesMore-toggle]");
                    sleep(4);
                    $this->fillForm_1($count);
                }
            } else {
                if ($this->exts->exists("a[id*=lufthansaID-toggle]")) {
                    $this->exts->click_element("a[id*=lufthansaID-toggle]");
                    sleep(4);
                    $this->fillForm_1($count);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
        $this->exts->log(__FUNCTION__ . "  End " . $count);
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

    private function checkAndSolveGeeTestCaptcha()
    {
        if ($this->exts->exists("div[id='captcha-box']")) {
            $this->exts->log("Found captcha, process to solve");
            $geetestKey = $this->exts->execute_javascript("return window.GeeGT;");
            $api_server = 'api-na.geetest.com';
            $script = '
			function httpGet(theUrl) {
				var xmlHttp = new XMLHttpRequest();
				xmlHttp.open( "GET", theUrl, false ); // false for synchronous request
				xmlHttp.send( null );
				return xmlHttp.responseText;
			}
			return httpGet("https://book.lufthansa.com/distil_r_captcha_challenge");
			';
            // return httpGet("https://book.lufthansa.com/lh/dyn/air-lh/servicing/cockpit");

            $geetestChallenge = explode(";", $this->exts->execute_javascript($script))[0];
            $this->exts->log("key: " . $geetestKey . " challenge: " . $geetestChallenge);
            $this->exts->moveToElementAndClick('.geetest_btn');
            sleep(1);
            $this->exts->capture('after-click-geetest-button');
            $this->exts->processGeeTestCaptcha('form[id="distilCaptchaForm"]', $geetestKey, $geetestChallenge, $this->exts->getUrl(), $api_server);
        } else {
            $this->exts->log("No captcha found!");
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitForSelectors($this->logout_btn, 10, 2);
            if ($this->exts->exists($this->logout_btn) || $this->exts->exists($this->logout_btn_1)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function multiple_user_agent($i = null)
    {
        $user_agent = [
            '0' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
            '1' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            '2' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
            '3' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            '4' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            '5' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36',
            '6' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        ];

        if ($i === null) {
            return count($user_agent);
        }

        return isset($user_agent[$i]) ? $user_agent[$i] : null;
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

    private function downloadInvoice($name, $count = 0)
    {
        $this->exts->log("Begin download invoice - " . $name . " with count " . $count);
        try {
            $this->waitForSelectors(".bookingCard", 10, 2);

            if (!$this->exts->exists('.bookingCard') && !$this->exts->exists('div.geetest_radar_tip, a[id*=lufthansaID-toggle]')) {
                $this->exts->execute_javascript('window.location.reload();');
            }

            if ($this->exts->exists('.bookingCard')) {
                sleep(5);
                $this->exts->capture("2-download-invoice");
                $invoices = array();

                $arr = $this->splitName($name);
                $firstName = $arr[0];
                $lastName = $arr[1];

                $max_pages = 50;
                for ($paging_count = 1; $paging_count <= $max_pages; $paging_count++) {
                    $receipts = [];
                    $receipts = $this->exts->getElements('.bookingCard[id], .bookingCards .bookingCard');
                    $this->exts->log(__FUNCTION__ . " : Receipts Found : " . count($receipts));

                    foreach ($receipts as $receipt) {
                        try {
                            sleep(5);
                            $bookingCode = $this->exts->extract('[title*="Buchungscode:"]', $receipt);

                            if ($bookingCode == null) {
                                $bookingCode = $this->exts->extract('[title*="Booking code:"]', $receipt);
                            }
                            $receiptDate = $this->exts->extract('div.infoLarge', $receipt);
                        } catch (\Exception $exception) {
                            $bookingCode = null;
                        }

                        if ($bookingCode != null && $receiptDate != null) {
                            $bookingCode = trim(array_pop(explode(":", $bookingCode)));
                            $receiptDate = trim(array_pop(explode(",", $receiptDate)));

                            $this->exts->log($bookingCode);
                            $parsed_date = $this->exts->parse_date($receiptDate, 'd F Y', 'Y-m-d');
                            $this->exts->log($parsed_date);
                            $receiptFileName = $bookingCode . '.pdf';
                            $this->exts->log(__FUNCTION__ . " : receiptFileName is " . $receiptFileName);
                            $this->exts->log(__FUNCTION__ . " : firstName is " . $firstName);
                            $this->exts->log(__FUNCTION__ . " : lastName is " . $lastName);
                            $invoice = array(
                                'bookingCode' => $bookingCode,
                                'firstName' => $firstName,
                                'lastName' => $lastName,
                                'parsed_date' => $parsed_date,
                                'receiptAmount' => '',
                                'receiptFileName' => $receiptFileName
                            );
                            array_push($invoices, $invoice);
                        }
                    }

                    $this->exts->closeCurrentTab();
                    // check and go to next page if have paging
                    if ($this->exts->exists('.bookingList .paginationList li.pageActive + li.pageContainer')) {
                        $this->exts->click_element('.bookingList .paginationList li.pageActive + li.pageContainer');
                        sleep(15);
                    } else {
                        break;
                    }
                }
            } else {
                if ($this->exts->getElement("div.geetest_radar_tip") != null) {
                    $count++;
                    if ($count < 5) {
                        sleep(5); // wait for some time and reload
                        //$this->exts->restart();
                        // $this->initPortal($count+1);
                        $this->checkAndSolveGeeTestCaptcha();
                        $this->downloadInvoice($name, $count);
                    } else {
                        $this->exts->log(__FUNCTION__ . " : Found geetest recaptcha. call init required");
                        $this->exts->capture("geetest-captcha-failed");
                        // $this->exts->init_required();
                    }
                } else if ($this->exts->getElement("a[id*=lufthansaID-toggle]") != null) {
                    $this->checkFillLoginSecondary($count++);
                    $this->downloadInvoice($name, $count++);
                } else {
                    $this->exts->log("No invoice !!! ");
                    $this->exts->no_invoice();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    private function download_ticket_receipt($receipt_id = '')
    {
        if ($this->exts->getElement('#dlBoardingPass') != null) {
            $this->isNoInvoice = false;
            $fileName = '';
            if ($receipt_id != '') {
                $fileName = $receipt_id . '.pdf';
            }
            $this->exts->click_element('#dlBoardingPass');
            sleep(5);

            $this->checkFillRecaptcha();

            sleep(5);
            $downloaded_file = "";
            $handles = $this->exts->get_all_tabs();
            if (count($handles) > 2) {
                $this->exts->switchToTab(end($handles));
                $downloaded_file = $this->exts->direct_download($this->exts->getUrl(), 'pdf', $fileName);
            } else {
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $fileName);
            }

            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                if ($receipt_id == '') {
                    $receipt_id = basename($downloaded_file);
                    $receipt_id = str_replace('.pdf', '', $receipt_id);
                }
                $pdf_content = file_get_contents($downloaded_file);
                if (stripos($pdf_content, "%PDF") !== false) {

                    $this->exts->new_invoice($receipt_id, '', '', $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . " :: Not Valid PDF - " . $filename);
                }
            } else {
                $this->exts->log(__FUNCTION__ . " :: No File Downloaded ? - " . $downloaded_file);
            }

            $handles = $this->exts->get_all_tabs();
            if (count($handles) > 2) {
                $this->exts->switchToTab(end($handles));
                $this->exts->closeCurrentTab();
                $handles = $this->exts->get_all_tabs();
                $this->exts->switchToTab($handles[$current_tab - 1]);
            }
            $this->exts->log(__FUNCTION__ . " : Current url is : " . $this->exts->getUrl());
        } else {
            $this->exts->log(__FUNCTION__ . " :: No PDF file for this booking - " . $invoice['bookingCode']);
        }
    }

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->execute_javascript('document.querySelector("my-trips").shadowRoot.querySelectorAll("section div ol li [data-testid=\'manage-my-booking-button\']").length');
        for ($i = 0; $i < $rows; $i++) {
            $this->exts->execute_javascript('document.querySelector("my-trips").shadowRoot.querySelectorAll("section div ol li [data-testid=\'manage-my-booking-button\']")[' . $i . '].click()');
            sleep(20);
            $this->isNoInvoice = false;
            $download_modal_button = $this->exts->getElement('button.passenger-receipt-label');
            try {
                $this->exts->log('Click download_modal button');
                $download_modal_button->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$download_modal_button]);
            }
            sleep(15);
            $invoiceName = $this->exts->extract('div.travel-document-id');
            $invoiceFileName = $invoiceName . '.pdf';

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->moveToElementAndClick('a.lh-icon-download');
                sleep(8);
                $this->exts->markCurrentTabByName('rootTab');
                $invoice_tab = $this->exts->findTabMatchedUrl(['documents/download']);
                if ($invoice_tab != null) {
                    $this->exts->switchToTab($invoice_tab);
                    $this->exts->moveToElementAndClick('a[href*="/doc/ETicket/"]');
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                // go back to root tab
                $this->exts->switchToTab($this->exts->getMarkedTab('rootTab'));
            }
            $this->exts->openUrl('https://www.lufthansa.com/de/en/bookinglist');
            sleep(20);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
