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

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
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