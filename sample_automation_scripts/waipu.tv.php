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

    // Server-Portal-ID: 23461 - Last modified: 30.01.2025 14:04:57 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://customer-self-care.waipu.tv';
    public $loginUrl = 'https://auth.waipu.tv/ui/login';
    public $invoicePageUrl = 'https://customer-self-care.waipu.tv/ui/my_invoices';

    public $username_selector = 'form#loginForm input[name="emailAddress"]';
    public $password_selector = 'form#loginForm input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_btn = 'form#loginForm button.button';

    public $checkLoginFailedSelector = 'form#loginForm .alert--error';
    public $checkLoggedinSelector = 'a[href*="/ui/logout"], .header--logged-in, .welcome__text + button';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        // after load cookies and open base url, check if user logged in
        // Wait for selector that make sure user logged in
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');

        $this->exts->openUrl($this->loginUrl);
        $this->exts->waitTillPresent('iframe[id*="sp_message_iframe"]', 20);
        $this->switchToFrame('iframe[id*="sp_message_iframe"]');
        sleep(5);
        if ($this->exts->exists('button[title="Zustimmen und weiter"]')) {
            $this->exts->click_by_xdotool('button[title="Zustimmen und weiter"]');
        }
        $this->exts->switchToDefault();
        sleep(10);
        $this->checkFillLogin();
        sleep(5);
        if ($this->exts->allExists([$this->password_selector])) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->exts->waitTillPresent('iframe[id*="sp_message_iframe"]', 20);
            $this->switchToFrame('iframe[id*="sp_message_iframe"]');
            sleep(5);
            if ($this->exts->exists('button[title="Zustimmen und weiter"]')) {
                $this->exts->click_by_xdotool('button[title="Zustimmen und weiter"]');
            }
            $this->exts->switchToDefault();
            sleep(2);
        }
        $this->checkFillLogin();
        sleep(10);
        $this->exts->capture("2-post-login");
        if ($this->exts->allExists([$this->password_selector])) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->checkFillLogin();
            sleep(10);
            $this->exts->capture("2-post-login");
        }

        sleep(15);

        if ($this->exts->exists($this->checkLoggedinSelector) || $this->exts->urlContains('waipu.tv/ARD')) {
            $this->exts->log('User logged in.');
            $this->exts->waitTillPresent('iframe[id*="sp_message_iframe"]', 20);
            $this->switchToFrame('iframe[id*="sp_message_iframe"]');
            sleep(5);
            if ($this->exts->exists('button[title="Zustimmen und weiter"]')) {
                $this->exts->click_by_xdotool('button[title="Zustimmen und weiter"]');
            }
            $this->exts->switchToDefault();
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log('Timeout waitForLogin: ' . $this->exts->getUrl());
            $this->exts->capture("LoginFailed");

            if (strpos(strtolower($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText')), 'die zugangsdaten sind ung') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
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
    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->username_selector, 5);
        if ($this->exts->querySelector($this->username_selector) != null) {
            // $this->capture_by_chromedevtool("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->checkFillRecaptcha();

            $this->exts->capture("1-filled-login");
            sleep(5);
            if ($this->exts->exists($this->submit_login_btn)) {
                $this->exts->click_element($this->submit_login_btn);
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[title="reCAPTCHA"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, true);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                $this->exts->capture('recaptcha-filled');
            } else {
                // try again if recaptcha expired
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }


    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.invoices ol.invoices__list li.invoices__row', 10);
        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.invoices ol.invoices__list li.invoices__row');
        foreach ($rows as $row) {

            $tags = $this->exts->querySelectorAll('span', $row);
            if (count($tags) < 3) {
                continue;
            }

            $as = $this->exts->querySelectorAll('a', $tags[2]);
            if (count($as) == 0) {
                continue;
            }

            $invoiceUrl = $as[0]->getAttribute("href");
            $urlParts = explode('/', $invoiceUrl);
            $fileName = array_pop($urlParts);
            $invoiceName = explode('.pdf', $fileName)[0];

            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $invoiceDate = trim(end(explode('vom', $invoiceDate)));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';

            $invoices[] = array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            );
        }

        // Download all invoices
        $this->exts->log('Invoices: ' . count($invoices));
        $count = 1;
        $totalFiles = count($invoices);

        foreach ($invoices as $invoice) {
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';

            $this->exts->log('Date before parse: ' . $invoice['invoiceDate']);
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Invoice name: ' . $invoice['invoiceName']);
            $this->exts->log('Invoice date: ' . $invoice['invoiceDate']);
            $this->exts->log('Invoice amount: ' . $invoice['invoiceAmount']);
            $this->exts->log('Invoice URL: ' . $invoice['invoiceUrl']);

            // Download invoice if it does not exist
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                    $count++;
                } else {
                    $this->exts->log('Timeout when downloading ' . $invoiceFileName);
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
