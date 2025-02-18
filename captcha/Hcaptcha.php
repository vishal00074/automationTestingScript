<?php


use GmiChromeManager;

class Hcaptcha
{

    public $exts;

    public function __construct()
    {
        $this->exts = new GmiChromeManager();
    }

    /**
     * This function is not for any coding purpose 
     * this will only provide you captcha image
     */
    public function CaptchaImage()
    {
        // Corrected Raw GitHub URL for the image
        return 'https://raw.githubusercontent.com/vishal00074/automationTestingScript/master/captcha/captcha_image.png';
    }




    // solve hcaptcha by clicking
    private function check_solve_hcaptcha_challenge()
    {
        $this->exts->log("Start Solving Captcha");
        $unsolved_hcaptcha_submit_selector = 'iframe[src*="hcaptcha.com/captcha"][title*="checkbox"][data-hcaptcha-response=""]'; // script selector
        $hcaptcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div  >  iframe[src*="frame=challenge"]'; // script selector
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
}
