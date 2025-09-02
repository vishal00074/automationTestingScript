<?php // added loadCookiesFromFile and adjust sleep time udpated filter date code


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

    // Server-Portal-ID: 441 - Last modified: 29.08.2025 13:38:31 UTC - User: 1

    public $baseUrl = "https://www.cyberport.de/";
    public $accountUrl = "https://www.cyberport.de/tools/my-account/meine-daten";
    public $loginUrl = "https://www.cyberport.de/";
    public $username_selector = 'form.loginForm input[name="j_username"], form input[name="username"]';
    public $password_selector = 'form.loginForm input[name="j_password"], form input[name="password"]';
    public $submit_button_selector = 'form.loginForm button[type="submit"], form button[type=submit]';
    public $check_login_success_selector = 'form#customer-profile-form, header #headerContainer > div > div > div > div:nth-child(2) > div > button:nth-child(3)';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;
    public $restrictDate = '';
    public $dateRestriction = true;
    public $maxInvoices = 10;
    public $invoiceCount = 0;
    public $terminateLoop = false;
    public $totalInvoices = 0;


    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disableExtension();
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(4);
        // Load cookies
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->baseUrl);
        sleep(7);

        $this->check_solve_blocked_page();

        $accecptAllBtn = 'button#consent-accept-all';
        $this->exts->waitTillPresent($accecptAllBtn, 10);
        if ($this->exts->exists($accecptAllBtn)) {
            $this->exts->click_element($accecptAllBtn);
            sleep(7);
        }



        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
            $this->check_solve_blocked_page();

            $this->exts->waitTillPresent($accecptAllBtn, 15);
            if ($this->exts->exists($accecptAllBtn)) {
                $this->exts->click_element($accecptAllBtn);
                sleep(10);
            }

            $this->fillForm(0);
            sleep(15);

            if (
                strpos(strtolower($this->exts->extract('form .text-info.text-error')), 'mit diesen zugangsdaten ist eine anmeldung nicht') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('form.loginForm  div.notification-error')), 'eine anmeldung ist mit diesen zugangsdaten nicht') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->moveToElementAndClick('header #headerContainer');
                sleep(10);
            }

            if (!$this->checkLogin()) {
                $this->exts->openUrl($this->accountUrl);
                sleep(10);
                $this->exts->openUrl($this->accountUrl);
                sleep(20);
            }

            sleep(2);
            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");


                $this->invoicePage();

                // Final, check no invoice
                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }

                $this->exts->success();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        } else {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(5);
            $this->invoicePage();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            $this->exts->moveToElementAndClick('header #headerContainer');
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);
                $this->exts->capture("1-login-filled");

                $this->exts->waitTillPresent($this->submit_button_selector, 20);
                if ($this->exts->exists($this->submit_button_selector)) {
                    $this->exts->click_element($this->submit_button_selector);
                }
                sleep(10);
                $this->exts->type_key_by_xdotool('Return');
                $this->exts->capture("1-login-after-submit");
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */


    private function waitForSelectors($selector, $max_attempt, $sec)
    {
        for (
            $wait = 0;
            $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for Selectors!!!!!!');
            sleep($sec);
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            sleep(15);
            $this->waitForSelectors($this->check_login_success_selector, 10, 2);
            sleep(2);
            if ($this->exts->exists($this->check_login_success_selector)) {
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
        $this->exts->log("Invoice page");
        $this->exts->openUrl($this->baseUrl);

        $this->exts->waitTillPresent('header button#headerContainer', 20);
        $this->exts->moveToElementAndClick('header button#headerContainer');
        sleep(5);

        $this->exts->moveToElementAndClick('a[href*="/tools/my-account/meine-bestellungen"]');
        sleep(15);

        $this->exts->waitTillPresent('input[role="combobox"][value*="Tage"]');

        $this->clickByJS('input[role="combobox"][value*="Tage"]');
        sleep(5);

        $select_dates = $this->exts->getElements('div#formSelect-undefined > div');
        if (count($select_dates) === 0) {
            $this->exts->log("No elements found using selector 'div#formSelect-undefined > div'");
            return;
        }


        $this->exts->log('Restrict Pages: ' . $this->restrictPages);

        $this->restrictDate = $this->restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $this->dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $this->restrictDate);

        $this->maxInvoices = 10;
        $this->invoiceCount = 0;


        foreach ($select_dates as $key => $element) {

            if ($key === 0) {
                continue;
            }

            if ($this->terminateLoop) {
                break;
            }
            if (count($select_dates) >= 3) {
                // started from year if exists
                $selector = 'div#formSelect-undefined > div:nth-child(' . ($key + 3) . ')';
            } else {
                $selector = 'div#formSelect-undefined > div:nth-child(' . ($key + 1) . ')';
            }

            $this->exts->waitTillPresent($selector);

            if ($this->exts->exists($selector)) {

                $text = trim($this->exts->querySelector($selector)->getText());
                $this->exts->log("Processing element #{$key} with text: '{$text}'");

                if ($this->restrictPages != 0) {
                    $this->clickByJS($selector);
                    sleep(5);

                    $this->processInvoices();
                    sleep(7);
                } else {
                    if ($key === 1) {
                        continue;
                    }

                    if ($key < 4) {
                        $this->clickByJS($selector);
                        sleep(5);
                        $this->processInvoices();
                        sleep(7);
                    }
                }
            }
        }

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('.flex-wrap.items-end', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('.flex-wrap.items-end');

        foreach ($rows as $row) {

            $tags = $this->exts->getElements('.items-start', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="bestelldetails"]', $row) != null) {

                $this->invoiceCount++;

                $invoiceUrl = $this->exts->getElement('a[href*="bestelldetails"]', $row)->getAttribute('href');
                $invoiceName = trim(end(explode(PHP_EOL, $tags[1]->getAttribute('innerText'))));
                $invoiceDate = trim(end(explode(PHP_EOL, $tags[0]->getAttribute('innerText'))));;
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', trim(end(explode(PHP_EOL, $tags[3]->getAttribute('innerText')))))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));


                $lastDate = !empty($invoiceDate) && $invoiceDate <= $this->restrictDate;

                if ($this->restrictPages != 0 && ($this->invoiceCount == $this->maxInvoices || ($this->dateRestriction && $lastDate))) {
                    $this->terminateLoop = true;
                    break;
                } else if ($this->restrictPages == 0 && $this->dateRestriction && $lastDate) {
                    $this->terminateLoop = true;
                    break;
                }
            }
        }

        foreach ($invoices as $invoice) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(2);

            $download_button = $this->exts->getElementByText('.flex-wrap .items-start button', 'Rechnungs PDF', null, false);

            if ($download_button != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $invoiceFileName = $invoice['invoiceName'] . '.pdf';

                $this->exts->click_element($download_button);
                sleep(1);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->isNoInvoice = false;
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        if (!$this->terminateLoop) {
            $this->exts->openUrl($this->baseUrl);

            $this->exts->waitTillPresent('header button#headerContainer', 20);

            $this->exts->moveToElementAndClick('header button#headerContainer');
            sleep(5);

            $this->exts->moveToElementAndClick('a[href*="/tools/my-account/meine-bestellungen"]');
            sleep(15);

            $this->exts->waitTillPresent('input[role="combobox"][value*="Tage"]');

            $this->clickByJS('input[role="combobox"][value*="Tage"]');
            sleep(5);
        }
    }

    private function clickByJS($selector)
    {
        $this->exts->execute_javascript("
	
			var element = document.querySelector('" . $selector . "');
			
			var rect = element.getBoundingClientRect();
			
			element.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, clientX: rect.x, clientY: rect.y }));
			element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, clientX: rect.x, clientY: rect.y }));
			element.dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: rect.x, clientY: rect.y }));
			
		");
    }

    private function disableExtension()
    {
        $this->exts->log('Disabling Accept all cookies extension!');
        $this->exts->openUrl('chrome://extensions/?id=ncmbalenomcmiejdkofaklpmnnmgmpdk');

        $this->exts->waitTillPresent('extensions-manager', 15);
        if ($this->exts->exists('extensions-manager')) {
            $this->exts->execute_javascript("
			var button = document
					    .querySelector('extensions-manager')
					    ?.shadowRoot?.querySelector('extensions-detail-view')
					    ?.shadowRoot?.querySelector('cr-toggle') || null;
						  
			if (button) {
				button.click();
			}
		");
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'CyberPort', '2673519', 'bWVkaWFAYXJ0ZW5naXMuY29t', 'dHRuVmRiM3czanRhM0xl');
$portal->run();
