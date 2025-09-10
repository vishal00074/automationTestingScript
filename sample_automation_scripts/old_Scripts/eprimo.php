<?php // updated selector to check user logged in or not and increase sleep time update invoice name extract the invoice name from invoice url and date

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

    // Server-Portal-ID: 15613 - Last modified: 29.07.2025 09:59:25 UTC - User: 1

    // Start Script 

    public $baseUrl = 'https://www.eprimo.de';
    public $loginUrl = 'https://www.eprimo.de/anmelden';
    public $invoicePageUrl = 'https://www.eprimo.de/kundenportal/posteingang';
    public $username_selector = 'input#email, form input#login[data-format="email"]';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = '.Login form button[type="submit"]';
    public $check_login_failed_selector = 'div.form-error-block, .Login form > div.error_message';
    public $check_login_success_selector = 'a[href*="/auth/logout"], a[href*="/abgemeldet"], div.loginButtonWrapper--loggedIn';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        //Accept Cookies button
        $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
        sleep(3);
        $this->exts->capture('1-init-page');
        $this->exts->click_by_xdotool('button#navAnchor_kupo');
        sleep(5);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            //Accept Cookies button
            $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
            sleep(3);

            $this->checkFillLogin();
            sleep(20);
            if ($this->exts->exists('#modal button[type="submit"]') && $this->exts->exists('[for="termsOfUse_confirmed"]')) {
                $this->exts->moveToElementAndClick('[for="termsOfUse_confirmed"]');
                sleep(2);
                $this->exts->moveToElementAndClick('#modal button[type="submit"]');
                sleep(15);
            }
            if ($this->exts->exists('a[href="/warenkorb"] + button span.Badged')) {
                $this->exts->moveToElementAndClick('a[href="/warenkorb"] + button span.Badged');
                sleep(5);
            }
            $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();');
            sleep(3);
            $this->exts->click_by_xdotool('button#navAnchor_kupo');
            sleep(5);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $account_lock_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['Ihr Konto ist temporÃƒÆ’Ã‚Â¤r gesperrt', 'Your account is temporarily blocked'], null, false);
            if ($account_lock_selector != null) {
                $this->exts->log(__FUNCTION__ . '::Account is temporarily blocked');
                $this->exts->account_not_ready();
            }

            $logged_in_failed_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['login details are not correct', 'Anmeldedaten sind nicht korrekt', 'Zugangsdaten sind leider nicht korrekt'], null, false);
            if ($logged_in_failed_selector != null) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
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
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(10);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public $totalInvoices = 0;

    private function processInvoices()
    {
        sleep(8);
        $load_more = 0;
        $this->exts->log('Trying to load more');
        while ($load_more < 10 && $this->exts->exists('button.more-button')) {
            $load_more++;
            $this->exts->moveToElementAndClick('button.more-button');
            sleep(8);
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        sleep(20);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $isDownloadAll =  isset($this->exts->config_array["download_all_documents"]) ? true : false;
        $indexInvoice = 1;
        $rows = $this->exts->getElements('div[class*="slick-current"] div[class="mailList"]');
        foreach ($rows as $index => $row) {
            $tags = $this->exts->getElements('a[href*="api/v1/document/"]', $row);
            if ($tags && count($tags) >= 3) {

                $invoiceUrl = $tags[0]->getAttribute("href");
                $extractName = str_replace('=', '', substr($invoiceUrl, -35));
                $invoiceDate = trim($this->exts->extract('.date', $row, 'innerText'));
                $invoiceName = $extractName . $invoiceDate;
                $subject = trim($tags[2]->getAttribute('innerText') ?? '');
                $invoiceAmount = '';

                if ($subject === 'Ihre Rechnung' || $isDownloadAll) {
                    $invoices[] = [
                        'invoiceName'   => $invoiceName,
                        'invoiceDate'   => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl'    => $invoiceUrl
                    ];

                    $this->isNoInvoice = false;
                    $indexInvoice++;
                }
            }
        }

        if (count($invoices) < 1) {
            if ($this->exts->exists('div.inbox a[href*="document/get/"]')) {
                $rows = $this->exts->getElements('div.inbox a[href*="document/get/"]');
                foreach ($rows as $row) {
                    $invoiceUrl = $row->getAttribute("href");
                    $extractName = str_replace('=', '', substr($invoiceUrl, -35));
                    $invoiceDate = trim($this->exts->extract('.date', $row, 'innerText'));
                    $invoiceName = $extractName . $invoiceDate;
                    $invoiceAmount = '';
                    $subject = trim($row->getAttribute('innerText'));
                    $indexInvoice++;
                    $this->exts->log('subject: ' . $subject);
                    if (strpos($subject, 'Ihre Rechnung') !== false || $isDownloadAll) {
                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                        $this->isNoInvoice = false;
                    }
                }
            } else {
                $rows = $this->exts->getElements('div.slick-active div.inbox__slide a[href*="document/get/"]');
                foreach ($rows as $row) {
                    $invoiceUrl = $row->getAttribute("href");
                    $invoiceName = trim(array_pop(explode('document/get/', $invoiceUrl)));
                    $invoiceDate = trim($this->exts->extract('.date', $row, 'innerText'));
                    $invoiceAmount = '';
                    $subject = trim($row->getAttribute('innerText'));
                    $this->exts->log('subject: ' . $subject);
                    if (strpos($subject, 'Ihre Rechnung') !== false || $isDownloadAll) {
                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                        $this->isNoInvoice = false;
                    }
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($this->totalInvoices >= 50 && $restrictPages != 0) {
                return;
            }

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
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
