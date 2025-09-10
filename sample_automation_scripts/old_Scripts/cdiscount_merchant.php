<?php
// Server-Portal-ID: 106126 - Last modified: 21.01.2025 15:12:42 UTC - User: 1

public $baseUrl = 'https://seller.octopia.com';
public $loginUrl = 'https://seller.octopia.com/login';
public $invoicePageUrl = 'https://seller.octopia.com/finance/Invoices';

public $username_selector = 'form#LoginFormId input#Login, input#username';
public $password_selector = 'form#LoginFormId input#Password, input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'form#LoginFormId input[type="submit"], input[name=login]';

public $check_login_failed_selector = 'form#LoginFormId .has-error input#Password';
public $check_login_success_selector = 'a[href="/logoff.html"]';

public $restrictPages = 3;
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    // Load cookies
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    // after load cookies and open base url, check if user logged in
    // Wait for selector that make sure user logged in
    sleep(15);

    $is_captcha = $this->solve_captcha_by_clicking(0);
    if ($is_captcha) {
        for ($i = 1; $i < 30; $i++) {
            if ($is_captcha == false) {
                break;
            }
            $is_captcha = $this->solve_captcha_by_clicking($i);
        }
    }

    $this->checkAndLogin();
}

private function checkAndLogin()
{
    sleep(5);
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, open the login url and wait for login form
    if (!$this->exts->check_exist_by_chromedevtool($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        // $this->clearChrome();
        // $this->exts->webdriver->get($this->baseUrl);
        sleep(5);
        $this->solve_captcha_by_clicking(1);
        $this->solve_captcha_by_clicking(1);
        if ($this->exts->check_exist_by_chromedevtool($this->password_selector)) {
            sleep(3);
            $this->exts->capture_by_chromedevtool("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("ctrl + a");
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("ctrl + a");
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);


            $this->exts->capture_by_chromedevtool("2-login-page-filled");
            $this->solve_captcha_by_clicking(1);
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(20);

            $this->solve_captcha_by_clicking(1);
            $this->solve_captcha_by_clicking(1);
        } else {
            $this->exts->log('Login page not found');
            $this->exts->loginFailure();
        }
    }

    // then check user logged in or not
    // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector($this->check_login_success_selector) == null; $wait_count++) {
    // 	$this->exts->log('Waiting for invoice...');
    // 	sleep(5);
    // }
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log('User logged in');
        $this->exts->capture("3-login-success");

        if ($this->exts->exists('#footer_tc_privacy_button_2')) {
            $this->exts->click_by_xdotool('#footer_tc_privacy_button_2');
            sleep(1);
        }

        $this->exts->openUrl($this->invoicePageUrl);
        sleep(16);
        $this->solve_captcha_by_clicking(1);

        $limit = 3;
        if ((int)@$this->restrictPages == 0) $limit = 12;
        $this->exts->click_by_xdotool('div#date-filter-container input#FilterStartDate');
        sleep(5);
        for ($i = 0; $i < $limit; $i++) {
            $this->exts->click_by_xdotool('div.datepicker div.datepicker-days th.prev');
            sleep(1);
        }

        $this->exts->click_by_xdotool('td.old.day + td.day');
        sleep(2);

        $this->exts->click_by_xdotool('input#apply-filters');
        sleep(25);
        $this->solve_captcha_by_clicking(1);

        $this->processInvoices(1);

        // Open invoices url and download invoice
        // if((int)@$this->restrictPages == 0) {
        //     $lastYearDate = date("d-m-Y",strtotime("-1 year", time()));
        //     //https://seller.cdiscount.com/Invoices/07-08-2018/07-08-2019/All/All/1/?invoiceDateType=InvoiceDate
        //     $this->invoicePageUrl = "https://seller.cdiscount.com/finance/Invoices/".$lastYearDate."/". date("d-m-Y")."/All/All/1/?invoiceDateType=InvoiceDate";
        //     $this->exts->openUrl($this->invoicePageUrl);
        //     $this->processInvoices(1);

        //     if($this->isNoInvoice){
        //         $this->exts->openUrl('https://seller.cdiscount.com/finance/Invoices');
        //         $this->processInvoices(1);
        //     }
        // } else {
        //     $this->exts->openUrl($this->invoicePageUrl);
        //     $this->processInvoices(1);
        // }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    } else {
        $this->exts->log('Timeout waitForLogin');
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('#input-error')), 'invalid username or password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $language_code = '';
    $unsolved_hcaptcha_submit_selector = 'div.g-recaptcha iframe';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]';
    $this->exts->waitTillAnyPresent([$unsolved_hcaptcha_submit_selector, $hcaptcha_challenger_wraper_selector], 20);
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
        $captcha_wraper_selector = 'div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]';

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
                $this->exts->makeFrameExecutable('div[style*="visible"] iframe[src*="hcaptcha"][title*="hallenge"]')->click_element('div.button-submit');
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

private function processInvoices($page = 1)
{
    sleep(25);
    $this->exts->capture("4-invoices-page");

    if ($this->exts->exists('#footer_tc_privacy_button_2')) {
        $this->exts->click_by_xdotool('#footer_tc_privacy_button_2');
        sleep(1);
    }

    $invoices = [];

    $rows = $this->exts->querySelectorAll('table > tbody > tr');
    $invoicesDownloadSuccess = 0;
    // foreach ($rows as $row) {
    for ($i = 0; $i < count($rows); $i++) {
        $tags = $this->exts->querySelectorAll('td', $rows[$i]);
        if (count($tags) >= 7 && $this->exts->querySelector('button.download-invoice', $tags[5]) != null) {

            $download_button = $this->exts->querySelector('button.download-invoice', $tags[5]);
            $invoiceName = trim($tags[2]->getAttribute('innerText'));
            $invoiceFileName = !empty(trim($invoiceName)) ? $invoiceName . '.pdf' : '';
            $invoiceDate = trim($tags[0]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';


            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $parsed_date);

            if (empty(trim($invoiceName))) continue;

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
                sleep(20);
                //Check if click button download error
                $modalDownloadError = 'div[aria-labelledby="idModalTitle"] .modal-footer > button';
                if (count($this->exts->querySelectorAll($modalDownloadError)) > 1) {
                    for ($j = 0; $j < 3; $j++) {
                        if ($this->exts->querySelectorAll($modalDownloadError)[1] != null) {
                            $this->exts->log('===== click popup error ====');
                            $this->exts->querySelectorAll($modalDownloadError)[1]->click();
                            sleep(2);
                            try {
                                $this->exts->log('Click download button');
                                $download_button->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click download button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                            }
                        } else {
                            break;
                        }
                    }
                }
                sleep(3);
                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    $this->isNoInvoice = false;
                    $invoicesDownloadSuccess++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        } else if (count($tags) >= 7 && $this->exts->querySelector('button.download-invoice', $tags[6]) != null) {

            $download_button = $this->exts->querySelector('button', $tags[6]);
            $invoiceName = trim($tags[1]->getAttribute('innerText'));
            $invoiceFileName = !empty(trim($invoiceName)) ? $invoiceName . '.pdf' : '';
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';


            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $parsed_date);

            if (empty(trim($invoiceName))) continue;

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
                sleep(20);
                //Check if click button download error
                $modalDownloadError = 'div[aria-labelledby="idModalTitle"] .modal-footer > button';
                if (count($this->exts->querySelectorAll($modalDownloadError)) > 1) {
                    for ($j = 0; $j < 3; $j++) {
                        if ($this->exts->querySelectorAll($modalDownloadError)[1] != null) {
                            $this->exts->log('===== click popup error ====');
                            $this->exts->querySelectorAll($modalDownloadError)[1]->click();
                            sleep(2);
                            try {
                                $this->exts->log('Click download button');
                                $download_button->click();
                            } catch (\Exception $exception) {
                                $this->exts->log('Click download button by javascript');
                                $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                            }
                        } else {
                            break;
                        }
                    }
                }
                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    $this->isNoInvoice = false;
                    $invoicesDownloadSuccess++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }


    if ($page < 50) {
        $page++;
        $this->exts->log("Process page - " . $page);
        if ($this->exts->exists('ul.pagination a[data-url*="page=' . $page . '"]')) {
            $this->exts->click_by_xdotool('ul.pagination a[data-url*="page=' . $page . '"]');
            sleep(15);

            $this->processInvoices($page);
        }
    }
    $this->exts->log('====== Download Completed: ' . $invoicesDownloadSuccess . ' invoices at page: ' . $page);
}