<?php // updated 2fa input message and submit button selector added restrictPages page condition to download limited invoices

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

    // Server-Portal-ID: 555213 - Last modified: 04.08.2025 13:22:16 UTC - User: 1

    public $baseUrl = 'https://app.klicktipp.com/';
    public $loginUrl = 'https://app.klicktipp.com/user';
    public $invoicePageUrl = 'https://app.klicktipp.com/user/me/digistore-invoice';

    public $username_selector = 'form#user-login input.edit-name, input[id="username"]';
    public $password_selector = 'form#user-login input.edit-pass, input[id="password"]';
    public $submit_login_selector = 'form#user-login input.btn-submit, input[id="kc-login"]';

    public $check_login_failed_selector = 'div.modal-messages div.alert-danger';
    public $check_login_success_selector = "a[href*='/logout'],kt-customer-account";

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        if ($this->exts->exists('div[data-e2e-id="main-6"]')) {
            $this->exts->moveToElementAndClick('div[data-e2e-id="main-6"]');
            sleep(5);
        } else if ($this->exts->exists('div[data-e2e-id="main-5"]')) {
            $this->exts->moveToElementAndClick('div[data-e2e-id="main-5"]');
            sleep(5);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->waitForSelectors($this->check_login_success_selector, 15, 2);

        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            $this->checkFillTwoFactor();
            if ($this->exts->exists('div[data-e2e-id="main-6"]')) {
                $this->exts->moveToElementAndClick('div[data-e2e-id="main-6"]');
                sleep(5);
            } else if ($this->exts->exists('div[data-e2e-id="main-5"]')) {
                $this->exts->moveToElementAndClick('div[data-e2e-id="main-5"]');
                sleep(5);
            }

            // some users can not load dashboard page
            $this->waitForSelectors($this->check_login_success_selector, 15, 2);
            if ($this->exts->getElement($this->check_login_success_selector) == null && !$this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->capture("user-can-not-load-dashboard-page");
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(20);
            }
        }

        $this->waitForSelectors($this->check_login_success_selector, 15, 2);

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);

            $this->exts->waitTillPresent('#edit-invoice a[href*="digistore24.com/receipt/"]');

            if ($this->exts->getElement('#edit-invoice a[href*="digistore24.com/receipt/"], a[href*="digistore24.com/receipt/"]') != null) {
                $this->exts->moveToElementAndClick('#edit-invoice a[href*="digistore24.com/receipt/"], a[href*="digistore24.com/receipt/"]');
                sleep(10);

                $receipt_tab = $this->exts->findTabMatchedUrl(['digistore24.com/receipt/']);
                if ($receipt_tab != null) {
                    $this->exts->switchToTab($receipt_tab);
                }
            } else {
                $invoice_page_bt = $this->exts->execute_javascript('document.querySelector("kt-shadow-dom").shadowRoot.querySelector(\'a[href*="digistore24.com/receipt/"]\').click();');

                $receipt_tab = $this->exts->findTabMatchedUrl(['digistore24.com/receipt/']);
                if ($receipt_tab != null) {
                    $this->exts->switchToTab($receipt_tab);
                }
            }

            // Click View invoices button
            $this->exts->moveToElementAndClick('div.tglr_container button.invoice_download_headline');
            $this->processInvoices();

            $this->exts->switchToInitTab();
            $this->exts->closeAllTabsButThis();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort wurden nicht akzeptiert') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'wurde nicht aktiviert oder ist gesperrt') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitForSelectors($this->password_selector, 10, 2);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = "form#user-login input[name='LoginCode'], input#otp";
        $two_factor_message_selector = '.modal-login .alert, span#input-error-otp-code';
        $two_factor_submit_selector = 'form#user-login #edit-submit[name="op"], input#kc-login';

        $this->waitForSelectors($two_factor_selector, 10, 2);
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
                $this->exts->type_key_by_xdotool("Return");
                sleep(2);
                $this->exts->click_element($two_factor_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
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

    public $totalInvoices = 0;

    private function processInvoices()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $this->waitForSelectors("div#maincontent ul li", 15, 2);
        $rows = $this->exts->querySelectorAll('div#maincontent ul li');
        $this->exts->log('rows: ' . count($rows));
        foreach ($rows as $row) {
            if ($this->exts->getElement('a[href*="/invoice/"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice/"]', $row)->getAttribute("href");
                $invoiceName = explode(
                    '/',
                    array_pop(explode('invoice/', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = '';

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

            if ($this->totalInvoices >= 50 && $restrictPages != 0) {
                return;
            }

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");


$portal = new PortalScriptCDP("optimized-chrome-v2", 'JLCPCB', '2673809', 'YWNjb3VudHNAaWZwLXNvZnR3YXJlLmRl', 'cGVmem81LXhpYndpYy16VWR2YW0=');
$portal->run();
