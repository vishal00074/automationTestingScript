<?php // replace exists to isExists function added code to open url before loadCookiesFromFile and after updated login code add captcha code after filling form 
// and befor submitting added code to trigger loginfailedConfimed  in login form
// added code to disabled extension

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

    // Server-Portal-ID: 124044 - Last modified: 27.08.2025 13:33:33 UTC - User: 1

    // Start Script 

    public $baseUrl = 'https://jlcpcb.com/';
    public $loginUrl = 'https://jlcpcb.com/';
    public $invoicePageUrl = 'https://jlcpcb.com/user-center/orders/';
    public $username_selector = 'form.public-input-form input[type="text"]';
    public $password_selector = 'form.public-input-form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = "form.public-input-form button[type='submit']";
    public $check_login_failed_selector = 'el-message--error';
    public $check_login_success_selector = ".//span[text()='Sign out']/ancestor::div[1]";
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->disable_extensions();
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->waitForSelectors("button#cookiesAcceptBtn", 5, 2);
            if ($this->isExists('button#cookiesAcceptBtn')) {
                $this->exts->click_element('button#cookiesAcceptBtn');
                sleep(5);
            }

            $this->waitForSelectors('div[id="home_sign in"]', 5, 2);
            if ($this->isExists('div[id="home_sign in"]')) {
                $this->exts->click_element('div[id="home_sign in"]');
                sleep(5);
            }

            if ($this->exts->queryXpath(".//button[contains(@class,'el-button--primary') and span[normalize-space(.)='Sign in']]") != null) {
                $this->exts->click_element(".//button[contains(@class,'el-button--primary') and span[normalize-space(.)='Sign in']]");
                sleep(10);
            }

            sleep(5);
            $this->fillForm(0);
            sleep(5);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitForSelectors($this->username_selector, 5, 2);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->isExists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                if ($this->isExists('iframe[title="reCAPTCHA"]')) {
                    $this->exts->click_element('iframe[title="reCAPTCHA"]');
                    sleep(5);
                }
                $this->check_solve_challenge();

                if ($this->isExists($this->submit_login_selector)) {
                    $this->exts->log("click the login button ------> 1");
                    $this->exts->click_element($this->submit_login_selector);
                    sleep(1);
                }

                $error_text = $this->exts->extract($this->check_login_failed_selector);
                sleep(1);
                $error_text1 = $this->exts->extract($this->check_login_failed_selector);
                sleep(1);
                $error_text2 = $this->exts->extract($this->check_login_failed_selector);
                $this->exts->log('error_text:: ' . $error_text);
                $this->exts->log('error_text1:: ' . $error_text1);
                $this->exts->log('error_text2:: ' . $error_text2);

                if (
                    stripos(strtolower($error_text), 'passwor') !== false ||
                    stripos(strtolower($error_text1), 'passwor') !== false ||
                    stripos(strtolower($error_text2), 'passwor') !== false
                ) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
                }
                sleep(5);

                if ($this->isExists($this->submit_login_selector)) {
                    $submitBtn = $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(5);
                    $this->exts->log("click the login button ------> 2");
                    $this->exts->execute_javascript('arguments[0].click();', [$submitBtn]);
                    sleep(1);
                }
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

        $captcha_iframe_selector = 'div[style*="visibility: visible;"] div iframe[title*="recaptcha"]';

        $this->waitForSelectors($captcha_iframe_selector, 3, 10);

        if ($this->isExists($captcha_iframe_selector)) {

            $this->exts->log(">>>>>>>>>>>>>> recaptcha");

            $this->exts->capture("google-captcha");

            $captcha_instruction = '';

            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);

            $captcha_wraper_selector = $captcha_iframe_selector;

            $this->exts->switchToDefault();
            sleep(2);

            if ($this->isExists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);

                if ($coordinates != '') {

                    foreach ($coordinates as $coordinate) {
                        $this->click_captcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->switchToDefault();
                    sleep(2);

                    $this->exts->capture("google-captcha-selected " . $count);

                    if ($this->isExists($captcha_iframe_selector)) {
                        $this->exts->log("Clicking next button!!!");
                        $iframe = $this->exts->makeFrameExecutable($captcha_iframe_selector);
                        $submitBtn = $iframe->querySelector('button#recaptcha-verify-button');
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

    private function waitForSelectors($selector, $max_attempt, $sec)
    {
        for (
            $wait = 0;
            $wait < $max_attempt && $this->exts->execute_javascript("return !!document.querySelector(\"" . $selector . "\");") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for Selectors!!!!!!');
            sleep($sec);
        }
    }

    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/');
        sleep(2);
        $this->exts->execute_javascript("
            let manager = document.querySelector('extensions-manager');
            if (manager && manager.shadowRoot) {
                let itemList = manager.shadowRoot.querySelector('extensions-item-list');
                if (itemList && itemList.shadowRoot) {
                    let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                    items.forEach(item => {
                        let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                        if (toggle) toggle.click();
                    });
                }
            }
        ");
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
            for ($i = 0; $i < 15 && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                sleep(1);
            }
            if ($this->exts->getElement($this->check_login_success_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function processInvoices()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 15;
        $invoiceCount = 0;


        $this->waitForSelectors("div#userCenterIndexOrderList div.list-item", 20, 2);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div#userCenterIndexOrderList div.list-item');

        foreach ($rows as $row) {

            if ($this->exts->querySelector('div.batch-operation-col a[target="_blank"]', $row) != null) {

                $invoiceCount++;

                $invoiceUrl = $this->exts->querySelector('div.batch-operation-col a[target="_blank"]', $row)->getAttribute('href');
                $invoiceName = '';
                $invoiceAmount = '';
                $invoiceDate = $this->exts->extract('div.thead > div:first-child > span:first-child', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;


                $parsedInvoiceDate = $this->exts->parse_date($invoiceDate, 'Y-m-d H:i:s', 'Y-m-d');
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
            $this->exts->openUrl($invoice['invoiceUrl']);
            // $this->exts->waitTillPresent('table tbody tr', 20);
            for ($i = 0; $i < 20 && $this->exts->getElement('table tbody tr') == null; $i++) {
                sleep(1);
            }
            $invoice['invoiceName'] = $this->exts->extract('div.info-list p:first-child span:last-child');
            $invoice['invoiceAmount'] = $this->exts->extract('div.price-list div:last-child span:last-child');
            $invoice['invoiceDate'] = $this->exts->extract('div.info-list p:nth-child(2) span:last-child');

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'y/m/d', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            for ($i = 0; $i < 20 && $this->exts->getElement('div.print-hide span:first-child') == null; $i++) {
                sleep(1);
            }

            $downloaded_file = $this->exts->click_and_download('div.print-hide span:first-child', 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");


$portal = new PortalScriptCDP("optimized-chrome-v2", 'JLCPCB', '2673809', 'YWNjb3VudHNAaWZwLXNvZnR3YXJlLmRl', 'cGVmem81LXhpYndpYy16VWR2YW0=');
$portal->run();
