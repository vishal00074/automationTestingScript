<?php //  added check_solve_blocked_page function in login form

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

    // Server-Portal-ID: 148964 - Last modified: 24.01.2025 14:37:13 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.powtoon.com/';
    public $loginUrl = 'https://www.powtoon.com/account/login/';
    public $invoicePageUrl = 'https://www.powtoon.com/account/info/';

    public $username_selector = 'form#login_form input#email_login';
    public $password_selector = 'form#login_form input#password_login';
    public $submit_login_selector = 'form#login_form button#login_button';

    public $check_login_failed_selector = 'form#login_form div.authentication-error label[for="email"]';
    public $check_login_success_selector = 'button[data-id="nav-bar-pricing-btn-crown"], [data-id="main-menu-user-logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
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

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->checkFillLogin();
            sleep(10);
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        if ($this->exts->urlContains('login')) {
            $this->checkFillLogin();
            sleep(5);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }


        if ($this->exts->exists('div[class*="DropDownMenu_menu-con"]') && $this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->moveToElementAndClick('div[class*="DropDownMenu_menu-con"]');
            sleep(5);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            if ($this->exts->exists('a[href*="https://powtoon-secure.recurly.com/account/"]')) {
                $invoiceUrl = $this->exts->getElement('a[href*="https://powtoon-secure.recurly.com/account/"]')->getAttribute("href");
                $this->exts->openUrl($invoiceUrl);
                sleep(10);
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "password don't match") !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(5);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            $this->exts->capture("2-login-page-filled");
            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 5; $i++) {
                    if ($is_captcha == false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);

            if ($this->exts->exists('button[name="next"]')) {
                $this->exts->moveToElementAndClick('button[name="next"]');
                sleep(5);
            }

            if ($this->exts->getElement($this->check_login_failed_selector) == null && $this->exts->getElement($this->password_selector) != null) {
                sleep(5);
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);

                $this->exts->capture("2-login-page-filled-1");

                sleep(5);

                $this->check_solve_blocked_page();

                $is_captcha = $this->solve_captcha_by_clicking(0);
                if ($is_captcha) {
                    for ($i = 1; $i < 5; $i++) {
                        if ($is_captcha == false) {
                            break;
                        }
                        $is_captcha = $this->solve_captcha_by_clicking($i);
                    }
                }
                sleep(5);
                $this->exts->moveToElementAndClick($this->submit_login_selector);

                sleep(5);

                if ($this->exts->exists('button[name="next"]')) {
                    $this->exts->moveToElementAndClick('button[name="next"]');
                    sleep(5);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
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


    // Captcha Code Start Here
    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $language_code = '';
        $unsolved_hcaptcha_submit_selector = 'div[id="RecaptchaLogin"] iframe[src*="/enterprise"]';
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';
        $this->exts->waitTillPresent($unsolved_hcaptcha_submit_selector, 20);
        if ($this->exts->check_exist_by_chromedevtool($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) {
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
                $this->exts->waitTillPresent($hcaptcha_challenger_wraper_selector, 20);
            }
            $this->exts->capture("tesla-captcha");

            $captcha_instruction = '';

            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);
            $captcha_wraper_selector = 'div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]';

            if ($this->exts->exists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


                // if($coordinates == '' || count($coordinates) < 2){
                //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
                // }
                if ($coordinates != '') {
                    // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->click_hcaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->capture("tesla-captcha-selected " . $count);
                    $this->exts->makeFrameExecutable('div[style*="visibility: visible;"]  iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                    sleep(10);
                    return true;
                }
            }

            return false;
        }
    }

    private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
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

    private function getCoordinates(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
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
    /// Captch Code end here







    private function processInvoices()
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/account/invoices"]', $tags[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/account/invoices"]', $tags[5])->getAttribute("href");
                $invoiceName = explode(
                    'invoices/',
                    array_pop(explode('/', $invoiceUrl))
                )[0];
                $invoiceName = trim(preg_replace('/[^\d]/', '', $invoiceName));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
