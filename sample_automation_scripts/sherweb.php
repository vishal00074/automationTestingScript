<?php // migrated updated login button selector and code to naviagated login form 

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 72987 - Last modified: 02.04.2024 13:38:59 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://www.sherweb.com/customer-login";
    public $username_selector = '#Username';
    public $password_selector = '#Password';
    public $submit_btn = '.loginButton, #usernamepassword-div button.btn';
    public $logout_btn = '.fa-power-off, a[data-bind*="logout()"], .billing a[href*="/billing/invoices"], button.UserMenu-logout';

    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }


        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(10);


            if ($this->exts->queryXpath('.//button[@onclick="window.open(\'https://cumulus.sherweb.com/\', \'_blank\', \'noopener\')"]') != null) {
                $this->exts->click_element('.//button[@onclick="window.open(\'https://cumulus.sherweb.com/\', \'_blank\', \'noopener\')"]');
                sleep(10);
            }

            $this->exts->switchToNewestActiveTab();
            sleep(2);
            $this->exts->closeAllTabsExcept();
            sleep(2);
        }

        if (!$this->checkLogin()) {
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(20);
            $this->checkFillTwoFactor();
            for ($i = 0; $i < 10 && $this->exts->exists('#SplashLoadingSpinner'); $i++) {
                sleep(2);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->moveToElementAndClick('li.billing a[href*="/billing/invoices"]');
            sleep(20);
            $this->downloadInvoice(0);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {

            if ($this->exts->exists('form[aspcontroller="Logout"]') && strpos(strtolower($this->exts->extract('.title')), 'unauthorized') !== false) {
                $this->exts->no_permission();
            }
            if ($this->exts->exists('app-mfa-required')) {
                $this->exts->account_not_ready();
            }
            $this->exts->capture("LoginFailed");
            if ($this->exts->exists('.validation-summary-errors')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            if ($this->exts->exists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

                $this->exts->moveToElementAndClick('button[type="submit"]');
                sleep(7);


                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

                $this->exts->click_element('#EnableSSO');

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha(0);

                $this->exts->click_element($this->submit_btn);
            } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha(0);
                $count++;
                $this->fillForm($count);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }


    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="recaptcha/api2/anchor?ar"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
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
            } else {
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'div#token-div:not([style*="none"]) input#TwoFactorAuthenticationToken';
        $two_factor_submit_selector = 'div#token-div:not([style*="none"]) button[type="submit"]';
        $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#sms-required';
        if ($this->exts->getElement($two_factor_message_selector) == null && $this->exts->extract('div#token-div:not([style*="none"]) span#email-required') !== '') {
            $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#email-required';
        } else if ($this->exts->extract('div#token-div:not([style*="none"]) span#otp-required') !== '') {
            $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#otp-required';
        }


        if (
            $this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3 &&
            $this->exts->getElement($two_factor_message_selector) != null && $this->exts->extract($two_factor_message_selector, null, 'innerText') !== ''
        ) {
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
                sleep(2);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->logout_btn) && $this->exts->exists($this->username_selector) == false) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    function downloadInvoice($count)
    {
        $this->exts->log("Begin download invoice - " . $count);
        try {
            if ($this->exts->exists('#invoices > tr')) {
                $this->exts->capture("2-download-invoice");
                $invoices = array();

                $receipts = $this->exts->getElements('#invoices > tr');
                foreach ($receipts as $receipt) {
                    try {
                        $receiptDate = trim($this->exts->extract('td[data-bind*="Date"]', $receipt));
                    } catch (\Exception $exception) {
                        $receiptDate = null;
                    }

                    if ($receiptDate != null && $this->exts->extract('td[data-bind*="GrandTotal"]', $receipt) != null) {
                        $this->exts->log($receiptDate);

                        $receiptName = $this->exts->extract('.invoice-number-column', $receipt);

                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd/m/Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = trim($this->exts->extract('td[data-bind*="GrandTotal"]', $receipt));
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount);
                        $receiptAmount = $receiptAmount . ' USD';

                        $receiptUrl = '[id="' . $receipt->getAttribute('id') . '"]';

                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                        $this->isNoInvoice = false;
                    }
                }

                foreach ($invoices as $invoice) {
                    try {
                        $this->exts->moveToElementAndClick($invoice['receiptUrl']);
                        sleep(10);
                        $downloaded_file = $this->exts->click_and_download('a[data-bind*=getInvoiceInPdf]', 'pdf', $invoice['receiptFileName']);
                        sleep(1);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                            sleep(2);
                        }
                        $this->exts->moveToElementAndClick('[data-bind*=closePanel]');
                        sleep(3);
                    } catch (\Exception $exception) {
                        $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                    }
                };
            } else {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
