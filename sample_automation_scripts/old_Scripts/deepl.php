<?php // handle empty invoice name

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

    // Server-Portal-ID: 79610 - Last modified: 30.07.2025 14:59:28 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.deepl.com/en/home';
    public $loginUrl = 'https://www.deepl.com/en/login';
    public $invoicePageUrl = 'https://www.deepl.com/en/your-account/billing';

    public $username_selector = 'input#menu-login-username';
    public $password_selector = 'input#menu-login-password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#menu-login-submit';

    public $check_login_failed_selector = 'span[data-testid="fieldError"], div[data-testid="error-notification"]';
    public $check_login_success_selector = 'button[id="usernav-button"]';

    public $isNoInvoice = true;

    /**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->check_solve_blocked_page();

                $this->exts->capture("1-login-page-filled");
                sleep(5);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(10);
                    $this->checkFillTwoFactor();
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices()
    {

        // In case of date filter:
        // If $restrictPages == 0, then download upto 2 years of invoices.
        // If $restrictPages != 0, then download upto 3 months of invoices with maximum 100 invoices.

        // In case of pagination and no date filter:
        // If $restrictPages == 0, then download all available invoices on all pages.
        // If $restrictPages != 0, then download upto pages in $restrictPages with maximum 100 invoices.

        // In case of no date filter and no pagination:
        // If $restrictPages == 0, then download all available invoices.
        // If $restrictPages != 0, then download upto 100 invoices.


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 5;
        $invoiceCount = 0;

        $terminateLoop = false;


        $this->exts->waitTillPresent('table tbody tr td:nth-child(4) button', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        do {

            $pagingCount++;

            $this->exts->waitTillPresent('table tbody tr td:nth-child(4) button', 30);
            $rows = $this->exts->querySelectorAll('table tbody tr');
            $button = 'td:nth-child(4) button';

            foreach ($rows as $row) {

                if ($this->exts->querySelector($button, $row) != null) {

                    $invoiceCount++;

                    $invoiceUrl = '';
                    $invoiceName = $this->exts->extract($button, $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);

                    $downloadBtn = $this->exts->querySelector($button, $row);

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

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                    $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'm/d/y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                    sleep(2);

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


                    $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

                    if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                        $terminateLoop = true;
                        break;
                    } elseif ($restrictPages == 0 && $dateRestriction && $lastDate) {
                        $terminateLoop = true;
                        break;
                    }
                }
            }


            if ($restrictPages != 0 && $pagingCount == $restrictPages) {
                break;
            } elseif ($terminateLoop) {
                break;
            }

            // pagination handle			
            if ($this->exts->exists('button[aria-label="next page"]:not([disabled])')) {
                $this->exts->log('Click Next Page in Pagination!');
                $this->exts->click_element('button[aria-label="next page"]:not([disabled])');
                sleep(5);
            } else {
                $this->exts->log('Last Page!');
                break;
            }
        } while (true);

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");
        sleep(10);
        $element = 'iframe#challenge-widget';
        $this->exts->waitTillPresent($element, 20);
        if ($this->exts->exists($element)) {
            $this->exts->capture("blocked-by-cloudflare");

            $this->exts->click_by_xdotool($element, 30, 28);
            sleep(10);
        }
    }


    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-checking-two-factor");
        $two_factor_selector = 'input#menu-login-mfa';
        $two_factor_message_selector = 'form > p';
        $two_factor_submit_selector = 'button#menu-mfa-submit';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                $this->exts->moveToElementAndClick('input[name="trusted"]:not(:checked) + span');
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);
                if ($this->exts->exists('form.two-factor-form span[class*="_error-message_"]')) {
                    $this->exts->log("Two factor can not solved");
                    $this->exts->loginFailure(1);
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
