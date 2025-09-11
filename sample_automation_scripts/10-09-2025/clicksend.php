<?php //added loadCookiesFromFile and added aditional sleep time according to page load
// handle empty invoices cases
// and remove unused commented code
// added waitFor function to wait for selector when page load
// added code to trigger no_invoice in case invoices not found
// added code to trigger success after login
// use base file  getElementByText function
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

    // Server-Portal-ID: 91850 - Last modified: 23.07.2025 06:46:40 UTC - User: 1

    /*Start script*/

    public $baseUrl = 'https://dashboard.clicksend.com/';
    public $loginUrl = 'https://dashboard.clicksend.com/login?';
    public $invoicePageUrl = 'https://dashboard.clicksend.com/account/billing-recharge/transactions';
    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button[type="submit"]';
    public $checkLoginFailedSelector = '';
    public $checkLoggedinSelector = 'a[ng-click*="logout"], a.avatar-online';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(2);

        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in
        // Wait for selector that make sure user logged in
        sleep(7);
        $this->waitFor($this->checkLoggedinSelector, 5);
        if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
            sleep(10);
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->waitForLoginPage();
            sleep(10);
        }
    }

    private function waitForLoginPage($count = 1)
    {
        sleep(5);
        $this->exts->waitTillPresent($this->username_selector, 15);
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");

            sleep(10);
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(10);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(10);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(10);

            $this->exts->capture("1-filled-login");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(10);
            $this->waitForLogin();
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
            $this->exts->loginFailure();
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'div.verification-code .code-box';
        $two_factor_message_selector = '//p[contains(text(),"code to mobile number ending")]';
        // $two_factor_submit_selector = 'form[action="/admin/auth/two_factor_authentication"] input[name="commit"]';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";

                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();

                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(2);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                // $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function waitForLogin($count = 1)
    {
        sleep(15);
        $this->checkFillTwoFactor();
        $this->waitFor($this->checkLoggedinSelector, 5);
        if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
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
            $logged_in_failed_selector = $this->exts->getElementByText('p.text-danger', ['Username or password is incorrect.', 'Benutzername oder Passwort ist falsch.'], null, false);
            if ($logged_in_failed_selector != null) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->urlContains('signup/select-product')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(5);
        if ($this->exts->querySelector('tr button[ng-click*="pdfExport"]') != null) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->querySelector('tr');
            foreach ($rows as $row) {
                $tags = $row->querySelector('td');
                if (count($tags) < 5) {
                    continue;
                }
                $as = $tags[4]->querySelector('button[ng-click*="pdfExport"]');
                if (count($as) == 0) {
                    continue;
                }

                $invoiceSelector = $as[0];
                $invoiceName = trim($tags[2]->getText());
                $invoiceDate = trim($tags[1]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[0]->getText())) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceSelector' => $invoiceSelector
                ));
                $this->isNoInvoice = false;
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);

            foreach ($invoices as $invoice) {
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'j M Y g:i:A', 'Y-m-d');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                // $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);

                    $invoice['invoiceSelector']->click();

                    // Wait for completion of file download
                    $this->exts->wait_and_check_download('pdf');

                    // find new saved file and return its path
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $count++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        } else if ($this->exts->exists('table > tbody > tr a[name*="download"]')) {
            $downloadCount = 0;
            $rows = count($this->exts->querySelectorAll('.billing-tabs table > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('.billing-tabs table > tbody > tr')[$i];
                $tags = $this->exts->querySelectorAll('td', $row);
                if (count($tags) >= 5 && $this->exts->querySelector('a[name*="download"]', $tags[4]) != null) {
                    if ($downloadCount >= 100) {
                        break;
                    }
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->querySelector('a[name*="download"]', $tags[4]);
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'M d, Y h:i A', 'Y-m-d');
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
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 && $paging_count < 50 &&
                $this->exts->querySelector('button.btn-next:not(.disabled) ') != null
            ) {
                $paging_count++;
                $this->exts->click_by_xdotool('button.btn-next:not(.disabled)');
                sleep(5);
                $this->processInvoices($paging_count);
            } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('button.btn-next:not(.disabled)') != null) {
                $paging_count++;
                $this->exts->click_by_xdotool('button.btn-next:not(.disabled)');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }
}
