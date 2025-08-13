<?php //  replace waitTillPresent to waitFor added code to trigger no_permission in case user dont have permission


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


    // Server-Portal-ID: 24460 - Last modified: 23.07.2025 06:37:34 UTC - User: 1

    public $baseUrl = 'https://my.kaspersky.com/';
    public $loginUrl = 'https://my.kaspersky.com/';
    public $invoicePageUrl = 'https://my.kaspersky.com/MyDownloads#(modal:myaccount/orderHistory)';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = 'input[data-at-selector="checkboxRememberMe"]';
    public $submit_login_selector = 'button[type="submit"], button[data-at-selector="welcomeSignInBtn"]';
    public $check_login_failed_selector = 'div.is-critical';
    public $check_login_success_selector = 'li a[href*="Password"]';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);

        $this->waitFor('button[id*="AllowAll"]', 30);
        if ($this->exts->exists('button[id*="AllowAll"]')) {
            $this->exts->click_element('button[id*="AllowAll"]');
        }
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);

        $this->waitFor('button[id*="AllowAll"]', 30);
        if ($this->exts->exists('button[id*="AllowAll"]')) {
            $this->exts->click_element('button[id*="AllowAll"]');
        }
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->waitFor('button[id*="AllowAll"]', 20);
            if ($this->exts->exists('button[id*="AllowAll"]')) {
                $this->exts->click_element('button[id*="AllowAll"]');
            }

            sleep(35);
            $this->checkFillLogin();
            sleep(20);
            if ($this->exts->exists('button#reload-button')) {
                //redirected you too many times.
                $this->exts->moveToElementAndClick('button#reload-button');
                sleep(15);
                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->checkFillLogin();
                    sleep(20);
                }
            }
            $this->checkFillTwoFactor();
            sleep(15);
        }
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);

            sleep(12);

            $error_text = strtolower($this->exts->extract('h2.empty-downloads__header'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if ($this->exts->getElementByText('h2.empty-downloads__header', ["Um Apps herunterzuladen, verknüpfen Sie das Abonnement mit Ihrem Konto"], null, false)) {
                $this->exts->no_permission();
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->log('No Invoice');
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function isValidEmail($username)
    {
        // Regular expression for email validation
        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';


        if (preg_match($emailPattern, $username)) {
            return 'email';
        }
        return false;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        sleep(15);
        if ($this->exts->exists('div.signin-invite button[class*="signin"]')) {
            $this->exts->log("Open Login form");
            $this->exts->moveToElementAndClick('div.signin-invite button[class*="signin"]');
            sleep(15);
        }
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            if (!$this->isValidEmail($this->username)) {
                $this->exts->loginFailure(1);
            }
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);

            sleep(30);
            //Accept Cookies
            $this->waitFor('button[id*="AllowAll"]', 20);
            if ($this->exts->exists('button[id*="AllowAll"]')) {
                $this->exts->click_element('button[id*="AllowAll"]');
            }

            sleep(5);
            if ($this->exts->exists('label[data-at-selector="allowedAgreements"]')) {
                $this->exts->log("*************************Accept to use*************************");
                $this->exts->click_element('label[data-at-selector="allowedAgreements"]');
                sleep(5);
                $this->exts->moveToElementAndClick('button[data-at-selector="agreementProceedBtn"]');
                sleep(15);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    // 2 FA
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[name*="OtpCode"]';
        $two_factor_message_selector = 'wp-modal[analyticsmodalname*="OtpDialog"] div[class*="descri"]';
        $two_factor_submit_selector = '';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                // $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

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

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->querySelectorAll('div[data-at-selector*="ordersList"]'));

        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('div[data-at-selector*="ordersList"]')[$i];
            if ($this->exts->querySelector('a[href*="invoiceUrl"]',  $row) != null) {
                $download_button = $this->exts->querySelector('a[href*="invoiceUrl"]', $row);
                $invoiceUrl = $this->exts->querySelector('a[href*="invoiceUrl"]',  $row)->getAttribute("href");
                $invoiceName = trim($this->exts->querySelector('span[class*="OrderId"]',  $row)->getAttribute("innerText"));
                $invoiceDate = trim($this->exts->querySelector('span[class*="at-orderPurchaseDate"]',  $row)->getAttribute("innerText"));
                $invoiceAmount = "";
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'M d, Y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd.M.Y H:i', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parsed_date);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(30);
                $downloaded_file = $this->exts->download_capture($invoiceUrl, $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
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
