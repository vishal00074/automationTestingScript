<?php // migrated added clear chrome function

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

    // Server-Portal-ID: 221822 - Last modified: 12.03.2025 04:50:05 UTC - User: 15

    /*Define constants used in script*/
    public $baseUrl = 'https://auftragsmanagement.deutschepost.de';
    public $loginUrl = 'https://auftragsmanagement.deutschepost.de';
    public $invoicePageUrl = '';

    public $username_selector = 'input#userid';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = '.messagecontainer';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElementByText('a.menuItem', ['Abmelden', 'Logout']) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();

            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('input[type="button"][onclick*="goSubmit"]');

            if ($this->exts->exists('input[type="button"][onclick*="goSubmit"]')) {
                $this->exts->moveToElementAndClick('input[type="button"][onclick*="goSubmit"]');
            }

            $this->checkFillLogin(0);
            sleep(10);
        }


        if ($this->exts->getElementByText('a.menuItem', ['Abmelden', 'Logout']) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $billing_button = $this->exts->getElementByText('a.menuItem', ['Rechnungsrecherche', 'accounting research']);
            if ($billing_button != null) {
                try {
                    $this->exts->log('Click Billing button');
                    $billing_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click Billing button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$billing_button]);
                }
                sleep(15);
                $this->invoicePage();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin($count = 0)
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
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
        sleep(10);
        $this->exts->capture("after-clear");
    }

    private function invoicePage()
    {
        sleep(25);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $startDate = date('d.m.Y', strtotime('-3 years'));
        } else {
            $startDate = date('d.m.Y', strtotime('-6 month'));
        }
        $this->exts->moveToElementAndType('input[name="dateFrom"]', '');
        sleep(3);
        $this->exts->moveToElementAndType('input[name="dateFrom"]', $startDate);
        sleep(3);
        $this->exts->moveToElementAndClick('input[type="submit"][value*="Suchen"]');
        sleep(10);
        $this->processInvoices();
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('table.hundredsizeTable > tbody > tr[class*="row"]'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table.hundredsizeTable > tbody > tr[class*="row"]')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a', $tags[1]) != null) {
                $download_button = $this->exts->getElement('a', $tags[1]);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim($tags[4]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceName: ' . $invoiceName);
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
                        $pdf_content = file_get_contents($downloaded_file);
                        if (stripos($pdf_content, "%PDF") !== false) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                $this->exts->moveToElementAndClick("input.bigbuttonback");
                $this->isNoInvoice = false;
                sleep(5);
            }
        }

        //Retricpage is not used here because it has already set the date of rage above, so always download from all the pages.
        if ($paging_count < 50 && $this->exts->getElement('input[src*="forward."]') != null) {
            $paging_count++;
            $this->exts->moveToElementAndClick('input[src*="forward."]');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
