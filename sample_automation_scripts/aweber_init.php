public $baseUrl = 'https://www.aweber.com/login.htm';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_login_selector = 'button.login-button';

public $check_login_failed_selector = '.login-wrapper #note-message';
public $check_login_success_selector = 'a[href*="/logout"], form#AccountSelectAccountForm .accounts__name, table#account-selection-table tr button, button.aw__nav-avatar';

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
    $this->exts->capture('1-init-page');
    $this->waitFor($this->check_login_success_selector, 10);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        $this->checkFillLogin();

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->openUrl($this->baseUrl);
            $this->checkFillLogin();
        }
    }
    $this->waitFor($this->check_login_success_selector, 10);
    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'entering your login information again') !== false || $this->exts->urlContains('/closed.htm') || strpos(strtolower($this->exts->extract('span#note-message')), 'your logins have been temporarily blocked') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->password_selector, 15);
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_element($this->submit_login_selector);
        $is_captcha = $this->solve_captcha_by_clicking(0);
        if ($is_captcha) {
            for ($i = 1; $i < 15; $i++) {
                if ($is_captcha == false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                    break;
                }
                $is_captcha = $this->solve_captcha_by_clicking($i);
            }
        }
        sleep(5);
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->click_element($this->submit_login_selector);
            sleep(5);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $this->waitFor('iframe[title="recaptcha challenge expires in two minutes"]', 10);
    $language_code = '';
    if ($this->exts->exists('iframe[title="recaptcha challenge expires in two minutes"]')) {
        $this->exts->capture("brevo-captcha");

        $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc-no-canonical');
        if (trim($captcha_instruction) == '') {
            $captcha_instruction = $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->extract('.rc-imageselect-desc');
        }

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        $captcha_wraper_selector = 'iframe[title="recaptcha challenge expires in two minutes"]';

        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->click_recaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }

                $this->exts->capture("brevo-captcha-selected " . $count);
                $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                sleep(10);
                return true;
            }
        }

        return false;
    }
}

private function click_recaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
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


public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}