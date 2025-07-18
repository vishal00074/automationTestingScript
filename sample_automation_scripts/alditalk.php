<?php // replace waitTillPresent to waitFor and exists to isEixst

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

    // Server-Portal-ID: 9011 - Last modified: 06.06.2025 12:13:29 UTC - User: 1

    public $baseUrl = 'https://www.alditalk-kundenbetreuung.de';
    public $loginUrl = 'https://www.alditalk-kundenbetreuung.de';
    public $invoicePageUrl = 'https://www.alditalk-kundenportal.de/portal/auth/postfach';

    public $username_selector = '[id="idToken3_od"]';
    public $password_selector = 'one-input[type="password"]';
    public $remember_me_selector = '[id="remember_od"]';
    public $submit_login_selector = '[id="IDToken5_4_od_2"]';

    public $check_login_failed_selector = 'div#one-messages one-stack, [id="errorMsg"]';
    public $check_login_success_selector = 'div#accountInfo';

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
        sleep(10);
        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->fillForm(0);
            sleep(10);
        }
        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            if ($this->isExists('div#uc-banner-modal button#uc-btn-accept-banner')) {
                $this->exts->click_by_xdotool('div#uc-banner-modal button#uc-btn-accept-banner');
            }
            $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
            $this->exts->moveToElementAndClick('[panel="invoices"]');

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($this->username);

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($this->password);
                // $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->isExists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(2); // Portal itself has one second delay after showing toast
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
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
            sleep(5);
            if ($this->isExists($this->check_login_success_selector) || count($this->exts->queryXpathAll("//one-button[@variant='outline' and @color='default' and @size='medium' and text()='Abmelden']")) != 0) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
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
        $this->waitFor('form:not([id="invoice_toggle"]) div[class="panel"] div.invoice-teaser', 10);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->isExists('one-tab-panel[name="invoices"] one-panel one-button')) {
            $this->exts->click_by_xdotool('one-tab-panel[name="invoices"] one-panel one-button');
            $attempt++;
            sleep(5);
        }
        $invoices = [];
        if ($this->isExists('form:not([id="invoice_toggle"]) div[class="panel"] div.invoice-teaser')) {
            $rows = $this->exts->querySelectorAll('form:not([id="invoice_toggle"]) div[class="panel"] div.invoice-teaser');
            foreach ($rows as $row) {
                if ($this->exts->querySelector('footer a.invoice-teaser__btn-download', $row) != null) {
                    $invoiceUrl = $this->exts->querySelector('footer a.invoice-teaser__btn-download', $row)->getAttribute('href');
                    preg_match('/documentId=([A-Za-z0-9]+)/', $invoiceUrl, $matches);
                    $invoiceName = isset($matches[1]) ? $matches[1] : $this->exts->extract('span.invoice-teaser__headline', $row);
                    $invoiceDate = $this->exts->extract('span.invoice-teaser__headline', $row);
                    $invoiceAmount = $this->exts->extract('span.invoice-teaser__subline', $row);
                    $downloadBtn = $this->exts->querySelector('footer a.invoice-teaser__btn-download', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'downloadBtn' => $downloadBtn
                    ));
                    $this->isNoInvoice = false;
                }
            }
        } else {
            $rows = $this->exts->querySelectorAll('[variant="base"] > one-description-list > one-description-list-item');
            foreach ($rows as $row) {
                // $tags = $this->exts->getElements('one-text', $row);
                if ($this->exts->querySelector('one-icon-button[slot="suffix"]', $row) != null) {
                    $invoiceName = '';
                    $invoiceDate = $this->exts->extract('one-text', $row);
                    $invoiceAmount = $this->exts->extract('one-price', $row);
                    $downloadBtn = $this->exts->querySelector('one-description-list-item one-icon-button[slot="suffix"]', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => '',
                        'downloadBtn' => $downloadBtn
                    ));
                    $this->isNoInvoice = false;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd-M-y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
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
