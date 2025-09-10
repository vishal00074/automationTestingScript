<?php // migrated updated login code.

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

    // Server-Portal-ID: 72442 - Last modified: 29.05.2024 12:10:44 UTC - User: 1

    public $baseUrl = 'https://manage.cookiebot.com/de/manage';
    public $loginUrl = 'https://manage.cookiebot.com/de/login';
    public $invoicePageUrl = 'https://manage.cookiebot.com/de/invoices';

    public $username_selector = '#logincontainer input#pageloginemail, .login input#pageloginemail';
    public $password_selector = '#logincontainer input#pageloginpassword, .login input#pageloginpassword';
    public $remember_me_selector = '#logincontainer input#persistentLogin, .login input#persistentLogin';
    public $submit_login_btn = '#logincontainer a#pagesubmitLoginButton, .login a#pagesubmitLoginButton';

    public $checkLoginFailedSelector = '#logincontainer input#pageloginpassword';
    public $checkLoggedinSelector = 'a[href="javascript: userLogout();"].enabled';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->temp_keep_useragent = $this->exts->send_websocket_event(
            $this->exts->current_context->webSocketDebuggerUrl,
            "Network.setUserAgentOverride",
            '',
            ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36"]
        );
        sleep(2);

        $this->exts->openUrl($this->baseUrl);
        sleep(16);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        sleep(10);
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            // login with cookie, invoice can not load
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if (strpos($this->exts->extract('span#lblEmptyData'), 'Sie wurden keine Rechnungen erstellt') !== false) {
                $this->exts->clearCookies();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                $this->waitForLoginPage();
            } else {
                $this->waitForLogin();
            }
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->loginUrl);
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage($count = 1)
    {
        sleep(15);
        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(5);
        }
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("1-filled-login");

            $alertPopup = $this->exts->evaluate('
            window.alert = function(msg) {
                console.log("Alert detected:", msg);
                window._alertTriggered = true;
            };');


            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(15);

            $alertBox = $this->exts->execute_javascript('window._alertTriggered;');

            $this->exts->log("print alertBox ----->" . $alertBox);

            if ($alertBox) {
                $this->exts->log('Incorrect username password');
                $this->exts->loginFailure(1);
            }

            if ($count < 5) {
                $count++;
                $this->waitForLoginPage($count);
            }
        } else {
            if ($count < 5) {
                $count++;
                $this->waitForLoginPage($count);
            } else {
                $this->exts->log('Timeout waitForLoginPage');
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }

    private function waitForLogin($count = 1)
    {
        sleep(5);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            $this->exts->success();
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->waitForLogin($count);
            } else {
                $this->exts->log('Timeout waitForLogin');
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($count = 1)
    {
        sleep(5);
        if ($this->exts->getElement('.listContainer > a[href*="showInvoice"]') != null) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->getElements('.listContainer > a[href*="showInvoice"]');
            foreach ($rows as $row) {
                $tags = $row->getElements('div.listItem');

                $invoiceName = $row->getAttribute("data-id");
                $invoiceSelector = '.listContainer > a[data-id="' . $invoiceName . '"]';
                if (count($tags) > 3) {
                    $invoiceDate = trim($tags[1]->getText());
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' EUR';
                }

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceSelector' => $invoiceSelector
                ));
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);

            foreach ($invoices as $invoice) {
                if ($this->exts->getElement('#invoiceContainer:not([style*="display: none"]) div#closeInvoiceIcon')) {
                    $this->exts->moveToElementAndClick('#invoiceContainer:not([style*="display: none"]) div#closeInvoiceIcon');
                    sleep(2);
                }

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceSelector: ' . $invoice['invoiceSelector']);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);
                    $this->exts->moveToElementAndClick($invoice['invoiceSelector']);
                    sleep(2);

                    $downloaded_file = $this->exts->click_and_print('#printInvoiceIcon', $invoiceFileName);
                    sleep(1);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $count++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }

                $this->exts->moveToElementAndClick('#invoiceContainer:not([style*="display: none"]) div#closeInvoiceIcon');
                sleep(2);
            }
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->processInvoices($count);
            } else {
                $this->exts->log('Timeout processInvoices');
                $this->exts->capture('4-no-invoices');
                $this->exts->no_invoice();
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
