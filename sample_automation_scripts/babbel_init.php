public $baseUrl = 'https://my.babbel.com/de/user-profile/account-information';
public $loginUrl = 'https://my.babbel.com/de/user-profile/account-information';
public $invoicePageUrl = 'https://my.babbel.com/de/user-profile/account-information';

public $username_selector = 'input#uat-email-input-field';
public $password_selector = 'input#uat-password-input-field';

public $check_login_failed_selector = 'div.alert-alert, #message-uat-password-input-field';
public $check_login_success_selector = 'li#logout, a[href*="/sign_out"], a[href*="/user-profile/"]';

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
    $this->exts->capture('1-init-page');
    if ($this->exts->exists('button[aria-haspopup="menu"]#radix-2') && !$this->exts->exists('button[aria-haspopup="menu"][id="radix-:r4:"]')) {
        $this->exts->moveToElementAndClick('button[aria-haspopup="menu"]#radix-2');
        sleep(3);
    } else if ($this->exts->exists('button[aria-haspopup="menu"][id="radix-:r4:"]')) {
        $this->exts->moveToElementAndClick('button[aria-haspopup="menu"][id="radix-:r4:"]');
        sleep(3);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(10);
        }
        $this->checkFillLogin();
        sleep(20);
        if ($this->exts->exists('button[aria-haspopup="menu"]#radix-2') && !$this->exts->exists('button[aria-haspopup="menu"][id="radix-:r4:"]')) {
            $this->exts->moveToElementAndClick('button[aria-haspopup="menu"]#radix-2');
            sleep(3);
        } else if ($this->exts->exists('button[aria-haspopup="menu"][id="radix-:r4:"]')) {
            $this->exts->moveToElementAndClick('button[aria-haspopup="menu"][id="radix-:r4:"]');
            sleep(3);
        }


        if ($this->exts->getElement('li:nth-child(3) button[type="button"]') != null) {
            $this->exts->moveToElementAndClick('li:nth-child(3) button[type="button"]');
            sleep(5);
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
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Die E-Mail-Adresse und das Passwort stimmen nicht') !== false || stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'the email address and password do not match') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        // click button next
        $this->exts->moveToElementAndClick('#uat-continue-button');
        sleep(10);
        $this->check_solve_hcaptcha_challenge();
        sleep(3);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick('#uat-continue-button');
        $this->check_solve_hcaptcha_challenge();
        sleep(5);
        if ($this->exts->exists('#uat-continue-button')) {
            $this->exts->moveToElementAndClick('#uat-continue-button');
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
// solve hcaptcha by clicking
private function check_solve_hcaptcha_challenge()
{
    $this->exts->log("Start Solving Captcha");
    $unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        $this->exts->log("Captcha found");
        if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            sleep(5);
        }

        if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // Select language English always
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
        if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
        }
        if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
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
    $unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->exts->exists($unsolved_hcaptcha_submit_selector) || $this->exts->exists($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
        $this->exts->capture("hcaptcha");
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            sleep(5);
        }
        // $this->exts->switchToDefault();
        if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
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
                if ($this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                    if (!empty($old_height)) {
                        $this->exts->evaluate('
                        document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
                    ');
                    }

                    foreach ($coordinates as $coordinate) {
                        if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
                            $this->exts->log('Error');
                            return;
                        }
                        $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                        // sleep(1);
                        if (!$this->exts->exists($hcaptcha_challenger_wraper_selector)) {
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
                    // $this->exts->switchToDefault();
                }
            }
            // $this->exts->switchToDefault();
        }
        return true;
    }
    // $this->exts->switchToDefault();
    return false;
}

private function click_hcaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
    $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
    $selector = base64_encode($selector);
    $element_coo = $this->exts->evaluate('
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
    $evalJson = json_decode($element_coo, true);
    $element_coo = $evalJson['result']['result']['value'];
    // sleep(1);
    $this->exts->log("Browser clicking position: $element_coo");
    $element_coo = explode('|', $element_coo);

    $root_position = $this->get_brower_root_position();
    $this->exts->log("Browser root position");
    print_r($root_position);

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

private function get_brower_root_position($force_relocated = false)
{
    if (isset($GLOBALS['browser_root_position']) && is_array($GLOBALS['browser_root_position']) && !$force_relocated) {
        return $GLOBALS['browser_root_position'];
    }

    $GLOBALS['browser_root_position'] = null;
    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
    for ($i = 0; $i < 5; $i++) {
        $x = rand(100, 355);
        $y = rand(370, 500);
        $this->exts->log("Getting browser current cursor... Screen reference point $x $y");
        $this->exts->evaluate('
        window.localStorage["lastMousePosition"] = "";
        window.addEventListener("mousemove", function(e){
            window.localStorage["lastMousePosition"] = e.clientX +"|" + e.clientY;
        });
    ');
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove --sync $x $y '");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool getmouselocation'", $output);
        $this->exts->log("Latest mouse posision on screen: ");
        print_r($output);

        $result = $this->exts->evaluate('window.localStorage["lastMousePosition"]');
        $evalJson = json_decode($result, true);
        $result = $evalJson['result']['result']['value'];

        if (isset($result['value']) && !empty($result['value'])) {
            $this->exts->log('Browser current cursor: ' . $result['value']);
            $current_cursor = explode('|', $result['value']);
            $GLOBALS['browser_root_position'] = array(
                'root_x' => $x - (int)$current_cursor[0],
                'root_y' => $y - (int)$current_cursor[1],
            );
            return $GLOBALS['browser_root_position'];
        }
    }

    $this->exts->log('CAN NOT detect root position of browser webview');
    return null;
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