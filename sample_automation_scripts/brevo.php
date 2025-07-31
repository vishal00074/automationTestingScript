<?php // handle empty invoice name updated loginfailedConfirmed conditon to check message first if incorrect then triggers updated login failed selector
 // check click_by_xdotool to moveToElementAndClick element data mismatch for current connection
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

    // Server-Portal-ID: 2201657 - Last modified: 31.07.2025 02:32:14 UTC - User: 1

    public $baseUrl = "https://account-app.brevo.com/account/session/organization";
    public $loginUrl = "https://login.brevo.com/";
    public $invoicePageUrl = 'https://app.brevo.com/billing/account/plans/billing-history';
    public $accountListUrl = 'https://account-app.brevo.com/account/session/organization?target=https://app.brevo.com/billing/account/plans/billing-history';
    public $username_selector = 'input[id="email"]';
    public $password_selector = 'input[id="password"]';
    public $submit_button_selector = 'button[data-testid="submit-button"]';
    public $check_login_failed_selector = 'div[role="alert"] > div:nth-child(2) div';
    public $check_login_success_selector = 'nav > ul > li > button';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            $this->checkFillTwoFactor();
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->processAccounts();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->waitTillPresent($this->check_login_failed_selector, 20);
            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            
            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

            if (stripos($error_text, strtolower('password')) !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->log("Failed due to unknown reasons");
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int) $this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(3);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5); // Portal itself has one second delay after showing toast
                $this->exts->waitTillPresent('iframe[src*="/recaptcha/api2/anchor?"]', 20);
                if ($this->exts->querySelector('iframe[src*="/recaptcha/api2/anchor?"]') != null) {
                    $this->checkFillRecaptcha();
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            sleep(10);
            $this->exts->waitTillPresent($this->check_login_success_selector, 40);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
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

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[id="login_verification_code"], input[id="vcode"]';
        $two_factor_message_selector = 'div.code_message';
        $two_factor_submit_selector = 'button[id="login_submit"], button[data-testid*="testId"]';

        $this->exts->waitTillPresent($two_factor_selector);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
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
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
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

    private function processAccounts()
    {
        $this->exts->waitTillPresent('ul.switch-organization > li', 20);
        $rows = $this->exts->querySelectorAll('ul.switch-organization > li');
        if ($this->exts->exists('ul.switch-organization > li')) {
            foreach ($rows as $row) {
                $this->exts->waitTillPresent('ul.switch-organization > li', 20);
                $accountLink = $row->querySelector('a');
                $this->exts->click_element($accountLink);
                sleep(10);
                $this->processAllInvoices();
                $this->exts->openUrl($this->accountListUrl);
            }
        } else {
            $this->processAllInvoices();
        }
    }

    private function processAllInvoices()
    {
        $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->waitTillPresent('table.sib-data-table--Y4hl5 > tbody > tr', 20);
        do {
            $moreButton = $this->exts->querySelector('div.table_bottom_action--CTdCE > button');

            if ($moreButton != null) {
                $this->exts->execute_javascript("arguments[0].click();", [$moreButton]);
                $this->exts->waitTillPresent('div.table_bottom_action--CTdCE > button', 10);
            }
        } while ($moreButton != null);

        $this->processInvoices();
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('table.sib-data-table--Y4hl5 > tbody > tr', 20);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('table.sib-data-table--Y4hl5 > tbody > tr');
        foreach ($rows as $row) {

            $invoiceName = $row->querySelector('td:nth-child(1) > div');
            if ($invoiceName != null) {
                $invoiceName = $invoiceName->getText();
            }

            $invoiceDate = $row->querySelector('td:nth-child(2) > div');
            if ($invoiceDate != null) {
                $invoiceDate = $invoiceDate->getText();
            }

            $amount = $row->querySelector('td:nth-child(4) > div');
            if ($amount != null) {
                $amount = $amount->getText();
            }

            $downloadLink = $row->querySelector('td:nth-child(7) > div > div > button');
            if ($downloadLink == null) {
                $downloadLink = $row->querySelector('td:nth-child(8) > div > div > button');
            }
            $this->exts->execute_javascript("arguments[0].click();", [$downloadLink]);
            $this->exts->waitTillPresent('ul[role="menu"] > li > ul > li > button', 10);
            $downloadBtn = $this->exts->querySelector('ul[role="menu"] > li > ul > li > button');
            if ($downloadBtn != null) {
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceDate: ' . $amount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $amount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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
