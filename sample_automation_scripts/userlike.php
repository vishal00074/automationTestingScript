<?php // migrated and updated download code.

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

    // Server-Portal-ID: 28615 - Last modified: 16.10.2024 13:41:25 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://www.userlike.com/de/login";
    public $loginUrl = "https://www.userlike.com/de/login";
    public $homePageUrl = "https://www.userlike.com/de/dashboard/company/invoice";
    public $username_selector = "form input[name='username']";
    public $password_selector = "form input[name='password']";
    public $submit_button_selector = "form button[type='submit']";
    public $login_tryout = 0;
    public $restrictPages = 3;



    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");


        // .aptr-engagement-close-btn
        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            if ($this->exts->getElement('//button[contains(text(), "Abbrechen")]', null, 'xpath') != null) {
                $this->exts->getElement('//button[contains(text(), "Abbrechen")]', null, 'xpath')->click();
                sleep(2);
            }
            $this->fillForm(0);
            sleep(10);

            if (stripos($this->exts->extract('form .chakra-form__error-message', null, 'innerText'), 'the provided username or password is wrong.') !== false) {
                $this->exts->log('Error message: ' . $this->exts->extract('form .chakra-form__error-message', null, 'innerText'));
                $this->exts->loginFailure(1);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
            $this->exts->success();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null || $this->exts->getElement($this->password_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                if ($this->exts->getElement($this->username_selector) != null) {
                    $this->exts->log("Enter Username");

                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                }

                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
            if ($this->exts->getElement('a[href*="logout"], div[id*="account-menu"], #sidebar-operator-availability') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->exts->openUrl("https://app.userlike.com/#/settings/account/invoice/");
        sleep(25);

        $this->downloadInvoice();

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    public function downloadInvoice()
    {
        $this->exts->log("Begin downlaod invoice 1");

        try {
            if ($this->exts->getElement("table.chakra-table tbody tr") != null) {
                $invoices = array();
                $rows = $this->exts->getElements("table.chakra-table tbody tr");
                foreach ($rows as $key => $row) {

                    if ($this->totalFiles >= 50) {
                        return;
                    }

                    $invoiceBtn = $this->exts->getElement('button[id*="menu-button"]', $row);
                    if ($invoiceBtn != null) {
                        sleep(2);
                        $invoiceUrl = '';
                        $invoiceName = '';
                        $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                        $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);

                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                        $invoiceFileName = '';
                        $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' .  $invoiceDate);


                        try {
                            $invoiceBtn->click();
                        } catch (\Exception $exception) {
                            $this->exts->executeSafeScript("arguments[0].click()", [$invoiceBtn]);
                        }
                        sleep(5);

                        if ($this->exts->queryXpath('//button[text()="Download" and @role="menuitem"]') != null) {
                            $this->exts->click_element('//button[text()="Download" and @role="menuitem"]');
                            sleep(5);
                        }

                        $this->exts->no_margin_pdf = 1;

                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        sleep(2);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $invoiceName = basename($downloaded_file, '.pdf');

                            $this->exts->log('invoiceName: ' . $invoiceName);
                            $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                            sleep(1);
                            $this->totalFiles++;
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
