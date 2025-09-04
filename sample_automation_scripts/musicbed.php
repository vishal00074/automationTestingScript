<?php // replace exists with custom js isEixsts function added code to close popup after login success
//  updated expired tab selector
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

    // Server-Portal-ID: 14258 - Last modified: 27.06.2025 14:32:55 UTC - User: 1

    public $baseUrl = 'https://www.musicbed.com/';
    public $loginUrl = 'https://www.musicbed.com/login';
    public $invoicePageUrl = 'https://www.musicbed.com/account/subscriptions';

    public $username_selector = 'input[name="login"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = 'input#remember';
    public $submit_login_selector = 'button#Login';

    public $check_login_failed_selector = 'form[data-cy="_LoginForm_form"] p';
    public $check_login_success_selector = 'a[href="/account/settings"], a[href="/account/pro-account"], a[href="/account/enterprise-account"]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture('1-init-open-homepage');

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(7);
            $this->checkFillLogin();
            sleep(10);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->querySelector('button.chakra-dialog__closeTrigger') != null) {
                $this->exts->moveToElementAndClick('button.chakra-dialog__closeTrigger');
                sleep(5);
            }


            $this->exts->openUrl($this->invoicePageUrl);
            sleep(7);
            // Open invoices url and download invoice
            if ($this->isExists('a[href="/account/subscriptions"]:not([aria-current])')) {
                $this->exts->click_by_xdotool('a[href="/account/subscriptions"]:not([aria-current])');
                sleep(10);
                $this->processInvoicesPage();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'invalid') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(7);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
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

    private function processInvoicesPage()
    {
        // process invoices at tab "ACTIVE"
        // download invoices with default date
        $this->processInvoices();
        // change date
        if ($this->exts->querySelector('input[id*="react-select"]') != null) {
            // click open select date
            $this->exts->click_by_xdotool('input[id*="react-select"]');
            sleep(1);
            // click date
            $this->exts->click_by_xdotool('div[class*="menu"]', 113, 65);
            sleep(1);
            $this->processInvoices();
        }
        // process invoices at tab "EXPIRED"
        $expired_tab = $this->exts->getElementByText('[data-cy="_Subscriptions_div"]>div', 'EXPIRED', null, false);
        if ($expired_tab != null) {
            $this->exts->click_element($expired_tab);
            sleep(2);
            // download invoices with default date
            $this->processInvoices();
            // change date
            if ($this->exts->querySelector('input[id*="react-select"]') != null) {
                // click open select date
                $this->exts->click_by_xdotool('input[id*="react-select"]');
                sleep(1);
                // click date
                $this->exts->click_by_xdotool('div[class*="menu"]', 113, 65);
                sleep(1);
                $this->processInvoices();
            }
        }
    }

    private function processInvoices()
    {
        sleep(5);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        if ($this->isExists('button[data-cy="_Table_Button"]')) {
            $this->exts->moveToElementAndClick('button[data-cy="_Table_Button"]');
            sleep(2);
        }
        $this->exts->moveToElementAndClick('table>tbody>tr');
        sleep(2);
        $rows = $this->exts->querySelectorAll('table>tbody>tr');
        $this->exts->log('Row count: ' . count($rows));
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            $this->exts->log('Tag count: ' . count($tags));

            if (count($tags) >= 6 && $this->exts->getElement('a[data-cy="_PaymentsTableBlock_Link"]', $row) != null) {
                // Extract required attributes
                $invoiceName = trim(explode("\\n", trim($tags[0]->getAttribute('innerText')))[0]);
                $invoiceName = str_replace('#', '', $invoiceName);
                $invoiceDate = trim(explode("\\n", trim($tags[2]->getAttribute('innerText')))[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';
                $download_button = $this->exts->getElement('a[data-cy="_PaymentsTableBlock_Link"]', $row);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $parsed_date = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('Invoice Name: ' . $invoiceName);
                $this->exts->log('Invoice Date: ' . $parsed_date);
                $this->exts->log('Invoice Amount: ' . $invoiceAmount);

                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    if ($download_button != null) {
                        $this->exts->click_element($download_button);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->isNoInvoice = false;
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
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
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Musicbed', '2673305', 'cmVjaG51bmdAd2FsdHNtZWRpYS5kZQ==', 'U29ubmUzMTY0');
$portal->run();
