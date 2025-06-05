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

    // Server-Portal-ID: 97926 - Last modified: 01.05.2025 14:16:25 UTC - User: 1

    /*start script*/

    public $loginUrl = 'https://stamped.io/account/signin';
    public $invoicePageUrl = 'https://go.stamped.io/v3/';
    public $username_selector = 'input[ng-model="user.username"] , input[name="username"], input#EmailInput';
    public $password_selector = 'input[ng-model="user.password"] , input[name="password"]';
    public $remember_me_selector = 'label[for="remember"] input[type="checkbox"]';
    public $submit_login_selector = 'form.signinForm  button[type="submit"]';
    public $check_login_failed_selector = 'input[ng-model="user.password"], input[name="password"]';
    public $check_login_success_selector = 'li[class*="style__StyledListItem"] a[href="https://go.stamped.io/account/plan"],[ng-click*="logout"], p.Polaris-TopBar-UserMenu__Name';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->loginUrl);
        sleep(1);

        // Load cookies
        // $this->exts->loadCookiesFromFile();
        $this->disable_extensions();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            sleep(10);
            $this->handleLogin();
            sleep(4);
        }

        if ($this->exts->exists('p.Polaris-TopBar-UserMenu__Name')) {
            $this->exts->moveToElementAndClick('p.Polaris-TopBar-UserMenu__Name');
            sleep(1);
        }

        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(10);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);

            if ($this->exts->querySelector('a[href*="/settings/plans"]')) {
                $this->exts->moveToElementAndClick('a[href*="/settings/plans"]');
                sleep(15);
                $this->exts->moveToElementAndClick('[href="#/settings/billing"]');
                sleep(15);
            } else {
                $this->exts->openUrl('https://go.stamped.io/v3/#/settings/billing');
            }
            $this->processInvoices();

            // page has changed 30.9.2021
            if ($this->isNoInvoice) {
                $this->processInvoicesSep();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
        }
    }

    private function handleLogin()
    {
        $this->exts->log('Attempting login...');
        $this->checkFillLogin();
        sleep(4);
        $this->exts->type_key_by_xdotool('Return');
        sleep(30);
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(10);
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->log('Not found the selector --------------->');
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username ");
            $this->exts->click_element($this->username_selector);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);
            if (!$this->isValidEmail($this->username)) {
                $this->exts->loginFailure(1);
            }

            $this->exts->log("Enter Password");
            $this->exts->click_element($this->password_selector);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);

            sleep(2);
            if ($this->exts->exists('div.recaptcha[data-action="LOGIN"]')) {
                $this->exts->click_element('div.recaptcha[data-action="LOGIN"]');
            }
            sleep(1);
            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 30; $i++) {
                    if ($is_captcha == false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
            sleep(10);
            $alertBox = $this->exts->evaluate('
			let alertDisplayed = false;
			// Override alert function
			window.alert = function (message) {
				if (message.toLowerCase().includes("incorrect")) {
					alertDisplayed = true;
					return alertDisplayed;
				}
				console.log("Alert triggered:", message);
			};
		');
            $this->exts->log("print msg ----->" . $alertBox);

            sleep(15);
            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->log("Clicking submit button");
                $this->exts->click_element($this->submit_login_selector);
                sleep(5);
            } else {
                $this->exts->log("Submit button not found");
            }
            sleep(5);
            $login_button = $this->exts->querySelector($this->submit_login_selector);
            if ($login_button != null) {
                $this->exts->execute_javascript("arguments[0].click();", arguments: [$login_button]);
            }
            if ($alertBox) {
                $this->exts->log('Incorrect username password');
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    function isValidEmail($username)
    {

        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (preg_match($emailPattern, $username)) {
            return 'email';
        }
        return false;
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $this->exts->waitTillPresent('iframe[title="recaptcha challenge expires in two minutes"]', 30);
        $language_code = '';

        if ($this->exts->exists('iframe[title="recaptcha challenge expires in two minutes"]')) {
            $this->exts->capture("checkdomain-captcha");

            if (!$this->exts->exists('div[style*="visibility: visible;"] iframe')) {
                $this->exts->click_by_xdotool('iframe[title="recaptcha challenge expires in two minutes"]');
                sleep(10);
            }
            if ($this->exts->exists('div[style*="visibility: visible;"] iframe')) {
                $captcha_instruction = $this->exts->makeFrameExecutable('div[style*="visibility: visible;"] iframe')->extract('.rc-imageselect-desc-no-canonical');
                if (trim($captcha_instruction) == '') {
                    $captcha_instruction = $this->exts->makeFrameExecutable('div[style*="visibility: visible;"] iframe')->extract('.rc-imageselect-desc');
                }

                $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
                sleep(5);
                $captcha_wraper_selector = 'div[style*="visibility: visible;"] iframe';

                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);

                if ($coordinates != '') {
                    $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->click_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->capture("checkdomain-captcha-selected " . $count);
                    if ($this->exts->exists('div[style*="visibility: visible;"] iframe')) {

                        $this->exts->makeFrameExecutable('div[style*="visibility: visible;"] iframe')->click_element('button[id="recaptcha-verify-button"]');
                    }
                    return true;
                }
            }
            return false;
        }
    }

    private function click_point($selector = '', $x_on_element = 0, $y_on_element = 0)
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

    private function processInvoices()
    {
        sleep(25);
        $this->exts->log('-------------------------');
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->querySelectorAll('.Polaris-Card table.Polaris-DataTable__Table > tbody > tr'));
        $this->exts->log($rows);
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('.Polaris-Card table.Polaris-DataTable__Table > tbody > tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('span[ng-click*="viewInvoice"]', $tags[3]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('span[ng-click*="viewInvoice"]', $tags[3]);
                $invoiceName = trim(preg_replace('/[, ]/', '', $tags[0]->getAttribute('innerText')));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' USD';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
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
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(8);
                    if ($this->exts->exists('.modal-content embed[type="application/pdf"]')) {
                        $pdf_content = $this->exts->extract('div.modal-dialog embed', null, 'src');
                        $parts = explode('base64,', $pdf_content);
                        $pdf_content = array_pop($parts);
                        $pdf_content = base64_decode($pdf_content);
                        if (strpos($pdf_content, '%PDF') !== false) {
                            file_put_contents($this->exts->config_array['download_folder'] . $invoiceFileName, $pdf_content);
                            if (file_exists($this->exts->config_array['download_folder'] . $invoiceFileName)) {
                                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                                sleep(1);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        } else {
                            $this->exts->log('pdf invalid' . $invoiceName);
                        }
                    }
                    $this->exts->refresh();
                    sleep(15);
                }
            }
        }
    }

    private function processInvoicesSep()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page-sep");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('.Polaris-Card table.Polaris-DataTable__Table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('a[href*="/invoice"]', $tags[3]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice"]', $tags[3])->getAttribute("href");
                $invoiceName = trim(preg_replace('/[, ]/', '', $tags[0]->getAttribute('innerText')));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' USD';

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
        $invoiceCount = 1;
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            $invoice['invoiceName'] = $this->exts->extract('//span[text()="Invoice number"]/../following-sibling::td/span');
            $this->exts->log('-------------' . $invoiceCount . '-------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . $invoiceCount . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            sleep(8);
            if (!$this->exts->exists('div.DesktopInvoiceDetailsDownloadRow-Container button, .InvoiceDetailsRow-Container button')) {
                sleep(5);
            }

            $this->exts->click_element('//span[text()="Download invoice"]');

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $invoiceCount++;
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
