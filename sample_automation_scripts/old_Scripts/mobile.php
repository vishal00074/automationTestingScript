<?php //replace waitTillPresent to waitFOr
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


    // Server-Portal-ID: 2846856 - Last modified: 11.08.2025 05:34:34 UTC - User: 1

    public $baseUrl = 'https://www.mobile.de';
    public $loginUrl = 'https://www.mobile.de/api/auth/login';
    public $invoicePageUrl = 'https://www.mobile.de/rechnung/herunterladen/?utmSource=invoice-email';

    public $username_selector = 'input#login-username';
    public $password_selector = 'input#login-password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#login-submit';

    public $check_login_failed_selector = 'div#login-error';
    public $check_login_success_selector = 'button[data-testid="my-mobile-logout"]';

    public $isNoInvoice = true;

    /**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->disableExtension();

        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        $acceptAllBtn = 'button.mde-consent-accept-btn';

        $this->waitFor($acceptAllBtn, 7);
        if ($this->exts->exists($acceptAllBtn)) {
            $this->exts->click_element($acceptAllBtn);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->waitFor($acceptAllBtn, 7);
            if ($this->exts->exists($acceptAllBtn)) {
                $this->exts->click_element($acceptAllBtn);
            }
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->capture("1-login-page-filled");
                sleep(5);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
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
            $this->waitFor($this->check_login_success_selector, 15);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('div[class*="invoiceItem--"]', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        if ($this->exts->exists('div[class*="showAllLink"]')) {
            $this->exts->click_element('div[class*="showAllLink"]');
            sleep(5);
        }

        $rows = $this->exts->querySelectorAll('div[class*="invoiceItem--"]');
        $this->exts->log('Total no of Rows : ' . count($rows));
        foreach ($rows as $row) {

            if ($this->exts->querySelector('div[class*="downloadPdfArea"] button', $row) != null) {

                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div[class*="invoiceItemRow"]:nth-child(3) div[class*="invoiceItemValue"]', $row);
                $invoiceAmount = $this->exts->extract('div[class*="invoiceItemRow"]:nth-child(4) div[class*="invoiceItemValue"]', $row);
                $invoiceDate = $this->exts->extract('div[class*="invoiceItemRow"]:nth-child(1) div[class*="invoiceItemValue"]', $row);

                $downloadBtn = $this->exts->querySelector('div[class*="downloadPdfArea"] button', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                sleep(3);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $this->exts->log(' ');
                $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                $this->exts->log(' ');
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }

    private function disableExtension()
    {
        $this->exts->log('Disabling Accept all cookies extension!');
        $this->exts->openUrl('chrome://extensions/?id=ncmbalenomcmiejdkofaklpmnnmgmpdk');

        $this->waitFor('extensions-manager', 7);
        if ($this->exts->exists('extensions-manager')) {
            $this->exts->execute_javascript("
			var button = document
						.querySelector('extensions-manager')
						?.shadowRoot?.querySelector('extensions-detail-view')
						?.shadowRoot?.querySelector('cr-toggle') || null;
							
			if (button) {
				button.click();
			}
		");
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
