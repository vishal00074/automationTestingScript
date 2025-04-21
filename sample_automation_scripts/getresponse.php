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
    private $remember_two_factor;


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

    // Server-Portal-ID: 9422 - Last modified: 19.09.2024 14:24:30 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://app.getresponse.com/login.html";
    public $loginUrl = "https://app.getresponse.com/login.html";
    public $homePageUrl = "https://secure.getresponse.com/billing-history";
    public $username_selector = "input[name=\"email\"]";
    public $password_selector = "input[name=\"password\"]";
    public $submit_button_selector = "button[data-ats-login-form=\"input_submit\"]";
    public $login_tryout = 0;
    public $twofa_form_selector = "div.form-field-container input[type=\"tel\"][data-ats-verify-free-account=\"phone_number_input\"]";
    public $restrictPages = 3;
    public $check_login_failed_selector = 'div[data-ats-login-form="error-message"] span';
    public $check_login_success_selector = 'div[data-ats-navbar="account_menu"], li a[href*="/logout.html"], button[data-ats-navbar="account_menu"]';


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);

            $this->exts->moveToElementAndClick('button[data-ats-dashboard-popup-modal="close_button"]');
            sleep(5);

            if ($this->checkLogin() == false && $this->exts->getElement("div.form-field-container input[type=\"tel\"][data-ats-verify-free-account=\"phone_number_input\"]") != null) {
                $this->processTFA();
            }

            if ($this->exts->urlContains('login/2-factor-authentication')) {
                $this->checkFillTwoFactor();
            }

            if ($this->exts->urlContains('/secure-login')) {
                $this->exts->moveToElementAndClick('div>button+a');
            }
        }

        if (strpos($this->exts->getUrl(), '/update_limited_account.html') !== false) {
            $this->exts->log("Your account has been limited.");
            $this->exts->account_not_ready();
        }


        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();

            if ($this->totalFiles == 0) {
                $this->exts->log("No invoice!!");
                $this->exts->no_invoice();
            } else {
                $this->exts->success();
            }
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $errorText = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log('errorText  ' . $errorText);
            if (stripos($errorText, strtolower('The email or password you entered are incorrect')) !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract('span[data-ats-login-form-input-error="email"]')), strtolower('Incorrect email')) !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract('div#verificationCode-error')), strtolower('Du hast einen falschen Authentifizierungscode eingegeben. Bitte versuche es erneut')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
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
                $this->checkFillRecaptcha();
                sleep(2);

                if ($this->exts->getElement($this->username_selector) != null) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }

                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");

                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);

                $this->checkFillRecaptcha();
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


    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->getElement($this->check_login_success_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    function processTFA()
    {
        try {
            $this->exts->log("Current URL - " . $this->exts->getUrl());

            if ($this->exts->getElement($this->twofa_form_selector) != null) {
                $this->handleTwoFactorCode($this->twofa_form_selector, "button.button[data-ats-verify-free-account=\"verification_code_button\"]");
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

                if ($this->exts->getElement($this->remember_two_factor) != null) {
                    $checkboxElements = $this->exts->getElements($this->remember_two_factor);
                    if (count($checkboxElements) > 0) {
                        $this->exts->log("Check remember two factor");
                        $bValue = false;
                        // this behaviour is not found in current connection 
                        // $bValue = $checkboxElements[0]->isSelected();
                        // if ($bValue == false) {
                        //     $checkboxElements[0]->sendKeys(WebDriverKeys::SPACE);
                        // }
                    }
                    sleep(2);
                }

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                if ($this->exts->getElement($submit_btn_selector) != null) {
                    $this->exts->moveToElementAndClick($submit_btn_selector);
                    sleep(10);
                } else {
                    sleep(5);
                    $this->exts->moveToElementAndClick($submit_btn_selector);
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

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[aria-label*="code_input"], input[type="tel"]';
        $two_factor_message_selector = 'div[aria-label="2fa_form"] div[role="heading"], div[aria-label="2fa_form"] > div > span[color="strong"], form > div > span';
        $two_factor_submit_selector = 'button[data-ats-2fa-login-button="on_complete_2fa_setup_login_button"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim(explode('You can also', $this->exts->two_factor_notif_msg_en)[0]);
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
                $two_factor_code_split = str_split($two_factor_code);

                for ($i = 0; $i < 5; $i++) {
                    $this->exts->type_key_by_xdotool('Tab');
                    sleep(1);
                }
                foreach($two_factor_code_split  as $value){
                    $this->exts->type_text_by_xdotool($value);
                    sleep(1);
                }
               

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

    function invoicePage()
    {
        $this->exts->log("invoice Page");

        $this->exts->openUrl($this->homePageUrl);
        sleep(20);

        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            $this->fillForm(0);
            sleep(5);

            if (!$this->checkLogin()) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }

            $this->exts->openUrl($this->homePageUrl);
            sleep(20);
        }

        if ($this->exts->exists('button[data-ats-dashboard-popup-modal="close_button"]')) {
            $this->exts->moveToElementAndClick('button[data-ats-dashboard-popup-modal="close_button"]');
            sleep(3);
        }

        // $this->exts->openUrl('https://secure.getresponse.com/billing-history');
        $this->exts->moveToElementAndClick('section > a.invoice-link');
        sleep(7);
        $this->exts->moveToElementAndClick('a[data-ats-billing-history="invoice-panel-url"]');
        sleep(10);
        $this->downloadInvoice();
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    function downloadInvoice($paging_count = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('table > tbody > tr') != null) {
                $receipts = $this->exts->getElements('table > tbody > tr');
                $invoices = array();
                foreach ($receipts as $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 5 && $this->exts->getElement('td a[href*="app/document"]', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptUrl = $this->exts->extract('td a[href*="app/document"]', $receipt, 'href');
                        $receiptName = trim(end(explode('/document/', $receiptUrl)));
                        $receiptName = explode('/', $receiptName)[0];
                        $receiptFileName = $receiptName . '.pdf';
                        $parsed_date = $this->exts->parse_date($receiptDate);
                        $receiptAmount = $tags[3]->getText();
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
                    $this->totalFiles += 1;
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }

                $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                if (
                    $restrictPages == 0 &&
                    $paging_count < 50 &&
                    $this->exts->getElement('button[class*="numberButton"] + button[class*="chevronButton"]:not([disabled])') != null
                ) {
                    $paging_count++;
                    $this->exts->moveToElementAndClick('button[class*="numberButton"] + button[class*="chevronButton"]:not([disabled])');
                    sleep(5);
                    $this->downloadInvoice($paging_count);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
