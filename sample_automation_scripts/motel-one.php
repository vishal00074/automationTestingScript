<?php // handle empty invoice name remove unncessary commented code

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


    // Server-Portal-ID: 26315 - Last modified: 10.07.2025 15:04:11 UTC - User: 1

    public $baseUrl = 'https://www.motel-one.com/';
    public $loginUrl = 'https://www.motel-one.com/';
    public $invoicePageUrl = 'https://booking.motel-one.com/de/profile/reservations/?state=EXPIRED';
    public $username_selector = 'form[id*="loggedOut"] input[name="email"]';
    public $password_selector = 'form[id*="loggedOut"] input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[id*="loggedOut"] button[type="submit"]';
    public $check_login_failed_selector = 'p.message-feedback__msg, #notification-viewport li, .formkit-message';
    public $check_login_success_selector = 'a[href*="/reservations"]';
    public $isNoInvoice = true;
    public $errorMessage = '';

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        $this->acceptCookies();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->acceptCookies();
            $this->exts->click_element('button[aria-label="Kundenkonto verwalten"]');
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos($this->errorMessage, 'not correct') !== false || stripos($this->errorMessage, 'nicht korrekt') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if (stripos($this->errorMessage, 'E-Mail Adresse ist ung') !== false || stripos($this->errorMessage, 'enter a valid email address') !== false) {
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
        sleep(10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_element($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(2);
                if ($this->exts->extract($this->check_login_failed_selector) != null) {
                    $this->errorMessage = $this->exts->extract($this->check_login_failed_selector);
                    $this->exts->log("Wrong credential message: " . $this->errorMessage);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }
            sleep(10);
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function acceptCookies()
    {
        sleep(10);
        if ($this->exts->getElement('#usercentrics-cmp-ui')) {
            $this->exts->execute_javascript('
		        var shadow = document.querySelector("#usercentrics-cmp-ui");
		        if(shadow){
		            shadow.shadowRoot.querySelector("button[id*=\'accept\']").click();
		        }
		    ');
            sleep(3);
        }

        if ($this->exts->getElement('button[aria-label*="Schlie"]:first-child') != null) {
            $this->exts->click_element('button[aria-label*="Schlie"]:first-child');
            sleep(2);
        }
    }

    public function checkLogin()
    {
        sleep(5);
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement("button[aria-label*='verwalten']") != null && $this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->moveToElementAndClick("button[aria-label*='verwalten']");
                sleep(5);
            }

            if ($this->exts->getElement($this->check_login_success_selector) != null) {
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
        sleep(20);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('ul#reservation-list li');
        for ($i = 0; $i <= count($rows); $i++) {
            $row = $this->exts->getElements('ul#reservation-list li form[action*="/detail"]')[$i];
            if ($row != null) {
                $invoiceUrl = $row->getAttribute("action");
                $invoiceName = $this->exts->getElement('#form_reservationNumber', $row)->getAttribute('value');
                $invoiceDate = '';
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(20);
            $this->exts->moveToElementAndClick('button.button-icon--download-invoice');
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->isNoInvoice = false;
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
