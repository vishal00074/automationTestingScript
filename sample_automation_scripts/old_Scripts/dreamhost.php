<?php // replace waitTillPresent to waitFor

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

    // Server-Portal-ID: 8580 - Last modified: 16.06.2025 14:45:57 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://panel.dreamhost.com/index.cgi";
    public $username_selector = '.login-form input#username';
    public $password_selector = '.login-form input#password';
    public $submit_button_selector = '.login-form button[type="submit"]';
    public $login_tryout = 0;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->baseUrl);
            }
        } else {
            $this->exts->openUrl($this->baseUrl);
        }

        if (!$isCookieLoginSuccess) {
            sleep(10);
            $this->fillForm(0);
            sleep(10);

            if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick('button[type="submit"]');
            }

            if ($this->exts->exists('input#mfa_password_1')) {
                $this->process2FA('input#mfa_password_1', '.login-mfa.active button[type="submit"]');
            }
            $this->checkFillTwoFactor();

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    private function process2FA($two_factor_selector, $submit_btn_selector)
    {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        if ($this->exts->querySelector('label[for="mfa_password_1"]') != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_de = $this->exts->extract('label[for="mfa_password_1"]');
        }

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->log("The msg: " . $this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            try {
                $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                if ($this->exts->querySelector($submit_btn_selector) != null) {
                    $this->exts->querySelector($submit_btn_selector)->click();
                    sleep(10);
                } else {
                    sleep(5);
                    $this->exts->querySelector($submit_btn_selector)->click();
                    sleep(10);
                }

                if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->process2FA($two_factor_selector, $submit_btn_selector);
                }
            } catch (\Exception $exception) {
                $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            }
        }
    }

    private function checkFillTwoFactor()
    {
        if ($this->exts->exists('.login-mfa p')) {

            $two_factor_message_selector = '.login-mfa p';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = str_replace('Click the link in that email from your current IP to verify your identity and continue logging in', '', $this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
                $this->exts->openUrl($two_factor_code);
                sleep(25);
                $this->exts->capture("after-open-url-two-factor");
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
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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


    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(1);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int) $this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                for ($i = 0; $i <= 5; $i++) {
                    $this->checkFillRecaptcha();
                }
                $this->exts->capture("1-login-filled");
                sleep(3);
                $this->exts->click_by_xdotool($this->submit_button_selector);
                sleep(3);
                $this->waitFor('div.Alert');
                $err_msg = $this->exts->extract('div.Alert');
                if ($err_msg != "" && $err_msg != null) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
            } else {
                if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
                    $this->checkFillRecaptcha();
                    $this->fillForm($count + 1);
                }
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists('a[href*="Nscmd=Nlogout"], div#app-shared-dashboard a[href*="domain.dashboard"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        $this->exts->log("Invoice page");
        if ($this->exts->exists('a[href="index.cgi?tree=domain.dashboard#!"]')) {
            $this->exts->moveToElementAndClick('a[href="index.cgi?tree=domain.dashboard#!"]');
            sleep(15);
        }
        if ($this->exts->exists('div.guide-container')) {
            $this->exts->moveToElementAndClick('.bell-ghost');
            sleep(1);
        }

        $this->exts->moveToElementAndClick('button#tracking_nav_billing, #panel-navigation-app > div > div >.sidebar .sidebar-inner a#tracking_nav_billing');
        sleep(3);
        $this->exts->moveToElementAndClick('#tracking_nav_billing_invoice');
        sleep(20);

        if ($this->exts->querySelector('.fancyform[method="get"] select#cur_invoice') == null) {
            $this->exts->openUrl('https://panel.dreamhost.com/index.cgi?tree=billing.invoice');
            sleep(20);
        }

        $this->downloadInvoice();

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    private function changeSelectbox($select_box = '')
    {
        $this->waitFor($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $this->exts->click_by_xdotool($select_box);
            sleep(2);

            $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
            // Simulate pressing the down arrow key
            $this->exts->type_key_by_xdotool('Down');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    private function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->querySelector('.fancyform[method="get"] select#cur_invoice') != null) {
                if ($this->restrictPages == 0) {
                    $total_receipts = count($this->exts->querySelectorAll('.fancyform[method="get"] select#cur_invoice > option'));
                } else {
                    $total_receipts = 3;
                }
                $this->totalFiles = $total_receipts;

                for ($i = 1; $i <= $total_receipts; $i++) {
                    $invoiceIndex = $i;
                    $invoiceMonthIndex = 1;
                    $invoice_date = "";
                    if ($this->exts->querySelector('.fancyform[method="get"] select#cur_invoice > option:nth-child(' . $invoiceIndex . ')') != null) {
                        $invoice_date = trim($this->exts->querySelector('.fancyform[method="get"] select#cur_invoice > option:nth-child(' . $invoiceIndex . ')')->getText());
                    }

                    $this->exts->moveToElementAndClick('.fancyform[method="get"] select#cur_month');
                    sleep(1);
                    $this->exts->moveToElementAndClick('.fancyform[method="get"] select#cur_month > option:nth-child(' . $invoiceMonthIndex . ')');
                    sleep(5);

                    $invoiceDownloadSelector = '.fancyform[method="get"] input[type="submit"]';
                    $downloaded_file = $this->exts->click_and_download($invoiceDownloadSelector, 'pdf', "");
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice("", $invoice_date, "", $downloaded_file);
                        sleep(5);
                    }

                    $this->exts->log('>>>>>>>>> invoice index' . $invoiceIndex);
                    $this->changeSelectbox('.fancyform[method="get"] select#cur_invoice');
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
