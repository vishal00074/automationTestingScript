<?php // migrated updated loginfailed message

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 105804 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    public $baseUrl = 'https://login.srn-manager.de/Start/Default.aspx';
    public $invoicePageUrl = 'https://login.srn-manager.de/Desktop/Default.aspx?lang=de';

    public $username_selector = 'form input#username';
    public $password_selector = 'form input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form input[type="submit"]';

    public $check_login_failed_selector = 'form .error';
    public $check_login_success_selector = 'button.start, button.start, a[class*="start-button"]';

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
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->moveToElementAndClick($this->check_login_success_selector);
            // Open invoices page and download invoice
            // Click Abrechnung icon
            $this->exts->moveToElementAndClick('a.dock-item img[src*="abrechnungen"]');
            sleep(3);
            // Click Abrechnungs-Browser
            $this->exts->moveToElementAndClick('.x-layer.x-menu a.x-menu-item');

            $menu_items = $this->exts->getElements('[data-ref="itemEl"]');
            $this->exts->log('---- menu: ' . count($menu_items));
            if (count($menu_items) >= 7) {
                try {
                    $this->exts->log("Click invoice link ");
                    $menu_items[6]->click();
                } catch (Exception $e) {
                    $this->exts->log("Click invoice link by javascript ");
                    // $menu_items = $this->exts->getElements('[data-ref="itemEl"]');
                    $this->exts->executeSafeScript('arguments[0].click()', [$menu_items[6]]);
                }
                sleep(2);
                $menu_items = $this->exts->getElements('[data-ref="itemEl"]');
                $this->exts->log('---- menu: ' . count($menu_items));
                try {
                    $this->exts->log("Click invoice link ");
                    $menu_items[7]->click();
                } catch (Exception $e) {
                    $this->exts->log("Click invoice link by javascript ");
                    $this->exts->executeSafeScript('arguments[0].click()', [$menu_items[7]]);
                }
            }

            $this->processInvoicesNew();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoicesNew()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');

        for ($i = 0; $i < count($rows); $i++) {
            $tags = $this->exts->getElements('td', $rows[$i]);
            if (count($tags) >= 11) {
                $invoiceDate = $this->exts->extract('div', $tags[0], 'innerText');
                $invoiceName = $this->exts->extract('div', $tags[10], 'innerText');
                $invoiceAmount = $this->exts->extract('div', $tags[8], 'innerText');
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $this->isNoInvoice = false;

                try {
                    $this->exts->log("Click row");
                    $rows[$i]->click();
                } catch (Exception $e) {
                    $this->exts->log("Click row by javascript ");
                    $this->exts->executeSafeScript('arguments[0].click()', [$rows[$i]]);
                }

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceName: ' . $invoiceFileName);

                $download_button = $this->exts->getElements('[id*=invoices-panel] [data-ref="innerCt"][role=presentation] a');
                if (count($download_button) >= 1) {
                    try {
                        $this->exts->log("Click download button ");
                        $download_button[1]->click();
                    } catch (Exception $e) {
                        $this->exts->log("Click download button by javascript ");
                        $this->exts->executeSafeScript('arguments[0].click()', [$download_button[1]]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
