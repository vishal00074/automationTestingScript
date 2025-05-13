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

    // Server-Portal-ID: 77911 - Last modified: 06.03.2025 13:49:09 UTC - User: 1

    public $baseUrl = 'https://www.fonic.de/';
    public $loginUrl = 'https://mein.fonic.de/login';
    public $invoicePageUrl = 'https://www.fonic.de/selfcare/gespraechsuebersicht';

    public $username_selector = 'input[name="msisdn"], input[name="telnumber"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[name="loginButton"]';

    public $check_login_failed_selector = 'div.alert.alert--error';
    public $check_login_success_selector = 'use[href*="logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        //$this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture_by_chromedevtool('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log('NOT logged via cookie');
            //$this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button#uc-btn-accept-banner')) {
                $this->exts->log('accept cookies -------');
                $this->exts->click_by_xdotool('button#uc-btn-accept-banner');
                sleep(5);
            }
            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->password_selector, 30);
            if ($this->exts->exists($this->password_selector) && !$this->exts->exists($this->check_login_failed_selector)) {

                $this->exts->openUrl($this->loginUrl);
                sleep(5);
                $this->checkFillLogin();
                sleep(30);
            }
            if ($this->exts->exists('div.change_password')) {
                $this->exts->account_not_ready();
            }
        }

        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->waitTillPresent('a[href="/selfcare/gespraechsuebersicht"]');
            $this->exts->moveToElementAndClick('a[href="/selfcare/gespraechsuebersicht"]');

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Kennwort ist nicht korrekt.') !== false) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->exists('div.change_password')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->exists('button#uc-btn-accept-banner')) {
            $this->exts->log('accept cookies -------');
            $this->exts->click_by_xdotool('button#uc-btn-accept-banner');
            sleep(5);
        }
        if ($this->exts->exists($this->password_selector) != null) {
            // $this->exts->capture_by_chromedevtool("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
            $this->exts->capture_by_chromedevtool("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(5);

            $this->exts->waitTillPresent('dialog[data-cy="migration-notice"] button[data-cy="modal__button__cta"]');

            if ($this->exts->exists('dialog[data-cy="migration-notice"] button[data-cy="modal__button__cta"]')) {
                $this->exts->moveToElementAndClick('dialog[data-cy="migration-notice"] button[data-cy="modal__button__cta"]');
                sleep(5);
            }

            $this->exts->waitTillPresent('div#usercentrics-root', 10);
            if ($this->exts->exists('div#usercentrics-root')) {
                $this->exts->log('accept cookies -------');
                $this->switchToFrame('div#usercentrics-root');
                sleep(2);
                $this->exts->moveToElementAndClick('button[data-testid="uc-accept-all-button"]"]');
                sleep(5);
                $this->exts->refresh();
                sleep(10);
            }

        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.selfcare__usage-invoices', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->querySelectorAll('div.selfcare__usage-invoices'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('div.selfcare__usage-invoices')[$i];
            $tags = $this->exts->querySelectorAll('div', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('div.invoice-icon', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('div.invoice-icon', $row);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim(array_pop(explode('am', $tags[2]->getAttribute('innerText'))));
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            } else if (count($tags) >= 3 && $this->exts->querySelector('div.invoice__number', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('div.invoice__number', $row);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim(array_pop(explode('am', $tags[2]->getAttribute('innerText'))));
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
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
