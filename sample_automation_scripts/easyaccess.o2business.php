<?php // updated login username and password selector code and trigger loginFailedConfirmed in case incorrect credentails.

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

    // Server-Portal-ID: 1246704 - Last modified: 07.04.2025 14:03:36 UTC - User: 1
    public $baseUrl = 'https://easyaccess.o2business.de/';
    public $loginUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';
    public $invoicePageUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';

    public $username_selector = 'lightning-primitive-input-simple input[id="input-16"], lightning-input > lightning-primitive-input-simple[exportparts*="input-text"] >div >div >input[id=input-16], .eCareLoginBox  .slds-form-element__control input[type=text], .eCareLoginBox input[type="text"],lightning-input #input-16';
    public $password_selector = 'lightning-primitive-input-simple input[id="input-17"], lightning-input > lightning-primitive-input-simple[exportparts*="input-text"] >div >div >input[id=input-17],  .eCareLoginBox  .slds-form-element__control input[type=password], .eCareLoginBox input[type="password"], lightning-input #input-17';
    public $submit_login_selector = '.eCareLoginBox .buttonBoxEcare button';

    public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
    public $check_login_success_selector = '#userNavItemId li a#userInfoBtnId, div.cECareOnlineInvoice';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);


            $this->checkFillLoginUndetected();
            sleep(10);
        }

        $this->exts->waitTillPresent($this->check_login_success_selector, 20);

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");


            $this->exts->type_key_by_xdotool("ctrl+l");
            sleep(5);
            $this->exts->type_text_by_xdotool($this->invoicePageUrl);

            sleep(5);
            // Select 1 Year Back date
            $this->exts->click_by_xdotool('select[name="input1"]');
            sleep(2);
            // Array for German month names
            $monthIndex = date('n', strtotime('-1 year')) - 1;
            $oneYearBack = date('Y', strtotime('-1 year')) . ' - ' . $this->exts->month_names_de[$monthIndex];
            $this->exts->log($oneYearBack);
            for ($i = 0; $i < 12; $i++) {
                $this->exts->type_key_by_xdotool("Up");
                sleep(1);
            }
            $this->exts->type_key_by_xdotool("Return");
            $this->exts->click_by_xdotool('button[class="form-button"]:first-child');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->capture('3-Current-page');
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.")');

            $this->exts->log('isErrorMessage:' . $isErrorMessage);

            if (trim($this->exts->extract('span.loginErrorMessage[data-aura-rendered-by="795:0"]')) != null) {
                $this->exts->log("---INVALID CREDENTIALS---");
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } else if ($isErrorMessage) {
                $this->exts->log("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLoginUndetected()
    {
        $this->exts->log("Enter Username");
        $this->exts->execute_javascript("
        var input = document
                    .querySelector('div[data-aura-rendered-by] lightning-input')
                    ?.shadowRoot?.querySelector('lightning-primitive-input-simple')
                    ?.shadowRoot?.querySelector('input[type=\"text\"]') || null;
                    
        if (input) {
            input.value = '" . $this->username . "';
            input.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        }
    ");

        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->execute_javascript("
        var input = document
                    .querySelector('div[data-aura-rendered-by]:nth-child(2) lightning-input')
                    ?.shadowRoot?.querySelector('lightning-primitive-input-simple')
                    ?.shadowRoot?.querySelector('input[type=\"password\"]') || null;
                    
        if (input) {
            input.value = '" . $this->password . "';
            input.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        }
    ");

        $this->exts->capture('2-Form-Filled');
        sleep(5);

        $this->exts->log("Submit Login Form");
        $this->exts->moveToElementAndClick($this->submit_login_selector);

        sleep(10);
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('lightning-datatable', 30);
        $this->exts->capture("4-invoices-page");

        // Use JavaScript to extract invoice data from lightning-datatable
        $invoices = $this->exts->executeSafeScript('
        let invoiceData = [];
        let datatable = document.querySelector("lightning-datatable");
        const baseUrl = "https://easyaccess.o2business.de/";

        if (datatable && datatable.data) {
            datatable.data.forEach(row => {
                invoiceData.push({
                    invoiceName: row.invoiceNumber.replace("/", "-"), // Invoice Name
                    invoiceDate: row.invoiceDate,   // Invoice Date
                    invoiceAmount: row.invoiceAmount, // Invoice Amount
                    invoiceUrl: baseUrl + row.getDocument.replace("../", "")    // Download URL
                });
            });
        }
    
        return invoiceData;
    ');

        if (empty($invoices)) {
            $this->exts->log("No invoices found.");
            return;
        }

        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('Invoice Name: ' . $invoice['invoiceName']);
            $this->exts->log('Invoice Date: ' . $invoice['invoiceDate']);
            $this->exts->log('Invoice Amount: ' . $invoice['invoiceAmount']);
            $this->exts->log('Download URL: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');

            // Download invoice
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->isNoInvoice = false;
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
