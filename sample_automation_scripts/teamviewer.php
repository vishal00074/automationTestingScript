<?php // migrated updated login code.

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

    // Server-Portal-ID: 653 - Last modified: 09.04.2024 13:53:43 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://service.teamviewer.com/';
    public $loginUrl = 'https://service.teamviewer.com/';
    public $invoicePageUrl = 'https://service.teamviewer.com/de-de/invoices/';

    public $username_selector = 'input#email-field,input#current-useremailorname';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = 'input#remember';
    public $submit_login_selector = 'button[type="submit"],button[type="submit"][class*="OAuthSubmitButton"]';

    public $check_login_failed_selector = ' div[class*="headerErrorInfo"] div[class*="errorText"]';
    public $check_login_success_selector = 'li.loginState a[href*="/logout/"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(10);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button.accept-cookies-button')) {
                $this->exts->moveToElementAndClick('button.accept-cookies-button');
                sleep(2);
            }
            if ($this->exts->exists('button[id="onetrust-accept-btn-handler"]')) {
                $this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"]');
                sleep(2);
            }
            sleep(10);
            $this->checkFillLogin();
            sleep(10);
            $this->checkFillLogin();
            sleep(10);
            $this->checkFillTwoFactor();
            sleep(10);
            if (strpos(strtolower($this->exts->extract('div[class*="LoginApp-styles__loader"] div[class*="loadingText"]')), 'logging in') !== false) {
                sleep(30);
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (
                strpos(strtolower($this->exts->extract('[class*="LoginApp-styles__errorText"]')), 'benutzername und das kennwort') !== false ||
                strpos(strtolower($this->exts->extract('[class*="LoginApp-styles__errorText"]')), 'username and password you entered do not match') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null || $this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(7);
            }


            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            sleep(2);

            $this->checkFillRecaptcha();

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(2);
            if ($this->exts->exists('div[class="Toastify__toast-body"] div>span')) {

                $err_msg1 = $this->exts->extract('div[class="Toastify__toast-body"] div>span');
                $lowercase_err_msg = strtolower($err_msg1);
                $substrings = array('incorrect email or password. please try again.', 'incorrect', 'email', 'password');
                foreach ($substrings as $substring) {
                    if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                        $this->exts->log($err_msg1);
                        $this->exts->loginFailure(1);
                        break;
                    }
                }
            }
            sleep(2);
            $this->checkFillRecaptcha();

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }

            sleep(2);

            if ($this->exts->exists('div[class="Toastify__toast-body"] div>span')) {

                $err_msg1 = $this->exts->extract('div[class="Toastify__toast-body"] div>span');
                $lowercase_err_msg = strtolower($err_msg1);
                $substrings = array('incorrect email or password. please try again.', 'incorrect', 'email', 'password');
                foreach ($substrings as $substring) {
                    if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                        $this->exts->log($err_msg1);
                        $this->exts->loginFailure(1);
                        break;
                    }
                }
            }

            //if($this->exts->waitTillPresent('div[class="Toastify__toast-body"] div>span',3)) {

            $this->exts->capture("2-login-after-submit");
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
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
                    sleep(5);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-2fa-checking");

        if ($this->exts->getElement('input#FormInput_TFA__TwoFactorSecurityCode') != null && $this->exts->urlContains('/tfa')) {
            $two_factor_selector = 'form input#FormInput_TFA__TwoFactorSecurityCode';
            $two_factor_message_selector = 'span[class*="LoginApp-styles__subText"]';
            $two_factor_submit_selector = 'form div[class*="Button-styles__primary"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

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
        } else if (
            strpos(strtolower($this->exts->extract('[class*="LoginApp-styles__errorText"]')), 'wir haben ihnen') !== false ||
            strpos(strtolower($this->exts->extract('[class*="LoginApp-styles__errorText"]')), 'confirm this browser is a trusted device. we have sent you') !== false
        ) {
            $this->exts->capture("2-check-fill-2fa");
            $this->exts->log("Sending 2FA request to ask user click on confirm link");
            $message = trim($this->exts->extract('[class*="LoginApp-styles__errorText"]'));
            $this->exts->two_factor_notif_msg_en = $message . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = $message . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            sleep(5);
            $this->exts->capture("2-after-2fa");
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("User clicked on confirm link");
                $this->checkFillLogin();
                sleep(5);
            }
        }
    }
    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('div.filtered-listing div.singlePod');
        foreach ($rows as $row) {
            if ($this->exts->getElement('div.podFooter a[href*="/invoices"]', $row) != null && $this->exts->getElement('div.podContent span.statusGreen', $row) != null) {
                $invoiceUrl = $this->exts->getElement('div.podFooter a[href*="/invoices"]', $row)->getAttribute("href");
                $invoiceName = $this->exts->extract('div.podHeader > span', $row);
                $invoiceName = explode('/', $invoiceName)[0];
                $invoiceDate = $this->exts->extract('div.podContent li:nth-child(1) > span:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('div.podContent li.total', $row);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', explode('/', $invoiceAmount)[0])) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
