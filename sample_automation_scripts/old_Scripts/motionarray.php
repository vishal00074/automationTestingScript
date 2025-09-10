<?php // replce waitTillPresent and exists to custom js function waitFor and isExists function handle empty invoices name

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
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
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
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

    // Server-Portal-ID: 131010 - Last modified: 07.08.2025 14:39:34 UTC - User: 1

    // Start Script 

    public $baseUrl = "https://motionarray.com/";
    public $loginUrl = "https://motionarray.com/account/login";
    public $invoiceUrl = "https://motionarray.com/account/invoices/";
    public $username_selector = 'input#login-email, form input[type="email"]';
    public $password_selector = 'input#login-password, form input[type="password"]';
    public $submit_button_selector = '.login-form button[type="submit"], form button[type="submit"]';
    public $check_login_success_selector = 'a[href*="/logout"], a[href="/account/details/"]';
    public $login_tryout = 0;
    public $restrictPages = 3;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->waitFor($this->check_login_success_selector);
        sleep(5);
        $this->check_solve_cloudflare_page();
        sleep(20);
        if ($this->exts->getElement('.adroll_consent_banner a#adroll_consent_accept div#adroll_allow') != null) {
            $this->exts->moveToElementAndClick('.adroll_consent_banner a#adroll_consent_accept div#adroll_allow');
            sleep(3);
        }
        if ($this->isExists('#cookiescript_accept')) {
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(3);
        }
        $this->exts->capture('1-init-page');

        // If the user has not logged in from cookie, do login.
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged in via cookie');
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            $this->waitFor($this->check_login_success_selector);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->capture("LoginFailed");
            if (strpos($this->exts->extract('form.login-form div[class*="bg-ma-red"], p[class*="text-red"]', null, 'innerText'), 'passwor') !== false) {
                $this->exts->log("Login fail!!!!: " . $this->exts->extract('form.login-form div[class*="bg-ma-red"], p[class*="text-red"]', null, 'innerText'));
                $this->exts->loginFailure(1);
            }
            $this->exts->loginFailure();
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->waitFor($this->username_selector, 10);
            if ($this->isExists($this->username_selector) && $this->isExists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int) $this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(3);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->exts->moveToElementAndClick('input[name="remember_me"]');
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->isExists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->getElement($unsolved_cloudflare_input_xpath) != null) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }
    public function invoicePage()
    {
        $this->exts->openUrl($this->invoiceUrl);
        sleep(5);
        $this->downloadInvoice();
        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */

    public $totalFiles = 0;
    public function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->waitFor('table tbody tr td a[href*="/invoices/"]', 15);
        if ($this->exts->getElement('#cookiescript_accept') != null) {
            $this->exts->moveToElementAndClick('#cookiescript_accept');
            sleep(1);
        }
        $this->exts->log("Begin download invoice");
        $this->exts->capture('4-List-invoice');
        try {
            if ($this->exts->querySelector('table tbody tr td a[href*="/invoices/"]') != null) {
                $receipts = $this->exts->querySelectorAll('table tbody tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->querySelectorAll('td', $receipt);
                    if (count($tags) == 4 && $this->exts->querySelector('td a[href*="/invoices/"]', $receipt) != null) {
                        $receiptDate = trim($tags[2]->getText());
                        $receiptUrl = $this->exts->extract('td a[href*="/invoices/"]', $receipt, 'href');
                        $receiptName = trim($tags[0]->getText());
                        $receiptAmount = trim($tags[1]->getText());
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf': '';

                        $parse_day = $this->exts->parse_date($receiptDate, 'j F, Y');
                        $receiptDate = $this->exts->parse_date($receiptDate, 'd F, Y', 'Y-m-d', 'en');

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice ParseDay: " . $parse_day);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    } else if (count($tags) == 3 && $this->exts->querySelector('td a[href*="/invoices/"]', $receipt) != null) {
                        $receiptDate = trim($tags[1]->getText());
                        $receiptUrl = $this->exts->extract('td a[href*="/invoices/"]', $receipt, 'href');
                        $receiptName = trim($tags[0]->getText());
                        $receiptAmount = '';
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf': '';

                        $parse_day = $this->exts->parse_date($receiptDate, 'j F, Y');
                        $receiptDate = $this->exts->parse_date($receiptDate, 'd F, Y', 'Y-m-d', 'en');

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice ParseDay: " . $parse_day);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }
                $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));
                $this->totalFiles = count($invoices);
                $count = 1;
                foreach ($invoices as $invoice) {
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'], $invoice['receiptAmount'], $downloaded_file);
                        $count++;
                    }
                }
                // next page
                if (
                    $this->restrictPages == 0 && $pageCount < 50 &&
                    $this->exts->querySelector('.pagination a.next_page:not(.disabled)') != null
                ) {
                    $pageCount++;
                    $this->exts->moveToElementAndClick('.pagination a.next_page:not(.disabled)');
                    sleep(5);
                    $this->downloadInvoice(1, $pageCount);
                } else if ($this->restrictPages > 0 && $pageCount < $this->restrictPages && $this->exts->querySelector('.pagination a.next_page:not(.disabled)') != null) {
                    $pageCount++;
                    $this->exts->moveToElementAndClick('.pagination a.next_page:not(.disabled)');
                    sleep(5);
                    $this->downloadInvoice(1, $pageCount);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
