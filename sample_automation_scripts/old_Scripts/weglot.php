<?php // updated login code 
// Server-Portal-ID: 75219 - Last modified: 15.01.2025 16:57:30 UTC - User: 1

public $baseUrl = "https://dashboard.weglot.com/";
public $loginUrl = "https://dashboard.weglot.com/login";
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_button_selector = 'button[name="login"]';
public $login_tryout = 0;
public $restrictPages = 3;
public $totalFiles = 0;
public $check_login_failed_selector = 'p[class="text-danger"]';




/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
   
    
    // $this->exts->openUrl($this->loginUrl);
    // sleep(5);
    // $this->exts->capture("Home-page-without-cookie");
    // $this->check_solve_blocked_page();
    // $isCookieLoginSuccess = false;
    // if($this->exts->loadCookiesFromFile()) {
    //     $this->exts->openUrl($this->loginUrl);
    //     sleep(10);
    //     if($this->checkLogin()) {
    //         $isCookieLoginSuccess = true;
    //     } else {
    //         $this->exts->clearCookies();
    //         $this->exts->openUrl($this->loginUrl);
    //         sleep(10);
    //         $this->check_solve_blocked_page();
    //     }
    // } else {
    //     $this->exts->openUrl($this->loginUrl);
    //     sleep(10);

    //     $this->check_solve_blocked_page();
    // }



    
    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    
    if(!$this->checkLogin()) {

        $this->clearChrome();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_blocked_page();
        $this->fillForm(0);
        if(!$this->checkLogin()){
             $this->exts->openUrl($this->baseUrl);
             sleep(10);
             $this->check_solve_blocked_page();
             $this->repeatedly();
        }
    }
        
    if($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->invoicePage();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "Wrong credentials.") !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
        
    }
}


/**
 * Clearing browser history, cookie, cache 
 * 
 */
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
    sleep(15);
    $this->exts->capture("after-clear");
}

private function repeatedly()
{
    $timeout = 200; // Max wait time in seconds
    $interval = 5;  // Time to wait between checks (adjust as needed)
    $startTime = time();
    $this->exts->log("Filling form repeatedly till untill success duration till 200 sec");
    $i = 1;
    while (time() - $startTime < $timeout) {
        if ($this->exts->exists('a[href*="/logout"], a[href*="/settings"]')) {
            $this->exts->log("Login success");
            break;
        }
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->check_solve_blocked_page();
        $this->fillForm($i);
        sleep($interval); 
        $i++;
    }

   
    if (!$this->exts->exists('a[href*="/logout"], a[href*="/settings"]')) {
          $this->exts->log("Login not success within 200 seconds.");
    }
}

function fillForm($count){
    $this->exts->log("Begin fillForm ".$count);
    try {
        sleep(1);
        if( $this->exts->exists($this->username_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("login-fill-form");
            
            if($this->exts->exists($this->submit_button_selector)){
                $this->exts->moveToElementAndClick($this->submit_button_selector);
            }
            sleep(5);
         
            $err_msg = $this->exts->extract('div.alert.alert-danger');
            if ($err_msg != "" && $err_msg != null) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }

            if($this->exts->exists('div[class*="security-message"] > p[class="text-danger"]')){
                $this->exts->log('something went wrong');
            }

        }
        
        sleep(10);
    } catch(\Exception $exception){
        $this->exts->log("Exception filling loginform ".$exception->getMessage());
    }
}
function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if($this->exts->exists('a[href*="/logout"], a[href*="/settings"]')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
            
        }
    } catch(Exception $exception){
        $this->exts->log("Exception checking loggedin ".$exception);
    }
    return $isLoggedIn;
}

function invoicePage() {
    $this->exts->log("Invoice page");

    
    if($this->exts->exists('.navbar a.btn.btn-dark.btn-icon[href*="#"]')) {
        $this->exts->moveToElementAndClick('.navbar a.btn.btn-dark.btn-icon[href*="#"]');
    } else {
        $this->exts->moveToElementAndClick('ul.top-nav>li.dropdown>a#profile-picture');
    }
    sleep(2);
    
    if($this->exts->exists('.navbar .dropdown.show a[href*="/billing/"]')) {
        $this->exts->moveToElementAndClick('.navbar .dropdown.show a[href*="/billing/"]');
    } else {
        $this->exts->moveToElementAndClick('ul.top-nav>li.dropdown.show a[href="/billing/"]');
    }
    sleep(10);
    
    if($this->exts->exists('ul.nav.top-menu>li>a[href="/invoices/"]')) {
        $this->exts->moveToElementAndClick('ul.nav.top-menu>li>a[href="/invoices/"]');
        sleep(5);
    }

  if($this->exts->exists(".col-4.d-none.d-lg-block li.nav-item a[href*='billing']")) {
    $this->exts->moveToElementAndClick(".col-4.d-none.d-lg-block li.nav-item a[href*='billing']");
    sleep(5);
  }
    
    if(!$this->exts->exists('table#js-data-tables-invoices tbody tr')) {

        $invoiceUrl = $this->exts->getUrl();

        $invoiceUrl = $invoiceUrl.'invoices';
        $this->exts->openUrl($invoiceUrl);
        sleep(10);
    }
    
    $this->downloadInvoice();
    
    if ($this->totalFiles == 0) {
        $this->exts->log("No invoice !!! ");
        $this->exts->no_invoice();
    }
}

function downloadInvoice($count=1, $pageCount=1){
    $this->exts->log("Begin download invoice");
    
    $this->exts->capture('4-List-invoice');
    if((int)$this->restrictPages == 0) {
        $load_more = 0;
        while ($this->exts->exists('button#invoices-load-more:not(.hide):not(.d-none)') && $load_more < 10) {
            $load_more++;
            $this->exts->moveToElementAndClick('button#invoices-load-more:not(.hide):not(.d-none)');
            sleep(4);
        }
    }
    
    try{
        if ($this->exts->getElement('table#js-data-tables-invoices tbody tr') != null) {
            $receipts = $this->exts->getElements('table#js-data-tables-invoices > tbody > tr');
            $invoices = array();
            foreach ($receipts as $i=> $receipt) {
                $tags = $this->exts->getElements('td', $receipt);
                if (count($tags) >= 4 && $this->exts->getElement('td a[href*=".pdf"]', $receipt) != null) {
                    if(count($tags) == 5) {
                        $receiptDate = trim($tags[1]->getText());
                        $receiptUrl = $this->exts->extract('td a[href*=".pdf"]', $receipt, 'href');
                        $receiptName = trim($tags[2]->getText());
                        $receiptAmount = trim($tags[3]->getText());
                    } else {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptUrl = $this->exts->extract('td a[href*=".pdf"]', $receipt, 'href');
                        $receiptName = trim($tags[1]->getText());
                        $receiptAmount = trim($tags[2]->getText());
                    }
                    
                    $receiptFileName = $receiptName . '.pdf';
                    
                    $this->exts->log("_____________________" . ($i+1) . "___________________________________________");
                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $this->exts->log("Invoice Url: " . $receiptUrl);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("________________________________________________________________");
                    
                    $invoice = array(
                        'receiptDate' => $receiptDate,
                        'receiptName' => $receiptName,
                        'receiptAmount' => $receiptAmount,
                        'receiptUrl' => $receiptUrl,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }
            
            $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));
            
            $this->totalFiles = count($invoices);
            $count = 1;
            foreach ($invoices as $invoice) {
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                $this->exts->log("Download file: " . $downloaded_file);
                
                if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'] , $invoice['receiptAmount'], $downloaded_file);
                    sleep(1);
                    $count++;
                }
            }
        }
    } catch(\Exception $exception){
        $this->exts->log("Exception downloading invoice ".$exception->getMessage());
    }
}


private function checkFillLoginUndetected() {
    
    $windowHandlesBefore = $this->exts->findTabMatchedUrl(['weglot']);
        if ($windowHandlesBefore != null) {
            $this->exts->switchToTab($windowHandlesBefore);
        }
    print_r($windowHandlesBefore);
    $this->exts->type_key_by_xdotool("Ctrl+t");
    sleep(3);
    $this->exts->type_text_by_xdotool($this->baseUrl);
    $this->exts->type_key_by_xdotool("Return");
    sleep(20);
    for ($i=0; $i < 11 ; $i++) { 
        $this->exts->type_key_by_xdotool("Tab");
        sleep(1);
    }
    $this->exts->log("Enter Username");
    $this->exts->type_text_by_xdotool($this->username);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(1);
    $this->exts->log("Enter Password");
    $this->exts->type_text_by_xdotool($this->password);
    sleep(1);
    $this->exts->type_key_by_xdotool("Return");
    sleep(20);
    $windowHandlesAfter = $this->exts->webdriver->getWindowHandles();
    print_r($windowHandlesAfter);
    $newWindowHandle = array_diff($windowHandlesAfter, $windowHandlesBefore);
    foreach ($newWindowHandle as $handle) {    
        $tab = $this->exts->findTabMatchedUrl(['weglot']);
        if ($tab != null) {
            $this->exts->switchToTab($tab);
            break;
        }
    }
}

// helper functions
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