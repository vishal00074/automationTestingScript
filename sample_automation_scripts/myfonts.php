<?php // handle empty invoice name case and extract invoice name date and amount from ui and updated loginfailedconfirmed message

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

    // Server-Portal-ID: 6026 - Last modified: 01.07.2025 14:45:53 UTC - User: 1

    // Script here
    public $baseUrl = 'https://www.myfonts.com/account?view=order-history';
    public $invoiceUrl = 'https://www.myfonts.com/account?view=order-history';

    public $username_selector = 'form input[name="username"]';
    public $password_selector = 'input#password';
    public $submit_login_selector = 'form button[type="submit"][name="action"]';

    public $check_login_failed_selector = 'form span[data-error-code]';
    public $check_login_success_selector = 'div[data-testid="username"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);

        $this->waitFor($this->check_login_success_selector);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            // close promotion popup.
            if ($this->exts->exists('div[role="dialog"] img.privy-x')) {
                $this->exts->moveToElementAndClick('div[role="dialog"] img.privy-x');
                sleep(3);
            }

            // accept cookies consent
            if ($this->exts->exists('div[role="dialog"] img.privy-x')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(3);
            }
            $this->checkFillLogin();
            $this->waitFor($this->check_login_success_selector);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoiceUrl);
            $this->waitFor($this->username_selector);
            $this->checkFillLogin();
            $this->waitFor('a[data-testid="invoice-download"]');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

            if (stripos($error_text, strtolower('passwor')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(1);
            $this->waitFor($this->password_selector);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices($count = 0)
    {

        $this->exts->capture("4-invoices-page");
        $rows = $this->exts->getElements('div.order-line-item-container, div[data-testid="order-detail"]');
        foreach ($rows as $row) {
            $download_link = $this->exts->getElement('button[class*=download-receipt]', $row);
            if ($download_link != null) {
                $orderNumber = $this->exts->extract('a.order-number', $row, 'innerText');
                $invoiceName = trim(end(explode('#', $orderNumber)));
                $invoiceDate = trim($row->getAttribute('data-index'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('button[class*=download-receipt] span', $row))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $invoiceDate;
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        // $download_link->getLocationOnScreenOnceScrolledIntoView();
                        $download_link->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_link]);
                    }
                    sleep(3);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $this->isNoInvoice = false;
            } else {
                $this->exts->log(__FUNCTION__ . ' trigger click 2.');
                $download_link = $this->exts->getElement('a[data-testid="invoice-download"]', $row);

                $orderNumber = $this->exts->extract('div:nth-child(5)', $row, 'innerText');
                $invoiceName = trim(end(explode('#', $orderNumber)));
                $invoiceDate = $this->exts->extract('div:nth-child(1) span.td__content', $row, 'innerText');
                $invoiceAmount = $this->exts->extract('div:nth-child(6) span.td__content', $row);

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);


                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $parsedDate = $this->exts->parse_date($invoiceDate, 'Y-m-d h:i:s', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsedDate);


                try {
                    $this->exts->log(__FUNCTION__ . ' trigger click.');
                    $download_link->click();
                } catch (\Exception $exception) {
                    $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_link]);
                }

                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $downloaded_file);
                    }
                } else {
                    $this->exts->log('Timeout when download');
                }
                $this->isNoInvoice = false;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
