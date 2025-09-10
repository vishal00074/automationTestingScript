<?php

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

    // Server-Portal-ID: 23462 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    public $baseUrl = 'https://travipay.com/';
    public $loginUrl = 'https://account.travipay.com/en/login';
    public $invoicePageUrl = 'https://account.travipay.com/de/transactions';

    public $username_selector = 'form#loginform input[formcontrolname="username"]';
    public $password_selector = 'form#loginform input[formcontrolname="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#loginform button.btn.btn-pc-accent.btn-lg';

    public $check_login_failed_selector = 'form#loginform input[formcontrolname="password"]';
    public $check_login_success_selector = 'header a .fa-power-off';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);

        $this->exts->moveToElementAndClick('div.cookie-box a[class*="btn-accept"]');
        sleep(15);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button[routerlink="/private/login-email"]')) {
                $this->exts->moveToElementAndClick('button[routerlink="/private/login-email"]');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(20);
        }

        if (strpos($this->exts->getUrl(), '/toc_privacy_updated') !== false && $this->exts->exists('div.card-body button.btn-pc-accent')) {
            $this->scroll_down_div_lazy_load('div.card-body h1', 100, 100);
            $this->exts->moveToElementAndClick('div.card-body button.btn-pc-accent');
            sleep(5);
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->processAfterLogin();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed url: ' . $this->exts->getUrl());
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) == null && $this->exts->getElement('#menu li a[href*="/login"]') != null) {
            $this->exts->moveToElementAndClick('#menu li a[href*="/login"]');
            sleep(15);
        }

        $this->exts->moveToElementAndClick('[routerlink="/login-email"]');
        sleep(5);
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
            $this->exts->moveToElementAndClick($this->submit_login_selector);

            $check_login_failed_selector = $this->check_login_failed_selector;
            $driver = $this->exts->webdriver;
            if ($this->waitFor(
                function () use ($driver, $check_login_failed_selector) {
                    return count($driver->findElements(WebDriverBy::cssSelector($check_login_failed_selector))) > 0;
                },
                7
            )) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    /**
     * Scroll on a div by sending key downs multiple times
     * @param (element or string) $element_to_focus the element to do scroll down on, or at least have to click on it to focus, then do scroll down/key down.
     * @param (int) $times_to_press_key_down_each_loop times to press key down each time from $times_to_repeat; this should approximately equal or less than (1, 2) the number of elements are visible on DOM.
     * @param (int) $times_to_repeat number of times to repeat this process.
     * @param (int) $delay_each_loop sleep in miliseconds.
     * @param (string) $element_selector_to_search element selector to get/return when scroll (optional).
     * @param (string) $attribute_to_get attribute of the element to return (default innertext), optional.
     * @return array of elements if $element_selector_to_search is defined
     */
    private function scroll_down_div_lazy_load($element_to_focus, $times_to_press_key_down_each_loop = 10, $times_to_repeat = 10, $delay_each_loop = 300, $element_selector_to_search = '', $attribute_to_get = 'innerText')
    {
        $returned_elements = array();
        try {

            if ($element_to_focus == null) {
                $this->exts->log(_FUNCTION_ . ' Can not click null');
                return;
            }

            if (!empty($element_selector_to_search) && empty($attribute_to_get)) {
                $this->exts->log(_FUNCTION_ . ' $element_selector_to_search must not empty!');
                return;
            }

            $this->exts->capture(_FUNCTION_ . '--before_scroll');

            $element = $element_to_focus;
            if (is_string($element_to_focus)) {
                $this->exts->log(_FUNCTION_ . '::Click selector: ' . $element_to_focus);
                $element = $this->exts->getElement($element_to_focus);
                if ($element == null) {
                    $element = $this->exts->getElement($element_to_focus, null, 'xpath');
                }
                if ($element == null) {
                    $this->exts->log(_FUNCTION_ . ':: Can not found element with selector/xpath: ' . $element_to_focus);
                }
            }
            if ($element != null) {
                try {
                    $this->exts->log(_FUNCTION_ . ' trigger click.');
                    $element->click();
                } catch (\Exception $exception) {
                    $this->exts->log(_FUNCTION_ . ' by javascript' . $exception);
                    $this->exts->executeSafeScript("arguments[0].click()", [$element]);
                }
            }
            sleep(3);

            for ($k = 0; $k < $times_to_repeat; $k++) {
                $this->exts->log(_FUNCTION_ . '--looping--' . $k . '....' . count($returned_elements));

                if (!empty($element_selector_to_search)) {
                    $rows = $this->exts->webdriver->findElements(WebDriverBy::cssSelector($element_selector_to_search));
                    $rows_count = count($rows);
                    for ($j = 0; $j < $rows_count; $j++) {
                        $row = $this->exts->webdriver->findElements(WebDriverBy::cssSelector($element_selector_to_search))[$j];
                        if ($row == null) continue;
                        elseif (!in_array(trim($row->getAttribute($attribute_to_get)), $returned_elements)) {
                            array_push($returned_elements, trim($row->getAttribute($attribute_to_get)));
                        }
                    }
                }

                // should minus 2 to offset top+bottom elements: $times_to_press_key_down_each_loop-2
                for ($i = 0; $i < $times_to_press_key_down_each_loop; $i++) {
                    $this->exts->webdriver->getKeyboard()->pressKey(WebDriverKeys::ARROW_DOWN);
                }

                // wait for the dom finish changing elements
                if ($delay_each_loop > 0) {
                    usleep($delay_each_loop);
                }
            }

            $this->exts->capture(_FUNCTION_ . '--after_scroll');

            if (!empty($element_selector_to_search)) {
                $this->exts->log('returning elements');
                $this->exts->log('========================================================');
                $this->exts->log(count($returned_elements));
                foreach ($returned_elements as $key => $value) {
                    $this->exts->log($value);
                }
                $this->exts->log('========================================================');
                return $returned_elements;
            }
        } catch (\Exception $exception) {
            $this->exts->capture(_FUNCTION_ . '_exception');
            $this->exts->log(_FUNCTION_ . ' WARINIG ERROR!!! CANNOT SCROLL DOWN BY SENDING KEY_DOWN!!!');
            $this->exts->log(_FUNCTION_ . ' Exception detail:::' . $exception->getMessage());

            if (count($returned_elements) > 0) return $returned_elements;
        }
    }

    private function waitFor($func_or_ec, $timeout_in_second = 15, $interval_in_millisecond = 500)
    {
        $this->exts->log('Waiting for condition...');
        try {
            $this->exts->webdriver->wait($timeout_in_second, $interval_in_millisecond)->until($func_or_ec);
            return true;
        } catch (\Exception $exception) {
            // $this->exts->log($exception);
            return false;
        }
    }

    private function processAfterLogin()
    {
        sleep(5);
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log('User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            if ($this->exts->exists('a[href="/en/fleet/transactions"], a[href="/en/private/transactions"]')) {
                $this->exts->moveToElementAndClick('a[href="/en/fleet/transactions"], a[href="/en/private/transactions"]');
                sleep(30);
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(15);
            }

            // check multi account
            $numbers = $this->exts->getElements('select#msisdn option:not([msisdn=""])');
            array_walk($numbers, function (&$element) {
                $element = $element->getAttribute('value');
            });
            $this->exts->log('Num Of phone numbers ' . count($numbers));
            if (count($numbers) > 1) {
                $this->exts->log('Multi numbers');
                foreach ($numbers as $number_selector) {
                    sleep(3);
                    $this->exts->log('Processing number: ' . $number_selector);
                    // $this->exts->selectDropdownByValue($this->exts->getElement('select#msisdn'), $number_selector);
                    $this->exts->execute_javascript('let selectBox = document.querySelector("select#msisdn");
                    selectBox.value = ' . $number_selector . ';
                    selectBox.dispatchEvent(new Event("change"));');
                    sleep(2);
                    if ($this->exts->getElement('select#period') != null) {
                        // $this->exts->selectDropdownByValue($this->exts->getElement('select#period'), 'forever');
                        $this->exts->execute_javascript('let selectBox = document.querySelector("select#period");
                        selectBox.value = "forever";
                        selectBox.dispatchEvent(new Event("change"));');
                        sleep(2);
                    }
                    $this->exts->moveToElementAndClick('button[type="submit"]');
                    sleep(25);
                    $this->processInvoices();
                }
            } else {
                if ($this->exts->getElement('select#period') != null) {
                    // $this->exts->selectDropdownByValue($this->exts->getElement('select#period'), '3months');
                    $this->exts->execute_javascript('let selectBox = document.querySelector("select#period");
                    selectBox.value = "3months";
                    selectBox.dispatchEvent(new Event("change"));');
                    sleep(2);
                }
                $this->exts->moveToElementAndClick('button[type="submit"]');
                sleep(25);
                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log('Timeout waitForLogin');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices()
    {
        // sleep(15);
        for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement('a.btn-pc-accent .mdi-printer, a.btn i.mdi-download') == null; $wait_count++) {
            $this->exts->log('Waiting for invoice...');
            sleep(5);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 8 && $this->exts->getElement('a.btn-pc-accent .mdi-printer, a.btn i.mdi-download', end($tags)) != null) {
                if ($this->exts->exists('a.btn-pc-accent .mdi-printer')) {
                    $download_button = $this->exts->getElement('a.btn-pc-accent .mdi-printer', end($tags));
                } else {
                    $download_button = $this->exts->getElement('a.btn i.mdi-download', end($tags));
                }
                $invoiceName = trim($tags[8]->getText());
                $invoiceDate = trim($tags[0]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getText())) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $invoiceFileName = $invoiceName . '.pdf';
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }

                $this->isNoInvoice = false;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
