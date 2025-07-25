<?php // replace click_element and click by using js getting Fatal error in click element 
// replace waitTillPresent to waitFor and handle empty invoiceName case added code to call again twoFa in case code is incorrect
// and tirgger login failed confirmed in case code incorrect second time

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

    // Server-Portal-ID: 1333427 - Last modified: 25.07.2025 02:26:48 UTC - User: 1

    // start script

    public $baseUrl = 'https://web.descript.com/invoices';
    public $loginUrl = 'https://www.descript.com/';
    public $invoicePageUrl = 'https://web.descript.com/invoices';
    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[name="submit"]';
    public $check_login_failed_selector = 'div.auth0-global-message-error';
    public $check_login_success_selector = 'button[aria-label="Open Account Menu"]';
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
            sleep(3);
            $this->waitFor('a[data-click-event="www_sign_in_clicked"]', 5);
            $signInClicked = $this->exts->getElement('a[data-click-event="www_sign_in_clicked"]');
            if ($signInClicked != null) {
                $this->exts->execute_javascript("arguments[0].click();", [$signInClicked]);
            }
            sleep(15);
            for ($i = 0; $i < 3; $i++) {
                $this->exts->processCaptcha('.auth0-lock-captcha .auth0-lock-captcha-image', '.auth0-lock-input-captcha input');
            }
            $this->fillForm(0);
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(3);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {

            $twoFAError = strtolower($this->exts->extract('div[color="error"]'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $twoFAError);


            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if (stripos($twoFAError, strtolower('Invalid passcode, please try again')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(10);
                $this->exts->click_element('button[type="submit"]');
                sleep(10);
                $this->checkFillTwoFactor();

                sleep(5);
                $this->exts->extract('div[color="error"]');

                $twoFAError = strtolower($this->exts->extract('div[color="error"]'));

                $this->exts->log(__FUNCTION__ . '::Error text: ' . $twoFAError);
                if (stripos($twoFAError, strtolower('Invalid passcode, please try again')) !== false) {
                    $this->exts->moveToElementAndClick('div[color="secondary"] button:not(:disabled)');
                    sleep(7);
                    $this->checkFillTwoFactor();
                }

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
                sleep(5);
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (TypeError $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
            sleep(10);
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        }
        return $isLoggedIn;
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[autocomplete="one-time-code"]';
        $two_factor_message_selector = 'div[size="body"]';
        $two_factor_submit_selector = 'button[type="submit"]';
        $this->waitFor($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


                // $this->exts->click_by_xdotool($two_factor_submit_selector);
                // sleep(15);
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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('table tbody tr', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        for ($i = 1; $i < count($rows); $i++) {
            $this->waitFor('table tbody tr:nth-child(' . $i . ')', 7);
            $row = $this->exts->querySelector('table tbody tr:nth-child(' . $i . ')');
            if ($row != null) {
                $this->exts->click_element($row);
                $this->waitFor("a[href*='pay.stripe']", 10);
                if ($this->exts->exists("a[href*='pay.stripe']")) {
                    $invoiceUrl = $this->exts->querySelector("a[href*='pay.stripe']")->getAttribute('href');
                    array_push($invoices, array(
                        'invoiceUrl' => $invoiceUrl,
                    ));
                    $this->isNoInvoice = false;
                }
            }
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->moveToElementAndClick('button[aria-label="Go back to invoice list"]');
            sleep(2);
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            $this->waitFor("a[href*='pay.stripe']", 10);
            sleep(2);
            if ($this->exts->querySelector("a[href*='pay.stripe']") != null) {
                $invoiceName = '';
                preg_match('/Paid\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/', $this->exts->extract("//span[contains(text(), 'Paid')]"), $matches);
                $invoiceDate = $matches[1];
                $invoiceAmount = $this->exts->extract("//span[contains(text(), '$')][1]");
                $downloadBtn = $this->exts->querySelector("a[href*='pay.stripe']");

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $this->isNoInvoice = false;
            }
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
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
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
