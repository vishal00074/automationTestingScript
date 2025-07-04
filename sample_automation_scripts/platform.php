<?php // migrated and updated login success selector

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

    // Server-Portal-ID: 39847 - Last modified: 19.11.2024 14:21:33 UTC - User: 1

    public $baseUrl = 'https://accounts.platform.sh/';
    public $loginUrl = 'https://auth.api.platform.sh/';
    public $homeUrl = 'https://accounts.platform.sh/user/orders';

    public $username_selector = 'input#email_address, form input#username';
    public $password_selector = 'input#password, form input#password';
    public $remember_me_selector = '';
    public $submit_login_btn = '';

    public $checkLoginFailedSelector = '';
    public $checkLoggedinSelector = 'div.profile, input#project-search-input';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in
        $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
        // Wait for selector that make sure user logged in
        sleep(10);
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
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
        $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $multi_languages_next = ['next', 'NÃ¤chster', 'suivant'];
            $next_button = $this->exts->getElementByText('button', $multi_languages_next);
            $this->exts->click_element($next_button);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            if ($this->remember_me_selector != '') {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }


            $this->exts->capture("1-filled-login");

            $multi_languages_submit = ['log in', 'Anmeldung', 's\'identifier'];
            $submit_button = $this->exts->getElementByText('button', $multi_languages_submit);
            $this->exts->click_element($submit_button);
            sleep(5);
            $this->checkFillTwoFactor();
            sleep(5);
            $this->waitForLogin();
        } else {
            if ($count < 5) {
                $count = $count + 1;
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
        if (strpos($this->exts->extract('div#fallback .with-js p'), 'disable any ad blockers and if all else fails') !== false && $this->exts->exists('div#fallback .with-js a[href="/"]')) {
            $this->exts->moveToElementAndClick('div#fallback .with-js a[href="/"]');
            sleep(10);
            $this->exts->capture("after-click-button-back");
        }


        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->homeUrl);
            sleep(15);

            $this->processInvoices();

            $this->exts->success();
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->waitForLogin($count);
            } else {
                $this->exts->log('Timeout waitForLogin');
                $this->exts->capture("LoginFailed");
                $logged_in_failed_selector = $this->exts->getElementByText('div', ['Incorrect email address and password combination', 'Please enter a valid email address']);
                if ($logged_in_failed_selector != null) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }

    // 2 FA
    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'div input[id*="fa"]';
        $two_factor_message_selector = 'div label';
        $two_factor_submit_selector = 'div button:not([width*=cal])';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
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

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
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

    private function processInvoices($count = 1, $pageCount = 1)
    {
        sleep(5);
        if ($this->exts->getElement('table > tbody > tr a[href*="invoices/"][href*="/pdf"]') != null) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->getElements('table > tbody > tr');
            foreach ($rows as $row) {
                $tags = $row->getElements('td');
                if (count($tags) < 6) {
                    continue;
                }
                $as = $tags[5]->getElements('a[href*="invoices/"][href*="/pdf"]');
                if (count($as) == 0) {
                    continue;
                }

                $invoiceUrl = $as[0]->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute("innerText"));
                $invoiceDate = trim(array_pop(explode('-', $tags[1]->getAttribute("innerText"))));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);

            foreach ($invoices as $invoice) {
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F j, Y', 'Y-m-d');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Dowloading invoice ' . $count . '/' . $totalFiles);

                    $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                    // sleep(2);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $count++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }

            // next page
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $pageCount < 50 && $this->exts->getElement('.pagination .next a') != null) {
                $pageCount++;
                $this->exts->moveToElementAndClick('.pagination .next a');
                sleep(5);
                $this->processInvoices(1, $pageCount);
            }
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->processInvoices($count, $pageCount);
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
