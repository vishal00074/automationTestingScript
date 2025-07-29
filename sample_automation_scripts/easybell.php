<?php // updtaed download code handle empty invoice case

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

    // Server-Portal-ID: 3932 - Last modified: 24.07.2025 14:06:33 UTC - User: 1

    public $baseUrl = 'https://login.easybell.de/rechnungen';
    public $loginUrl = 'https://login.easybell.de/login';
    public $invoicePageUrl = 'https://login.easybell.de/rechnungen';
    public $username_selector = 'input[autocomplete="username"]';
    public $password_selector = 'input[autocomplete="current-password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-test="submit"]';

    public $check_login_failed_selector = 'SELECTOR_error';
    public $check_login_success_selector = 'div.desktop-menu button[data-test="desktop-menu-button"]';

    public $isNoInvoice = true;

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
        if ($this->exts->querySelector('#dontShowAgainCheck') != null) {
            $this->exts->moveToElementAndClick('#dontShowAgainCheck');
            $this->exts->moveToElementAndClick('button.eb-button.eb-button--text.eb-button--base');
            sleep(10);
        }
        if ($this->exts->querySelector('button[data-test="toggleDesktopMenu"]') != null) {
            $this->exts->moveToElementAndClick('button[data-test="toggleDesktopMenu"]');
            sleep(3);
        }
        sleep(15);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            sleep(25);
            if (stripos(strtolower($this->exts->extract('.alert.error', null, 'innerText')), 'ihr account ist zur zeit gesperrt') !== false) {
                $this->exts->log('account currently blocked');
                $this->exts->account_not_ready();
            }
            if (stripos(strtolower($this->exts->extract('.alert.error', null, 'innerText')), 'sie haben falsche zugangsdaten eingegeben') !== false) {
                $this->exts->log('account currently blocked');
                $this->exts->loginFailure(1);
            }
            //2FA check
            if ($this->exts->exists('form fieldset  div[class*="eb-input"] input') && $this->exts->exists('header h2[class="text-sm font-regular"]')) {
                $this->checkFillTwoFactor();
            }

            //dontShowAgainCheck
            if ($this->exts->exists('#dontShowAgainCheck')) {
                $this->exts->moveToElementAndClick('#dontShowAgainCheck');
                $this->exts->moveToElementAndClick('button.eb-button.eb-button--text.eb-button--base');
                sleep(10);
            }
            if ($this->exts->exists('button[data-test="toggleDesktopMenu"]')) {
                $this->exts->moveToElementAndClick('button[data-test="toggleDesktopMenu"]');
                sleep(3);
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->openUrl('https://login.easybell.de/bills');
                $this->processInvoices1510();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);

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

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form fieldset  div[class*="eb-input"] input';
        $two_factor_message_selector = 'header h2[class="text-sm font-regular"]';
        $two_factor_submit_selector = 'form button[class*="eb-button eb-button--primar"]';
        $two_factor_resend_selector = 'form button[class*="eb-button eb-button--text"]';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

                $cha_arr = str_split($two_factor_code);
                $two_factor_els = count($this->exts->getElements($two_factor_selector));
                $this->exts->log("checkFillTwoFactor: Number of digit." . $two_factor_els);

                for ($i = 0; $i < $two_factor_els; $i++) {

                    $two_factor_el = $this->exts->getElements($two_factor_selector)[$i];
                    if ($i < count($cha_arr))
                        $this->exts->moveToElementAndType($two_factor_el, $cha_arr[$i]);
                }

                //$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->exists($two_factor_resend_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_resend_selector);
                    sleep(1);
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
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

    private function processInvoices()
    {
        $this->exts->waitTillPresent('.list-group-item .expand-icon.glyphicon-plus', 20);
        $this->exts->capture("4-invoices-page");


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 5;
        $invoiceCount = 0;


        if (
            $restrictPages == 0 &&
            $this->exts->getElement('.list-group-item .expand-icon.glyphicon-plus') != null
        ) {

            $expand_rows = $this->exts->getElements('.list-group-item .expand-icon.glyphicon-plus');
            foreach ($expand_rows as $expand_row) {
                try {
                    $this->exts->log('Click expand_row button');
                    $expand_row->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click expand_row button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$expand_row]);
                }
                sleep(3);
            }
        } else if ($this->exts->getElement('.list-group-item .expand-icon.glyphicon-plus') != null) {
            $this->exts->getElements('.list-group-item .expand-icon.glyphicon-plus')[0]->click();
            sleep(3);
        }

        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0] . '//' . $paths[2];

        $rows = $this->exts->getElements('li.list-group-item');

        foreach ($rows as $row) {
            $tags = $this->exts->getElements('div.row > span[class*="col"]', $row);
            if (count($tags) >= 7 && $this->exts->getElement('a[href*="/document/"][href*=".pdf"]', $tags[4]) != null) {

                $invoiceCount++;

                $invoiceUrl = $this->exts->getElement('a[href*="/document/"][href*=".pdf"]', $tags[4])->getAttribute("href");
                $invoiceName = trim($tags[4]->getAttribute('innerText'));

                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;


                $parsedInvoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $lastDate = !empty($parsedInvoiceDate) && $parsedInvoiceDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    break;
                } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    break;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    // updated 15102024
    private function processInvoices1510()
    {
        sleep(10);
        $this->exts->capture("4-new-invoices-page");
        $invoices = [];


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 5;
        $invoiceCount = 0;


        $rows = $this->exts->getElements('article table>tbody>tr');

        $this->exts->log("------Count: " . count($rows));

        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            $this->exts->log("------Count tags: " . count($tags));
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="document/bills"]', $tags[3]) != null) {

                $invoiceCount++;

                $invoiceUrl = $this->exts->getElement('a[href*="document/bills"]', $tags[3])->getAttribute("href");
                $invoiceName = trim($tags[2]->getAttribute('innerText'));

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;


                $parsedInvoiceDate = $this->exts->parse_date($invoiceDate, 'j. F Y', 'Y-m-d');
                $lastDate = !empty($parsedInvoiceDate) && $parsedInvoiceDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    break;
                } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    break;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'j. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $pdf_content = file_get_contents($downloaded_file);
                if (stripos($pdf_content, "%PDF") !== false) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                }
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
