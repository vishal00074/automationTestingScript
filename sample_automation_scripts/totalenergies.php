<?php// updated login code and download code // updated login code and added extract zip code in invoice download
// Server-Portal-ID: 2798003 - Last modified: 07.02.2025 10:53:05 UTC - User: 1

/*Define constants used in script*/

public $baseUrl = 'https://client.mobility.totalenergies.com/web/guest/select-account';
public $loginUrl = 'https://client.mobility.totalenergies.com/web/guest/home';
public $invoicePageUrl = 'https://client.mobility.totalenergies.com/group/france/invoices';

public $continue_login_button = 'form input#tec';

public $username_selector = 'form.gigya-passwordless-login-form input#fixed-username';
public $password_selector = 'form#gigya-password-auth-method-form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_email_selector = 'form.gigya-passwordless-login-form input#submitLoginPasswordLess';



public $submit_login_selector = 'form#gigya-password-auth-method-form input#passwd-submit';

public $check_login_failed_selector1 = 'form#gigya-register-form input#register-firstname';
public $check_login_failed_selector2 = 'form#gigya-password-auth-method-form div.gigya-form-error-msg';


public $check_login_success_selector = 'span[class*="user-profile-img user-profile-initial"], table#cardProAccountList';

public $accounts_selector = 'table#invoiceListTable tbody tr, table#cardProAccountList tbody tr';

public $isNoInvoice = true;
/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
   
    $this->exts->openUrl($this->invoicePageUrl);
    sleep(10);
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->waitTillPresent($this->continue_login_button, 10);
        if ($this->exts->exists($this->continue_login_button)) {
            $this->exts->click_element($this->continue_login_button);
        }

        $this->fillForm(0);
        sleep(5);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        if($this->exts->exists($this->continue_login_button)){
             $this->exts->click_element($this->continue_login_button);
        }
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        $this->exts->openUrl($this->invoicePageUrl);
        $this->processInvoices();


    } else {
        if ($this->exts->exists($this->check_login_failed_selector1) || $this->exts->exists($this->check_login_failed_selector2)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    sleep(10);
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 20);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(5);
            if ($this->exts->exists($this->submit_login_email_selector)) {
                $this->exts->click_element($this->submit_login_email_selector);
            }
            sleep(5);

            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);
                $this->exts->click_element($this->submit_login_email_selector);
            }

            sleep(35);


            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(4);

            $this->exts->click_element('input[type="checkbox"][id="passwd-gotoprofile"]');
            sleep(4);


            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }
            sleep(5);
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillAnyPresent([$this->check_login_success_selector], 20);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}


private function processInvoices() {
    sleep(10);
    $this->exts->waitTillPresent('table#invoiceListTable tbody tr', 30);
    $rows = $this->exts->querySelectorAll('table#invoiceListTable tbody tr');

    $this->exts->log('invoices found: ' . count($rows));

    foreach($rows as $index => $row) {
        $this->isNoInvoice = false;

        $invoiceAmount = $row->querySelectorAll('td')[9]->getText();
        $this->exts->log('invoice amount: ' . $invoiceAmount);

        $invoiceDate =  $row->querySelectorAll('td')[3]->getText();
        $this->exts->log('invoice date: ' . $invoiceDate);

        $parsedDate = $this->exts->parse_date($invoiceDate, '', 'Y-m-d');

        $this->exts->execute_javascript("arguments[0].click();", [$row->querySelectorAll('td')[10]->querySelector('a')]);

                
        $this->exts->wait_and_check_download('zip');
        $downloaded_file = $this->exts->find_saved_file('zip');
        $invoiceFileName = basename($downloaded_file);

        $invoiceName= substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

        $this->exts->log('invoiceName: ' . $invoiceName);


        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->extract_single_zip_save_pdf($downloaded_file);
            // $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
            sleep(1);
        } else {
            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
        }
        sleep(2);
    }

}

function extract_single_zip_save_pdf($zipfile)
{
    $zip = new \ZipArchive;
    $res = $zip->open($zipfile);
    if ($res === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipPdfFile = $zip->statIndex($i);
            $fileName = basename($zipPdfFile['name']);

            $this->exts->log(__FUNCTION__ . '::Extracted file name: ' . $fileName);

            $fileInfo = pathinfo($fileName);

            $this->exts->log(__FUNCTION__ . '::Pathinfo: ' . print_r($fileInfo, true));

            if (isset($fileInfo['extension']) && strtolower($fileInfo['extension']) === 'pdf') {
                $this->exts->log('PDF file verified');

                $this->isNoInvoice = false;
                
                $zip->extractTo($this->exts->config_array['download_folder'], $fileName);
                $saved_file = $this->exts->config_array['download_folder'] . $fileName;

                $this->exts->new_invoice($fileInfo['filename'], "", "", $saved_file);

                sleep(1);
            }
        }
        $zip->close();
        unlink($zipfile); 
    } else {
        $this->exts->log(__FUNCTION__ . '::File extraction failed');
    }
}



