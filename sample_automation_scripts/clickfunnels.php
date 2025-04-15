<?php  //  added check_solve_cloudflare_login function 
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

    // Server-Portal-ID: 9187 - Last modified: 02.04.2025 14:29:43 UTC - User: 1

    public $baseUrl = "https://www.clickfunnels.com/";
    public $loginUrl = "https://www.clickfunnels.com/";
    public $homePageUrl = "https://app.clickfunnels.com/users/edit/billing/subscription";
    public $username_selector = "form#new_user input[name=\"user[email]\"]";
    public $password_selector = "form#new_user input[name=\"user[password]\"]";
    public $submit_button_selector = "form#new_user input[name=\"commit\"][type=\"submit\"]";
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $checkbox_coo = '';
    public $current_cursor = '';


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->send_websocket_event(
            $this->exts->current_context->webSocketDebuggerUrl,
            "Network.setUserAgentOverride",
            '',
            ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36"]
        );
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                $this->exts->waitTillPresent("//a[text()='ClickFunnels Classic Login']");
                if ($this->exts->exists("//a[text()='ClickFunnels Classic Login']")) {
                    // $this->exts->queryXpath("//a[text()='ClickFunnels Classic Login']")->click();
                    $this->exts->click_element("//a[text()='ClickFunnels Classic Login']");
                    sleep(5);
                    $this->exts->switchToNewestActiveTab();
                    $this->exts->closeAllTabsButThis();
                }
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            $this->check_solve_blocked_page();
            $this->fillForm(0);
            $this->check_solve_blocked_page();

            if ($this->exts->exists('div.error-block a[href="https://www.clickfunnels.com"]')) {
                $this->exts->log('Url error: ' . $this->exts->getUrl());
                $this->exts->moveToElementAndClick('div.error-block a[href="https://www.clickfunnels.com"]');
                sleep(15);
                $this->exts->capture("after-error-clicked");
                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->fillForm(0);
                    $this->check_solve_blocked_page();

                    sleep(10);
                }
            }

            $err_msg = "";
            if ($this->exts->querySelector("div[class*=\"error message\"]") != null) {
                $err_msg = trim($this->exts->querySelectorAll("div[class*=\"error message\"]")[0]->getAttribute('innerText'));
            }

            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }

            $err_msg = strtolower($this->exts->extract('div.error-block', null, 'innerText'));
            if (strpos($err_msg, 'something is broken') !== false) {
                $this->exts->loginFailure(1);
            }
            if (strpos(strtolower($this->exts->extract('div.error-block', null, 'innerText')), 'no page matching this path found') !== false) {
                $this->exts->account_not_ready();
            }
            if (strpos(strtolower($this->exts->extract('div.error', null, 'innerText')), 'nvalid email or password') !== false) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->exists($this->password_selector)) {
                $this->fillForm(1);
                sleep(10);
                $this->check_solve_blocked_page();
            }

            if ($this->exts->exists($this->password_selector)) {
                $this->fillForm(2);
                sleep(10);
                $this->check_solve_blocked_page();
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->exts->waitTillPresent($this->username_selector, 60);
            if ($this->exts->querySelector($this->password_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($this->password);
                sleep(3);
                $this->exts->capture("1-login-filled");
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                if (stripos(strtolower($this->exts->extract('div[class*=\"error message\"]')), 'password cannot be empty') !== false) {

                    $this->exts->log("Enter Password");
                    $this->exts->click_by_xdotool($this->password_selector);
                    sleep(2);
                    $this->exts->type_text_by_xdotool($this->password);
                    sleep(2);
                    $this->exts->moveToElementAndClick($this->submit_button_selector);
                }
                sleep(15);
                $this->check_solve_cloudflare_login();
                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->openUrl($this->loginUrl);
                    $this->fillForm(3);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_cloudflare_login($refresh_page = false)
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('div:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }


    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent('a[href*="sign_out"]', 20);
            if ($this->exts->querySelector('a[href*="sign_out"]') != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }


    private function invoicePage()
    {
        $this->exts->log("invoice Page");

        $this->exts->moveToElementAndClick('a#toast--action-agreement');
        sleep(2);
        $this->exts->waitTillPresent('a[href="/users/edit/billing/history"]', 20);

        if (!$this->exts->exists('a[href="/users/edit/billing/history"]')) {
            $this->exts->openUrl($this->homePageUrl);
            $this->exts->waitTillPresent('a[href="/users/edit/billing/history"]', 20);
        }

        $this->exts->moveToElementAndClick('a[href="/users/edit/billing/history"]');

        $this->exts->waitTillPresent("iframe#cd-frame-billing-history", 20);

        $inv_url = "";
        if ($this->exts->querySelector("iframe#cd-frame-billing-history") != null) {
            $inv_url = $this->exts->querySelectorAll("iframe#cd-frame-billing-history")[0]->getAttribute('src');
        } else if ($this->exts->querySelector('a[href*="/history"]') != null) {
            $inv_url = $this->exts->querySelectorAll('a[href*="/history"]')[0]->getAttribute('href');
        }
        $this->exts->log("Invoice url: " . $inv_url);

        if ($inv_url != "" && $inv_url != null) {
            $this->exts->openUrl($inv_url);
            $this->downloadInvoice();
        } else {
            $this->exts->log("No invoices!!!");
        }

        $this->exts->openUrl($this->homePageUrl);
        sleep(15);

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoices!!!");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    public $totalFiles = 0;
    private function downloadInvoice()
    {
        for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('#billing-history > .row a[href*="/invoice/download"]') == null; $wait_count++) {
            $this->exts->log('Waiting for invoice...');
            sleep(10);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('#billing-history > .row');

        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('.value', $row);
            $len_tags = count($tags);
            if (count($tags) >= 5 && $this->exts->querySelector('a[href*="/invoice/download"]', $tags[$len_tags - 1]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice/download"]', $tags[$len_tags - 1])->getAttribute("href");
                $invoiceName = end(explode(
                    '/',
                    explode('/invoice/download', $invoiceUrl)[0]
                ));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->querySelector('.value.right span.nowrap', $row)->getAttribute('innerText'))) . ' USD';
                if ($this->restrictPages == 3) {
                    $currentDate = time();
                    $sixMonthsAgo = mktime(0, 0, 0, date("m") - 6, date("d"), date("Y"));
                    if (strtotime($invoiceDate) > $sixMonthsAgo && strtotime($invoiceDate) < $currentDate) {
                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                    }
                } else {
                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->totalFiles += 1;
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::Timeout when download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
