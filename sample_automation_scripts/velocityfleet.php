<?php // optimize  and updated login code

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

    // Server-Portal-ID: 2128515 - Last modified: 26.06.2025 14:37:48 UTC - User: 1

    public $baseUrl = 'https://www.velocityfleet.com/index';
    public $loginUrl = 'https://www.velocityfleet.com/en-us/accounts/login';
    public $invoicePageUrl = 'https://www.velocityfleet.com/app/invoices/list/all-invoices';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button.login-panel__submit';

    public $check_login_failed_selector = 'ul.errorlist';
    public $check_login_success_selector = 'a[href="/accounts/logout/"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->fillForm(0);

            $this->check_solve_challenge();
            sleep(10);
            if ($this->exts->querySelector('a[href="/mfa/enter-code-mfa-email/"]') != null) {
                $this->exts->moveToElementAndClick('a[href="/mfa/enter-code-mfa-email/"]');
                sleep(10);
            }

            $this->checkFillTwoFactor();
            sleep(7);
            if ($this->exts->querySelector('div[class="contactus-field-container contactus-field-error-container"] p')) {
                $this->exts->moveToElementAndClick('a[id="resend_code_btn"]');
                sleep(10);
                $this->checkFillTwoFactor();
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
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
            } else if ($this->exts->exists('div[class="contactus-field-container contactus-field-error-container"] p')) {
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
        $this->exts->waitTillPresent($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->capture("1-login-page-filled");
                sleep(5);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
                sleep(10);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#id_code';
        $two_factor_message_selector = '';
        $two_factor_submit_selector = 'button.radiusBtn';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                sleep(10);
                if ($this->exts->exists('div[class*="errorMessage"]')) {

                    $this->exts->capture("wrong 2FA code error-" . $this->exts->two_factor_attempts);
                    $this->exts->log('The code you entered is incorrect. Please try again.');
                }

                if ($this->exts->querySelector($two_factor_selector) == null) {
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
            if ($this->exts->exists($this->check_login_success_selector) && !$this->exts->urlContains('mfa/enter-code-mfa-email/')) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table[data-qa-id="table__select_customer"], div.invoice-table tbody tr', 30);

        $accountCount = 0;
        $accountSelectionPage = false;

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        if ($this->exts->exists('div.invoice-table tbody tr')) {
            $accountCount = 1;
        } elseif ($this->exts->exists('table[data-qa-id="table__select_customer"]')) {
            $accountCount = count($this->exts->querySelectorAll('table[data-qa-id="table__select_customer"] tbody tr'));
            $accountSelectionPage = true;
        }

        for ($i = 1; $i <= $accountCount; $i++) {

            if ($accountSelectionPage) {
                $this->exts->openUrl('https://www.velocityfleet.com/selectSessionCustomer');

                $this->exts->waitTillPresent('table[data-qa-id="table__select_customer"]', 30);

                $this->exts->click_element('table[data-qa-id="table__select_customer"] tbody tr:nth-child(' . $i . ')');

                sleep(5);

                $this->exts->openUrl($this->invoicePageUrl);
            }

            do {

                $this->exts->waitTillPresent('div.invoice-table tbody tr', 30);

                $rows = $this->exts->querySelectorAll('div.invoice-table tbody tr');

                foreach ($rows as $row) {
                    if ($this->exts->querySelector('td input#download-checkbox', $row) != null) {

                        $invoiceUrl = '';

                        $invoiceName = $this->exts->extract('td:nth-child(4)', $row);
                        $explodeName = explode('Invoice Number', $invoiceName);
                        $invoiceName = !empty($explodeName[0]) ? trim($explodeName[0]) : '';

                        $invoiceAmount = $this->exts->extract('td:nth-child(2)', $row);
                        $explodeAmount = explode('Invoice Amount', $invoiceAmount);
                        $invoiceAmount = !empty($explodeAmount[0]) ? trim($explodeAmount[0]) : '';

                        $invoiceDate = $this->exts->extract('td:nth-child(5)', $row);
                        $explodeDate = explode('Invoice Date', $invoiceDate);
                        $invoiceDate = !empty($explodeDate[0]) ? trim($explodeDate[0]) : '';

                        $checkBox = $this->exts->querySelector('td input#download-checkbox', $row);
                        $this->exts->execute_javascript("arguments[0].click();", [$checkBox]);

                        $downloadBtn = $this->exts->querySelector('button.undefined');

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

                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd/m/Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $invoiceDate);

                        $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                        sleep(3);

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

                        // click modal close button after download
                        $this->exts->click_element('button.undefined');
                        sleep(1);

                        //click to uncheck the invoice
                        $checkBox = $this->exts->querySelector('td input#download-checkbox', $row);
                        $this->exts->execute_javascript("arguments[0].click();", [$checkBox]);
                        sleep(1);

                        $this->exts->log(' ');
                        $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                        $this->exts->log(' ');
                    }
                }

                // pagination handle
                if ($this->exts->exists('button.table-pagination__btn--next:not([disabled])')) {
                    $this->exts->log('Click Next Page in Pagination!');
                    $this->exts->click_element('button.table-pagination__btn--next:not([disabled])');
                    sleep(5);
                } else {
                    $this->exts->log('Last Page!');
                    break;
                }
            } while (true);
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }

    private function check_solve_challenge()
    {
        $is_captcha = $this->solve_captcha_by_clicking(0);
        if ($is_captcha) {
            for ($i = 1; $i < 30; $i++) {
                if ($is_captcha == false) {
                    break;
                }
                $is_captcha = $this->solve_captcha_by_clicking($i);
            }
        }
    }

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $language_code = '';

        $this->exts->switchToDefault();

        $captcha_iframe_selector = 'div[style*="visibility: visible;"] div iframe[src*="hcaptcha"]';

        $this->exts->waitTillPresent($captcha_iframe_selector, 30);

        if ($this->exts->exists($captcha_iframe_selector)) {

            $this->exts->log(">>>>>>>>>>>>>> hcaptcha");

            $this->exts->capture("velocity-fleet-captcha");

            $captcha_instruction = '';

            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);

            $captcha_wraper_selector = $captcha_iframe_selector;

            $this->exts->switchToDefault();
            sleep(2);

            if ($this->exts->exists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);

                if ($coordinates != '') {

                    foreach ($coordinates as $coordinate) {
                        $this->click_captcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->switchToDefault();
                    sleep(2);

                    $this->exts->capture("artstation-captcha-selected " . $count);

                    if ($this->exts->exists($captcha_iframe_selector)) {
                        $this->exts->log("Clicking next button!!!");
                        $iframe = $this->exts->makeFrameExecutable($captcha_iframe_selector);
                        $submitBtn = $iframe->querySelector('div.button-submit');
                        $iframe->execute_javascript("arguments[0].click();", [$submitBtn]);
                    } else {
                        $this->exts->log("-----Captcha submit button not found!!!-----");
                    }

                    sleep(5);
                    $this->exts->switchToDefault();
                    return true;
                }
            }
            $this->exts->switchToDefault();
            return false;
        }
    }

    private function getCoordinates($captcha_image_selector, $instruction = '', $lang_code = '', $json_result = false, $image_dpi = 75)
    {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
        $response = '';
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }

    private function click_captcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
    {
        $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
        $selector = base64_encode($selector);
        $element_coo = $this->exts->execute_javascript('
		var x_on_element = ' . $x_on_element . '; 
		var y_on_element = ' . $y_on_element . ';
		var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
		// Default get center point in element, if offset inputted, out put them
		if(x_on_element > 0 || y_on_element > 0) {
			Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
		} else {
			Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
		}
		
	');
        // sleep(1);
        $this->exts->log("Browser clicking position: $element_coo");
        $element_coo = explode('|', $element_coo);

        $root_position = $this->exts->get_brower_root_position();
        $this->exts->log("Browser root position");
        $this->exts->log(print_r($root_position, true));

        $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
        $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
        $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        // move randomly
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
