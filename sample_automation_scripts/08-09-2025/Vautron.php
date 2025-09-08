<?php //  migrated updated download btn name date and amount selector 
// remove clearChrome function before loadCookiesFromFile  added loginfailed selector and messsage
// added waitFor function to optimize the script 
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

    // Server-Portal-ID: 14577 - Last modified: 04.04.2025 11:41:08 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';
    public $loginUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';
    public $invoicePageUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';

    public $username_selector = '#loginbox input#logincnotext, #loginwrapper input#logincnotext';
    public $password_selector = '#loginbox input#loginpass, #loginwrapper input#loginpass';
    public $remember_me_selector = '';
    public $submit_login_selector = '#loginbox input#loginbutton, #loginwrapper button#loginbutton';

    public $check_login_failed_selector = 'div.alert-danger span#showInactiveText';
    public $check_login_success_selector = 'a#logout, a[title="Abmelden"]';

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
        $this->exts->capture('1-init-page-before-loadcookies');
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        $this->waitFor($this->check_login_success_selector, 3);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }
        $this->waitFor($this->check_login_success_selector);
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
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
        if ($this->exts->exists($this->password_selector)) {
            sleep(3);
            $this->exts->capture_by_chromedevtool("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            $this->exts->capture_by_chromedevtool("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);

            $login_response = $this->getLoginResponse($this->username, $this->password);

            if ($login_response == 'DENIED') {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function getLoginResponse($usernameVal, $passwordVal)
    {
        $usernameValue = base64_encode($usernameVal);
        $passwordValue = base64_encode($passwordVal);

        $login_response = $this->exts->executeSafeScript('
            var username = atob("' . $usernameValue . '");
            var password = atob("' . $passwordValue . '");
            
            var form_data = new FormData();
            form_data.append("logincno", username);
            form_data.append("loginpass", password);
            
            // Send login request
            var xhr = new XMLHttpRequest();
            
            xhr.open("POST", "https://kundencenter.n0q.de/ajax/login.php", false);
            
            xhr.send(form_data);

            var response_data =xhr.response;
            return  response_data;
        ');

        $this->exts->log('lOGIN RESPONSE : ' . $login_response);

        return $login_response;
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(1);
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices($paging_count = 0)
    {
        sleep(10);
        $this->waitFor('table#billingTable tbody tr, table#tableRechnungen tbody tr', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('table#billingTable tbody tr, table#tableRechnungen tbody tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table#billingTable tbody tr, table#tableRechnungen tbody tr')[$i];
            if ($this->exts->getElement('a.pdf', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a.pdf',  $row);
                $invoiceName = trim($this->exts->extract('td:nth-child(1)', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($this->exts->extract('td:nth-child(3)', $row));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(7)', $row))) . ' USD';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'Y-m-d', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);

                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('a#billingTable_next:not(.ui-state-disabled), div#tableRechnungen_wrapper ul.pagination li:not(.disabled) button.next') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('a#billingTable_next:not(.ui-state-disabled)');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Avaza', '2673526', 'bHVkd2lnQHN1cnZleWVuZ2luZS5jb20=', 'cHNDZms4Ny4=');
$portal->run();
