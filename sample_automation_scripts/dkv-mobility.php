<?php // replace waitTillPresent and waitTillPresentAny to waitFor and handle empty invoice name case

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

    // Server-Portal-ID: 1664932 - Last modified: 22.07.2025 20:55:39 UTC - User: 1

    public $baseUrl = 'https://www.dkv-mobility.com/en/';
    public $loginUrl = 'https://my.dkv-mobility.com/dkv-portal-webapp';
    public $invoicePageUrl = 'https://my.dkv-mobility.com/customer/invoices/overview';

    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[name="login"]';

    public $check_login_success_selector = '//div[contains(@class, "wireframe-icon") and contains(text(), "logout")]';
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
        $this->waitFor($this->check_login_success_selector);
      
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(3);
            $this->waitFor($this->username_selector);
            $button_cookie = $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\')');
            if ($button_cookie != null) {
                $this->exts->execute_javascript("arguments[0].click()", [$button_cookie]);
                sleep(5);
            }
            $this->checkFillLogin();
            $this->waitFor($this->check_login_success_selector, 5);
            if ($this->exts->getElement('a#loginContinueLink') != null) {
                $this->exts->moveToElementAndClick('a#loginContinueLink');
                sleep(5);
            }
           $this->waitFor($this->check_login_success_selector, 5);
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(5);
            }
            //click terms
            if ($this->exts->exists('input#kc-accept') && $this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->moveToElementAndClick('input#kc-accept');
                sleep(3);
                 $this->waitFor($this->check_login_success_selector, 5);
                if ($this->exts->exists('input#kc-accept') && $this->exts->getElement($this->check_login_success_selector) == null) {
                    $this->exts->moveToElementAndClick('input#kc-accept');
                    sleep(3);
                     $this->waitFor($this->check_login_success_selector, 5);
                }
            }
            if ($this->exts->getElements('button#onetrust-accept-btn-handler') != null) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(2);
            }
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(3);
            $this->waitFor('span[data-test="cf-customer-search-toggle"],[id*="customerSelectForm:custNoSelection"] button.ui-autocomplete-dropdown');
            sleep(2);
            if ($this->exts->exists('div#usercentrics-root')) {
                $this->exts->execute_javascript("document.querySelector('div#usercentrics-root').shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]').click()");
                sleep(3);
            }
            $this->exts->moveToElementAndClick('span[data-test="cf-customer-search-toggle"],[id*="customerSelectForm:custNoSelection"] button.ui-autocomplete-dropdown');
            sleep(1);

            $accounts = $this->exts->getElements('span[data-test="cf-customer-search-customer-number"],[id*="customerSelectForm:custNoSelection_panel"] ul li');
            $this->exts->log('Total Accounts - ' . count($accounts));

            if (count($accounts) > 1) {
                $account_array = [];

                for ($i = 0; $i < count($accounts); $i++) {
                    $element = 'dkv-customer-record:nth-child(' . ($i + 1) . ') span[data-test="cf-customer-search-customer-number"]';
                    $accountNumber = $this->exts->getElement($element)->getAttribute('innerText');
                    $account_array[] = $accountNumber;
                }

                print_r($account_array);

                foreach ($account_array  as $account) {

                    $this->exts->log('Accounts - ' . $account);

                    $accountsToSelect = array_filter(explode(',', $this->exts->config_array['account_number']));

                    if (empty($accountsToSelect) || in_array($account, $accountsToSelect)) {
                        $btnClick = $this->exts->getElement('//dkv-customer-record//span[@data-test="cf-customer-search-customer-number" and text()="' . $account . '"]', '', 'xpath');
                        try {
                            $btnClick->click();
                        } catch (\Exception $ex) {
                            $this->exts->execute_javascript('arguments[0].click()', [$btnClick]);
                        }
                        sleep(15);
                        if ($this->exts->getElements('button#onetrust-accept-btn-handler') != null) {
                            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                            sleep(2);
                        }

                        if (!$this->exts->exists('lib-invoice-overview-table-pc mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"])')) {

                            $this->exts->moveToElementAndClick('span[data-test="cf-customer-search-toggle"],[id*="customerSelectForm:custNoSelection"] button.ui-autocomplete-dropdown');
                            sleep(5);
                        }

                        $this->processInvoices();

                        $this->exts->moveToElementAndClick('span[data-test="cf-customer-search-toggle"],[id*="customerSelectForm:custNoSelection"] button.ui-autocomplete-dropdown');
                        sleep(5);
                    }
                }
            } else {
                if ($this->exts->getElements('button#onetrust-accept-btn-handler') != null) {
                    $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                    sleep(2);
                }
                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract('.kc-feedback-text')), 'account is disabled') !== false) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->exists('input[name="password-new"]')) {
                $this->exts->account_not_ready();
            }
            if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            }
            if ($this->exts->getElementByText('#dkv-login-container .alert-error, div#password-error', ['invalid username or password', 'tiger benutzername oder passwort', "Nom d'utilisateur ou mot de passe invalide.", 'utilisateur ou mot de passe invalide', 'Benutzername oder Passwort'], null, false) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
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
            sleep(3);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('lib-invoice-overview-table-pc mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"]),table mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"] span.download-link');
        sleep(1);
        if ($this->exts->exists('div#usercentrics-root')) {
            $this->exts->execute_javascript("document.querySelector('div#usercentrics-root').shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]').click()");
            sleep(3);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('lib-invoice-overview-table-pc mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"]),table mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"]'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('lib-invoice-overview-table-pc mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"]),table mat-row[style*="visible"] > mat-cell > mat-table > mat-row:not([style*="none"]')[$i];
            $tags = $this->exts->getElements('mat-cell', $row);
            if (count($tags) >= 7 && $this->exts->getElement('span.download-link', $tags[4]) != null) {
                $this->isNoInvoice = false;
                if ($this->exts->getElement('span.download-link.clickable', $tags[5]) != null) {
                    $download_button = $this->exts->getElement('span.download-link.clickable', $tags[5]);
                } else {
                    $download_button = $this->exts->getElement('span.download-link', $tags[4]);
                }
                $invoiceName = str_replace('/', '', trim($tags[1]->getAttribute('innerText')));

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getAttribute('innerText'))) . ' USD';
                $invoiceAccountNum = trim($tags[2]->getAttribute('innerText'));

                $invoiceName = $invoiceName . '-' . $invoiceAccountNum . '.pdf';

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceAccountNum: ' . $invoiceAccountNum);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'j/n/y', 'Y-m-d');
                if ($parsed_date == '') {
                    $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                }
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
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(3);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) == '' && !file_exists($downloaded_file)) {
                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);
                    }
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('span.ui-paginator-next:not(.ui-state-disabled)') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('span.ui-paginator-next:not(.ui-state-disabled)');
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
