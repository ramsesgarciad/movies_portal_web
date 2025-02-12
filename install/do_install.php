<?php

ini_set('max_execution_time', 300); //300 seconds 

if (isset($_POST)) {
    $host           = $_POST["host"];
    $dbuser         = $_POST["dbuser"];
    $dbpassword     = $_POST["dbpassword"];
    $dbname         = $_POST["dbname"];

    $first_name     = $_POST["first_name"];
    $last_name      = $_POST["last_name"];
    $admin_name     = $first_name.' '.$last_name;
    $email          = $_POST["email"];
    $login_password = $_POST["password"] ? $_POST["password"] : "";

    $purchase_code  = $_POST["purchase_code"];

    //check required fields
    if (!($host && $dbuser && $dbname && $first_name && $last_name && $email && $login_password && $purchase_code)) {
        echo json_encode(array("success" => false, "message" => "Please input all fields."));
        exit();
    }


    //check for valid email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        echo json_encode(array("success" => false, "message" => "Please input a valid email."));
        exit();
    }


    //validate purchase code
    $verification = valid_purchase_code($purchase_code);

    if (!$verification || $verification != "verified") {
        echo json_encode(array("success" => false, "message" => "Please enter a valid purchase code."));
        exit();
    }



    //check for valid database connection
    $mysqli = @new mysqli($host, $dbuser, $dbpassword, $dbname);

    if (mysqli_connect_errno()) {
        echo json_encode(array("success" => false, "message" => $mysqli->connect_error));
        exit();
    }


    //all input seems to be ok. check required fiels
    if (!is_file('database.sql')) {
        echo json_encode(array("success" => false, "message" => "The database.sql file could not found in install folder!"));
        exit();
    }





    /*
     * check the db config file
     * if db already configured, we'll assume that the installation has completed
     */


    $db_file_path = "../application/config/database.php";
    $db_file = file_get_contents($db_file_path);
    $is_installed = strpos($db_file, "enter_hostname");

    if (!$is_installed) {
        echo json_encode(array("success" => false, "message" => "Seems this app is already installed! You can't reinstall it again."));
        exit();
    }


    //start installation

    $sql = file_get_contents("database.sql");


    //set admin information to database
    $sql = str_replace('first_user_full_name', $admin_name, $sql);
    $sql = str_replace('first_user_email', $email, $sql);
    $sql = str_replace('first_user_email', $email, $sql);
    $sql = str_replace('first_user_password', md5($login_password), $sql);
    $sql = str_replace('item_purchase_code', $purchase_code, $sql);

    // generate default api key
    $deafult_api_key = substr(md5(rand()), 0, 15);
    $sql = str_replace('deafult_api_key', $deafult_api_key, $sql);

    // generate rest password
    $rest_user_password = substr(md5(rand()), 0, 15);
    $sql = str_replace('rest_user_password', $rest_user_password, $sql);

    // generate mobile secret key
    $default_mobile_apps_api_secret_key = substr(md5(rand()), 0, 15);
    $sql = str_replace('default_mobile_apps_api_secret_key', $default_mobile_apps_api_secret_key, $sql);

    // generate cron key
    $default_cron_key = substr(md5(rand()), 0, 15);
    $sql = str_replace('default_cron_key', $default_cron_key, $sql);


    //create tables in database 

    $mysqli->multi_query($sql);
    do {
        
    } while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));


    $mysqli->close();
    // database created
    // set the database config file

    $db_file = str_replace('enter_hostname', $host, $db_file);
    $db_file = str_replace('enter_db_username', $dbuser, $db_file);
    $db_file = str_replace('enter_db_password', $dbpassword, $db_file);
    $db_file = str_replace('enter_database_name', $dbname, $db_file);

    file_put_contents($db_file_path, $db_file);


    // set random enter_encryption_key

    $config_file_path = "../application/config/config.php";
    $encryption_key = substr(md5(rand()), 0, 15);
    $config_file = file_get_contents($config_file_path);
    $config_file = str_replace('enter_encryption_key', $encryption_key, $config_file);

    file_put_contents($config_file_path, $config_file);


    // set the environment = development

    $index_file_path = "../index.php";

    $index_file = file_get_contents($index_file_path);
    $index_file = preg_replace('/pre_installation/', 'development', $index_file, 1); //replace the first occurrence of 'pre_installation'

    file_put_contents($index_file_path, $index_file);


    echo json_encode(array("success" => true, "message" => "Installation successful."));
    exit();
}

function valid_purchase_code($purchase_code =''){
    $purchase_code = urlencode($purchase_code);
    $verified  = "unverified";
	if(!empty($purchase_code) && $purchase_code !='' && $purchase_code !=NULL && strlen($purchase_code) > 24):
		$url = 'https://api.envato.com/v3/market/author/sale?code='.$purchase_code;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; Envato API Wrapper PHP)');

		$header = array();
		$header[] = 'Content-length: 0';
		$header[] = 'Content-type: application/json';
		$header[] = 'Authorization: Bearer 5CZXrrM34RPf7ukUzCKqod2BAcQJNKE6';

		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 

		$data = curl_exec($ch);
		curl_getinfo($ch,CURLINFO_HTTP_CODE); 
		curl_close($ch);	
		if( !empty($data) ):
			$result = json_decode($data,true);
            if(isset($result['buyer']) && isset($result['item']['id']) && $result['item']['id'] =='23526581'):
                $verified  = "verified";
            endif;
		endif;
	endif;
	return $verified;
}
