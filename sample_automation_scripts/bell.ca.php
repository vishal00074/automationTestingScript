<?php // migrated

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

    // Server-Portal-ID: 8578 - Last modified: 26.04.2024 14:16:14 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://business.bell.ca/Self-Serve/secure/login?lang=en&prov=";
    public $loginUrl = "https://business.bell.ca/Self-Serve/secure/login?lang=en&prov=";
    public $homePageUrl = "https://business.bell.ca/self-serve/";
    public $region = "";
    public $username_selector = "input#USER";
    public $password_selector = "input#PASSWORD";
    public $submit_button_selector = "form#loginForm a.button";
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;



    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->region = isset($this->exts->config_array["region"]) ? $this->exts->config_array["region"] : "";
        $this->exts->log($this->region);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->loginUrl = $this->baseUrl = $this->baseUrl . $this->region;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);
            if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement("div.message ul#loginErrorMsg li, div#divErrorMsg ul#divLoginValidationMessages li#summaryMessageLogin") == null) {
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
                $this->fillForm(0);
                sleep(10);
            }

            if ($this->exts->exists('input#verificationCode')) {
                $this->checkFillTwoFactor();
            }


            $err_msg = "";
            if ($this->exts->getElement("div.message ul#loginErrorMsg li") != null) {
                $err_msg = trim($this->exts->getElements("div.message ul#loginErrorMsg li")[0]->getText());
            }

            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }

            $err_msg1 = "";
            if ($this->exts->getElement("div#divErrorMsg ul#divLoginValidationMessages li#summaryMessageLogin") != null) {
                $err_msg1 = trim($this->exts->getElements("div#divErrorMsg ul#divLoginValidationMessages li#summaryMessageLogin")[0]->getText());
            }

            if ($err_msg1 != "" && $err_msg1 != null) {
                $this->exts->log($err_msg1);
                $this->exts->loginFailure(1);
            }

            if (strpos($this->exts->getURL(), "/Self-Serve/Registration?isForFullRegistration=True") !== false) {
                $this->exts->log("account not ready!!!");
                $this->exts->account_not_ready();
            }

            $title_page = strtolower($this->exts->extract('h1.simplified-header-area-title'));
            if (strpos($title_page, 'password update') !== false) {
                $this->exts->log("Password need update!!");
                $this->exts->account_not_ready();
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {

                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

                $error_text = strtolower($this->exts->extract('ul#loginErrorMsg li'));
                $this->exts->capture("LoginFailed");
                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
                if (stripos($error_text, strtolower('invalid credentials')) !== false) {
                    $this->exts->loginFailure(1);
                } else {

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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null || $this->exts->getElement($this->password_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                if ($this->exts->getElement($this->username_selector) != null) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }



                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);


                if ($this->exts->getElement("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") != null && $this->exts->getElement("textarea[name=\"g-recaptcha-response\"]") != null) {
                    $this->checkFillRecaptcha($count);
                } else {
                    $this->exts->moveToElementAndClick($this->submit_button_selector);
                }
                sleep(10);
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
					if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
					} else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
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
                } else {
                    $this->exts->moveToElementAndClick($this->submit_button_selector);
                }
            } else {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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
            if ($this->exts->getElement("a[href=\"/Self-Serve/Secure/logout\"]") != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }


    private function checkFillTwoFactor()
    {

        $two_factor_selector = 'input#verificationCode';
        $two_factor_message_selector = 'label[for="recoveryEmailList"],#recoveryEmailList option[value="0"]';
        $two_factor_submit_selector = 'button#continueButton';
        $two_factor_resend_selector = 'a#re-send-link';


        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                // <!-- if($this->exts->exists($two_factor_resend_selector)){
                // 	$this->exts->moveToElementAndClick($two_factor_resend_selector);
                // 	sleep(1);
                // } -->
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



    function invoicePage()
    {
        $this->exts->log("invoice Page");

        $str = "var div = document.querySelector('div[class=\"padder toTextLeft\"]'); if (div != null) {  div.style.display = \"none\"; }";
        $this->exts->execute_javascript($str);
        sleep(2);

        if ($this->exts->getElement('div#navigationMain a[href="/bills_payments/"]') != null) {
            $this->exts->moveToElementAndClick('div#navigationMain a[href="/bills_payments/"]');
            sleep(15);
        }

        $bill_tabs = $this->exts->getElements('ul#navigationBillingPaymentsJs li a');
        if (count($bill_tabs) > 0) {
            $bill_tabs = $this->exts->getElements('ul#navigationBillingPaymentsJs li a');
            try {
                $this->exts->log('Click current bill tab button');
                $bill_tabs[0]->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click current bill tab button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$bill_tabs[0]]);
            }
            sleep(15);

            $this->downloadCurrentInvoice();

            $bill_tabs = $this->exts->getElements('ul#navigationBillingPaymentsJs li a');
            try {
                $this->exts->log('Click current bill tab button');
                end($bill_tabs)->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click current bill tab button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [end($bill_tabs)]);
            }

            sleep(15);

            $this->setPeriodDays();

            $this->downloadInvoice();
        } else {
            $this->downloadCurrentInvoice();
            $this->setPeriodDays();
            $this->downloadInvoice();
        }

        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }

        $this->exts->success();
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $option_value;
            $this->exts->click_by_xdotool($select_box);
            sleep(2);
            $optionIndex = $this->exts->executeSafeScript('
			const selectBox = document.querySelector("' . $select_box . '");
			const targetValue = "' . $option_value . '";
			const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
			return optionIndex;
		');
            $this->exts->log($optionIndex);
            sleep(1);
            for ($i = 0; $i < $optionIndex; $i++) {
                $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
                // Simulate pressing the down arrow key
                $this->exts->type_key_by_xdotool('Down');
                sleep(1);
            }
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    private function setPeriodDays()
    {
        if ($this->exts->exists('select.pastDateFilterDropdown option')) {
            $this->changeSelectbox('select.pastDateFilterDropdown', '0');
            sleep(5);

            $startDate = Date('m/d/Y', strtotime("-100 days"));
            if ($this->restrictPages == 0) {
                $startDate = Date('m/d/Y', strtotime("-2 years"));
            }

            $endDate = Date('m/d/Y', strtotime("-0 days"));

            $this->exts->log($startDate);
            $this->exts->log($endDate);

            if ($this->exts->getElement('input[id*="fromDate"]') != null) {
                $fromEl = $this->exts->getElement('input[id*="fromDate"]');
                $this->exts->execute_javascript("arguments[0].removeAttribute('readonly','readonly')", array($fromEl));
                sleep(2);
                $this->exts->moveToElementAndType('input[id*="fromDate"]', $startDate);
                sleep(2);
            }

            if ($this->exts->getElement('input[id*="toDate"]') != null) {
                $toEl = $this->exts->getElement('input[id*="toDate"]');
                $this->exts->execute_javascript("arguments[0].removeAttribute('readonly','readonly')", array($toEl));
                sleep(2);
                $this->exts->moveToElementAndType('input[id*="toDate"]', $endDate);
                sleep(2);
            }

            $this->exts->moveToElementAndClick('a.loadingBoxOpener');
            sleep(15);
        }
    }


    private function downloadCurrentInvoice()
    {
        $this->exts->capture("4-current-invoices-page");
        $invoices = [];
        $base_handle = $this->exts->current_chrome_tab;

        $count_rows = count($this->exts->getElements('div[id*="demoCurrentBillsJs"] table:not(.accountFilter) tbody tr'));
        for ($i = 0; $i < $count_rows; $i++) {
            $row = $this->exts->getElements('div[id*="demoCurrentBillsJs"] table:not(.accountFilter) tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a', $tags[1]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a', $tags[1]);
                $invoiceDate = trim($this->exts->extract('a', $tags[1], 'innerText'));
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M. d, Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' USD';

                try {
                    $this->exts->log('Click download_button button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download_button button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                sleep(15);

                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 0) {
                    $this->exts->switchToTab(end($handles));
                }

                if ($this->exts->exists('a[class*="download-print-bill-pdf"]')) {
                    $this->exts->moveToElementAndClick('a[class*="download-print-bill-pdf"]');
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceFileName = basename($downloaded_file);
                        $invoiceName = explode('.pdf', $invoiceFileName)[0];
                        $invoiceName = explode('(', $invoiceName)[0];
                        $invoiceName = preg_replace('/[\n\s\-]/', '', $invoiceName);
                        $this->exts->log('Final invoice name: ' . $invoiceName);
                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ');
                    }
                }

                // $handles = $this->exts->get_all_tabs();
                // if (count($handles) > 1) {
                //     // $this->exts->webdriver->close();
                //     sleep(3);
                // }

                $this->exts->switchToTab($base_handle);
                sleep(3);
                $this->exts->closeAllTabsButThis();
                sleep(3);
            }
        }
    }

    /**
     *method to download incoice
     */
    function downloadInvoice($current_page = 1)
    {
        $this->exts->capture("4-pass-invoices-page");
        $invoices = [];
        $base_handle = $this->exts->current_chrome_tab;

        $count_rows = count($this->exts->getElements('table[id*="pastBilltable"] tbody tr'));
        for ($i = 0; $i < $count_rows; $i++) {
            $row = $this->exts->getElements('table[id*="pastBilltable"] tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a', $tags[0]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a', $tags[0]);
                $invoiceDate = trim($this->exts->extract('a', $tags[0], 'innerText'));
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'M. d, Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';

                try {
                    $this->exts->log('Click download_button button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download_button button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                sleep(15);

                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 0) {
                    $this->exts->switchToTab(end($handles));
                }

                if ($this->exts->exists('a[class*="download-print-bill-pdf"]')) {
                    $this->exts->moveToElementAndClick('a[class*="download-print-bill-pdf"]');
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceFileName = basename($downloaded_file);
                        $invoiceName = explode('.pdf', $invoiceFileName)[0];
                        $invoiceName = explode('(', $invoiceName)[0];
                        $invoiceName = preg_replace('/[\n\s\-]/', '', $invoiceName);
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
                        $this->exts->log(__FUNCTION__ . '::No download ');
                    }
                }

                $this->exts->switchToTab($base_handle);
                sleep(3);
                $this->exts->closeAllTabsButThis();
                sleep(3);
            }
        }

        if (
            $this->restrictPages == 0 &&
            $current_page < 50 &&
            $this->exts->getElement('ul.paginationList li.next a:not(.disabled)') != null
        ) {
            $current_page++;
            $this->exts->moveToElementAndClick('ul.paginationList li.next a:not(.disabled)');
            sleep(5);
            $this->downloadInvoice($current_page);
        }
    }

    public function downloadAgain($invoice, $count)
    {
        $this->exts->log("reDownload " . $count);

        if ($this->exts->getElement("a.omniture-scan-download-print-bill-pdf") != null) {
            $downloaded_file = $this->exts->click_and_download('a.omniture-scan-download-print-bill-pdf', 'pdf', $invoice['receiptFileName']);
            // $downloaded_file = $this->exts->download_current($receiptFileName);
            $this->exts->log("downloaded file");
            sleep(5);
            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                $this->exts->log("create file");
                $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                sleep(5);
            } else {
                $count += 1;
                if ($count < 10) {
                    $this->downloadAgain($invoice, $count);
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
