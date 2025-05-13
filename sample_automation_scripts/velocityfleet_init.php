public $baseUrl = 'https://www.velocityfleet.com';
public $loginUrl = 'https://www.velocityfleet.com/accounts/login/';
public $invoicePageUrl = 'https://www.velocityfleet.com/app/invoices/list/all-invoices';
public $UserUrl = 'https://www.velocityfleet.com/selectSessionCustomer/';

public $username_selector = 'form input#id_username, form[action*="/login"] input[name*="username"]';
public $password_selector = 'form input#id_password, form[action*="/login"] input[name*="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form input[data-qa-id="button__login"], form[action*="/login"] button[type="submit"]';

public $check_login_failed_selector = '.messages-section .alert.message, form#login-panel__form ul.errorlist';
public $check_login_success_selector = 'div#logout-top-nav a[href*="/logout"], a[href="/accounts/logout/"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // $this->fake_user_agent('Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36');   
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
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(5);
        for ($i = 0; $i < 3 && !$this->exts->check_exist_by_chromedevtool('div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'); $i++) {
            sleep(5);
        }
        $this->check_solve_hcaptcha_challenge();
        $this->check_solve_hcaptcha_challenge();
        // if Step above failed, try again
        for ($i = 0; $i < 3 && !$this->exts->exists($this->check_login_failed_selector) && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(5);
            for ($i = 0; $i < 3 && !$this->exts->check_exist_by_chromedevtool('div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'); $i++) {
                sleep(5);
            }
            $this->check_solve_hcaptcha_challenge();
            $this->check_solve_hcaptcha_challenge();
        }
        if (stripos($this->exts->extract('form#login-panel__form ul.errorlist li'), 'You could not be logged in at this time') !== false) {
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(5);
            for ($i = 0; $i < 3 && !$this->exts->check_exist_by_chromedevtool('div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'); $i++) {
                sleep(5);
            }
            $this->check_solve_hcaptcha_challenge();
            $this->check_solve_hcaptcha_challenge();
        }
    }


    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        sleep(2);
    }
    $this->exts->type_key_by_xdotool("F5");
    sleep(7);

    $this->exts->type_text_by_xdotool($this->username);
    sleep(1);
    $this->exts->type_key_by_xdotool("Tab");
    $this->exts->type_text_by_xdotool($this->password);
    sleep(1);
    $this->exts->type_key_by_xdotool("Return");
    sleep(10);
}


private function check_solve_hcaptcha_challenge()
{
    $this->exts->waitTillPresent('iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]');
    $unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->isExists($unsolved_hcaptcha_submit_selector) || $this->isExists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        $this->exts->log("Captcha found");
        if (!$this->isExists($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            sleep(5);
        }

        if ($this->isExists($hcaptcha_challenger_wraper_selector)) { // Select language English always
            $this->exts->log("Select language English always");
            $wraper_side = $this->exts->evaluate('
            window.lastMousePosition = null;
            window.addEventListener("mousemove", function(e){
                window.lastMousePosition = e.clientX +"|" + e.clientY;
            });
            var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
            coo.width + "|" + coo.height;
        ');
            $evalJson = json_decode($wraper_side, true);
            $wraper_side = $evalJson['result']['result']['value'];

            $this->exts->log('Select English language ' . $wraper_side);
            $wraper_side = explode('|', $wraper_side);
            $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 20, (int)$wraper_side[1] - 71);
            sleep(1);
            $this->exts->type_key_by_xdotool('e');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);
        }
        $this->exts->log("prcess hcaptcha start");

        $this->process_hcaptcha_by_clicking();
        $this->process_hcaptcha_by_clicking();
        sleep(5);
        if ($this->isExists($hcaptcha_challenger_wraper_selector)) {
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
        }
        if ($this->isExists($hcaptcha_challenger_wraper_selector)) {
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
        }
        sleep(10);
        $this->exts->capture("2-after-solving-hcaptcha");
    } else {
        $this->exts->log("Captcha Not found");
    }
}
private function process_hcaptcha_by_clicking()
{
    $unsolved_hcaptcha_submit_selector = '.h-captcha[data-size="normal"] iframe[data-hcaptcha-response=""]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->isExists($unsolved_hcaptcha_submit_selector) || $this->isExists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
        $this->exts->capture("hcaptcha");
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        if (!$this->isExists($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            sleep(5);
        }
        // $this->exts->switchToDefault();
        if ($this->isExists($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
            $captcha_instruction = '';
            $old_height = $this->exts->evaluate('
            var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
            var old_height = wrapper.style.height;
            wrapper.style.height = "600px";
            old_height
        ');
            $evalJson = json_decode($old_height, true);
            $old_height = $evalJson['result']['result']['value'];
            $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
            if ($coordinates == '') {
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
            }
            if ($coordinates != '') {
                if ($this->isExists($hcaptcha_challenger_wraper_selector)) {
                    if (!empty($old_height)) {
                        $this->exts->evaluate('
                        document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
                    ');
                    }

                    foreach ($coordinates as $coordinate) {
                        if (!$this->isExists($hcaptcha_challenger_wraper_selector)) {
                            $this->exts->log('Error');
                            return;
                        }
                        $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                        // sleep(1);
                        if (!$this->isExists($hcaptcha_challenger_wraper_selector)) {
                            $this->exts->log('Error');
                            return;
                        }
                    }
                    $marked_time = time();
                    $this->exts->capture("hcaptcha-selected-" . $marked_time);

                    $wraper_side = $this->exts->evaluate('
                    var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
                    coo.width + "|" + coo.height;
                ');

                    $evalJson = json_decode($wraper_side, true);
                    $wraper_side = $evalJson['result']['result']['value'];

                    $wraper_side = explode('|', $wraper_side);
                    $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$wraper_side[0] - 50, (int)$wraper_side[1] - 30);

                    sleep(5);
                    $this->exts->capture("hcaptcha-submitted-" . $marked_time);
                }
            }
        }
        return true;
    }
    return false;
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


private function processClickCaptcha(
    $captcha_image_selector,
    $instruction = '',
    $lang_code = '',
    $json_result = false,
    $image_dpi = 90
) {
    $this->exts->log("--CAll CLICK CAPTCHA SERVICE-");
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
                $response = trim(end(explode("coordinates:", $output)));
            }
        }
    }
    if ($response == '') {
        $this->exts->log("Can not get result from API");
    }
    return $response;
}
//END block

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(10);
    $this->exts->capture("after-clear");
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