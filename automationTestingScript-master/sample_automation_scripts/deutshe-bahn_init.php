public $baseUrl = 'https://www.bahn.de/bahnbusiness';
public $invoicePageUrl = 'https://www.bahn.de/bahnbusiness/buchung/reiseuebersicht/vergangene';

public $username_selector = 'input#login-input-loginname, input[name="username"]';
public $password_selector = 'input#password, input[name="password"]';
public $submit_login_selector = 'button[type="submit"][name="button.weiter"], button[name="login"]';

public $check_login_failed_selector = '#page_login form span.errormsg';
public $check_login_success_selector = 'a[name="link.logout"], .auth-status-logged-in .nav__customer-name';
public $overview_page = 0;

public $isNoInvoice = true;
public $security_question_setup_input = '[name="loginquestion"]';

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->overview_page = isset($this->exts->config_array["overview_page"]) ? (int)@$this->exts->config_array["overview_page"] : $this->overview_page;

    // Load cookies
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    if ($this->exts->check_exist_by_chromedevtool('[aria-modal="true"] [aria-label="Close"]')) {
        $this->exts->click_element('[aria-modal="true"] [aria-label="Close"]');
        sleep(2);
    }
    if ($this->exts->check_exist_by_chromedevtool('button.js-accept-all-cookies')) {
        $this->exts->click_element('button.js-accept-all-cookies');
        sleep(2);
    }
    $this->exts->capture_by_chromedevtool('1-init-page');

    if (!$this->exts->check_exist_by_chromedevtool($this->check_login_success_selector)) {
        $this->exts->loadCookiesFromFile(true);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
    }

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->check_exist_by_chromedevtool($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl('https://www.bahn.de/bahnbusiness/login');
        sleep(5);
        if ($this->exts->check_exist_by_chromedevtool('[aria-modal="true"] [aria-label="Close"]')) {
            $this->exts->click_element('[aria-modal="true"] [aria-label="Close"]');
            sleep(2);
        }
        if ($this->exts->check_exist_by_chromedevtool('button.js-accept-all-cookies')) {
            $this->exts->click_element('button.js-accept-all-cookies');
            sleep(2);
        } else {
            $this->exts->execute_javascript('document.querySelector("body > div:nth-child(1)").shadowRoot.querySelector("button.js-accept-all-cookies").click();');
            sleep(2);
        }

        $this->checkFillLogin();
        sleep(7);
        $this->checkFillTwoFactor();

        if ($this->exts->oneExists([$this->username_selector, $this->password_selector]) && !$this->incorrectCredential()) {
            $this->checkFillLogin();
            sleep(7);
            if ($this->exts->oneExists([$this->username_selector, $this->password_selector]) && !$this->incorrectCredential()) {
                $this->checkFillLogin();
                sleep(7);
            }
        }

        if ($this->exts->exists('#btn-skip-2fa-config')) {
            $this->exts->moveToElementAndClick('#btn-skip-2fa-config');
            sleep(5);
        }


        if ($this->exts->check_exist_by_chromedevtool('button.js-accept-all-cookies')) {
            $this->exts->click_element('button.js-accept-all-cookies');
            sleep(2);
        } else {
            $this->exts->execute_javascript('document.querySelector("body > div:nth-child(1)").shadowRoot.querySelector("button.js-accept-all-cookies").click();');
            sleep(2);
        }
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 10);
    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        //check for security question setup page
        if ($this->exts->exists($this->security_question_setup_input)) {
            $this->exts->account_not_ready();
        }

        $this->exts->waitTillPresent('div#new-password-description-text', 10);
        if ($this->exts->exists('div#new-password-description-text')) {
            $this->exts->account_not_ready();
        }

        if (stripos($this->exts->extract('.errormsg, .message-error'), 'Ihre Buchung als Firmenkunde den Firmenkunden-Zugang')) {
            $this->exts->account_not_ready();
        } else if (stripos($this->exts->extract('.errormsg, .message-error'), 'Bitte verwenden Sie hier einen anderen Benutzername und ein anderes Passwort')) {
            $this->exts->account_not_ready();
        } else if (stripos($this->exts->extract('.errormsg, .message-error', null, 'innerText'), 'Konto ist deaktiviert')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('form#kc-update-profile-form')) {
            $this->exts->account_not_ready();
        } else if (strpos($this->exts->extract('.login-page__message'), 'Benutzername oder das Passwort ist') !== false || strpos($this->exts->extract('.login-page__message'), 'Invalid username or password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->check_exist_by_chromedevtool($this->username_selector)) {
        $this->exts->capture_by_chromedevtool("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);


        $this->exts->capture_by_chromedevtool("2-username-filled");
        if (!$this->exts->check_exist_by_chromedevtool($this->password_selector)) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(7);
            $this->solve_login_hcaptcha();
            if (!$this->exts->exists($this->password_selector) && !$this->incorrectCredential()) {
                $this->solve_login_hcaptcha();
            }
        }

        if ($this->exts->check_exist_by_chromedevtool($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->password);
            sleep(1);

            $this->exts->capture_by_chromedevtool("2-password-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(7);
            if ($this->exts->check_exist_by_chromedevtool($this->password_selector)) {
                $this->solve_login_hcaptcha();
                if ($this->exts->exists($this->password_selector) && !$this->incorrectCredential()) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(7);
                    $this->solve_login_hcaptcha();
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture_by_chromedevtool("2-password-not-found");
        }
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="otp"],input[name="smsCode"],input#verificationCode';
    $two_factor_message_selector = '#kc-content-wrapper p,form#verify-email-form';
    $two_factor_submit_selector = 'button#totp-submit,button#button-submit-verify-email';

    if ($this->exts->exists($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture_by_chromedevtool("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            $message_count = $this->exts->count_elements($two_factor_message_selector);
            for ($i = 0; $i < $message_count; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->get('innerText') . "\n";
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
            $this->exts->click_element($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);
            $this->exts->capture_by_chromedevtool("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->click_element($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->exists($two_factor_selector)) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
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

private function incorrectCredential()
{
    $message = $this->exts->querySelector('.login-page__message, #error-message-container.message-error');
    if ($message) {
        $message = $message->get('innerText');
    }
    return stripos($message, 'Benutzername oder das Passwort ist') !== false ||
        stripos($message, 'Benutzername oder das Passwort sind ung') !== false ||
        stripos($message, 'Invalid username or password') !== false ||
        stripos($message, 'eingegebene Benutzername ist ung') !== false ||
        stripos($message, 'tiges Passwort eingegeben') !== false;
}

private function solve_login_hcaptcha()
{
    $unsolved_hcaptcha_submit_selector = 'button[name="login"].h-captcha[data-size="invisible"]';
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector) || $this->exts->exists($unsolved_hcaptcha_submit_selector)) { // if exist hcaptcha and it isn't solved
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
        if (!$this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
            $this->exts->click_by_xdotool($unsolved_hcaptcha_submit_selector);
            sleep(5);
        }
        $wraper_side = null;
        if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) { // Select language English always
            $wraper_side = $this->exts->execute_javascript('
            window.lastMousePosition = null;
            window.addEventListener("mousemove", function(e){
                window.lastMousePosition = e.clientX +"|" + e.clientY;
            });
            var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
            coo.width + "|" + coo.height;
        ');

            $this->exts->log('Select English language ' . $wraper_side);
            $wraper_side = explode('|', $wraper_side);
            $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 28, (int)$wraper_side[1] - 71);
            sleep(1);
            $this->exts->type_key_by_xdotool('e');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);
        }

        $this->process_hcaptcha_by_clicking();
        $this->process_hcaptcha_by_clicking();
        $this->process_hcaptcha_by_clicking();
        sleep(5);
        if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector) && !$this->incorrectCredential()) {
            $wraper_side = $this->exts->execute_javascript('
            window.lastMousePosition = null;
            window.addEventListener("mousemove", function(e){
                window.lastMousePosition = e.clientX +"|" + e.clientY;
            });
            var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
            coo.width + "|" + coo.height;
        ');

            $this->exts->log('Select English language ' . $wraper_side);
            $wraper_side = explode('|', $wraper_side);
            $this->exts->click_by_xdotool($hcaptcha_challenger_wraper_selector, 28, (int)$wraper_side[1] - 71);
            sleep(1);
            $this->exts->type_key_by_xdotool('e');
            sleep(1);
            $this->exts->type_key_by_xdotool('Return');
            sleep(2);

            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
        }
        if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector) && !$this->incorrectCredential()) {
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            $this->process_hcaptcha_by_clicking();
            sleep(5);
        }
        sleep(5);
        $this->exts->capture_by_chromedevtool("2-after-solving-hcaptcha");
    }
}

private function process_hcaptcha_by_clicking()
{
    $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]';
    if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) { // if exist hcaptcha and it isn't solved
        $this->exts->capture_by_chromedevtool("hcaptcha");

        // $this->exts->switchToDefault();
        if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) { // If image chalenge doesn't displayed, maybe captcha solved after clicking checkbox
            $captcha_instruction = 'For the scale challenger ONLY, click the heavier. In other case follow description in image.';
            $old_height = $this->exts->execute_javascript('
            var wrapper = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '"));
            var old_height = wrapper.style.height;
            wrapper.style.height = "600px";
            old_height
        ');
            $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85); // use $language_code and $captcha_instruction if they changed captcha content
            if ($coordinates == '') {
                $coordinates = $this->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true, 85);
            }
            if ($coordinates != '') {
                if ($this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                    if (!empty($old_height)) {
                        $this->exts->execute_javascript('
                        document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).style.height = "' . $old_height . '";
                    ');
                    }

                    foreach ($coordinates as $coordinate) {
                        if (!$this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                            $this->exts->log('Error');
                            return;
                        }
                        $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                        // sleep(1);
                        if (!$this->exts->check_exist_by_chromedevtool($hcaptcha_challenger_wraper_selector)) {
                            $this->exts->log('Error');
                            return;
                        }
                    }
                    $marked_time = time();
                    $this->exts->capture_by_chromedevtool("hcaptcha-selected-" . $marked_time, false);

                    $wraper_side = $this->exts->execute_javascript('
                    var coo = document.querySelector(atob("' . base64_encode($hcaptcha_challenger_wraper_selector) . '")).getBoundingClientRect();
                    coo.width + "|" + coo.height;
                ');
                    $wraper_side = explode('|', $wraper_side);
                    $this->exts->log('Click submit hcaptcha');
                    $this->click_hcaptcha_point($hcaptcha_challenger_wraper_selector, (int)$wraper_side[0] - 50, (int)$wraper_side[1] - 30);

                    sleep(5);
                    $this->exts->capture_by_chromedevtool("hcaptcha-submitted-" . $marked_time, false);
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

// util function for resolve captcha
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

private function captureElement($fileName, $selector = null)
{
    $screenshot = '' . time();
    $image_file_path = $this->exts->screen_capture_location . $screenshot . '.png';
    $reponse_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Page.captureScreenshot', []);
    if (empty($reponse_text)) {
        $reponse_text = $this->exts->send_websocket_request($this->exts->current_context->webSocketDebuggerUrl, 'Page.captureScreenshot', []);
    }
    $base64_string = json_decode($reponse_text, true);
    $ifp = fopen($image_file_path, 'wb');
    fwrite($ifp, base64_decode($base64_string['result']["data"]));
    fclose($ifp);
    $this->exts->log('Screenshot saved - ' . $image_file_path);

    $screenshot = $image_file_path;
    if (!file_exists($screenshot)) {
        $this->log("Could not save screenshot");
        return $screenshot;
    }

    if (!(bool)$selector) {
        return $screenshot;
    }

    $selector = base64_encode($selector);
    $javascript_expression = '
    var element = document.querySelector(atob("' . $selector . '"));
    var bcr = element.getBoundingClientRect();
    JSON.stringify(bcr);
';
    $coodinate = $this->exts->execute_javascript($javascript_expression);
    $coodinate = json_decode($coodinate, true);
    $this->exts->log(print_r($coodinate, true));

    // Copy
    $element_screenshot = $this->exts->screen_capture_location . $fileName . ".png";
    $src = imagecreatefrompng($screenshot);
    $dest = imagecreatetruecolor(round($coodinate['width']), round($coodinate['height']));
    imagecopy($dest, $src, 0, 0, round($coodinate['x']), round($coodinate['y']), round($coodinate['width']), round($coodinate['height']));
    imagepng($dest, $element_screenshot);

    if (!file_exists($element_screenshot)) {
        $this->exts->log("Could not save screenshot");

        return $screenshot;
    }

    return $element_screenshot;
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
    $image_path = $this->captureElement($this->exts->process_uid, $captcha_image_selector);
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

// Huy END block