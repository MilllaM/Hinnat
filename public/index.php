<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\UploadedFileInterface as UploadedFile;
use Slim\Views\PhpRenderer;
//use Dflydev\FigCookies\Modifier\SameSite; //tarvitaanko?
//use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\FigRequestCookies;
//use Dflydev\FigCookies\FigResponseCookies;


require '../vendor/autoload.php';
// Create and configure Slim app
$config = ['settings' => [
    'addContentLengthHeader' => false,
    'displayErrorDetails' => true
]];
$c = new \Slim\Container($config);
$app = new \Slim\App($c); //app-object creation

$container = $app->getContainer();
$container['view'] = new \Slim\Views\PhpRenderer('../templates/');
$container['upload_directory'] = '../uploads';
$container['errorHandler'] = function ($container) {
    return function($request, $response, $exception) use ($container) {
        return $response->withStatus(500)
        ->withHeader('Content-Type', 'text/html')
        ->write('Ooops, something went wrong!');
    };
};


// The main page
$app->get('/', function ($request, $response) {
    $response= $this->view->render($response, 'newprices.html'); 
    return $response;
});

//after the file upload on the UI:
$app->post('/', function ($request, $response) {
    //read the user credentials
    $data = $request->getParsedBody();
    $user_url = filter_var($data['url_tobe_checked'], FILTER_SANITIZE_STRING);
    $user_token = filter_var($data['token_tobe_checked'], FILTER_SANITIZE_STRING);
    $item_list = filter_var($data['item_list_tobe_used'], FILTER_SANITIZE_STRING);

    
    //check that URL is valid
    if (authCheck($user_url) == false) {
         return $response->withStatus(500)
        ->withHeader('Content-Type', 'text/html')
        ->write('Something went wrong. Pls, check your REST API URL!');
    }
/*
    * ToBeAdded here:
    * fetch the available item lists from *existing db via REST API* and populate them onto a dropdown 
    * wording on the dropdown: "select the department for the price updates" + add also option for "all"
    * 
    * Edit also the usage of item list info within the cookie - maybe it isn't needed in the cookie at all?
    *
*/

    // FILE upload & comparison
    $directory = $this->get('upload_directory'); //defined further above    
    $uploadedFiles = $request->getUploadedFiles();   
    
    //single file upload
    $uploadedFile = $uploadedFiles['uudethinnat']; 
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);

        //uploded file contents as an array
        $newItems = fileToArray($filename); //each index contains three fields (name, code, price), 
        //e.g. [1120] => PRODUCT NAME XXYYnnn ,123123123, 12.56 
        
        //split new items -array into smaller pieces to separate the code-string form the rest of the text
        $newItemsInNewArrays = array();
        foreach($newItems as $justOneItem) {         
            $osa = explode(",",$justOneItem); //splitting done according to commas found
            array_push($newItemsInNewArrays, $osa);
        }
        /*now data is ready for comparison w/ old items
        each item in its own array with 3 indexes:
        [0]=name, [1]=code, [2]=price      
        */


        // get the existing data via REST API
        //get product ID's belonging to the requested item list       
        $oldItems = getItems($item_list, $user_url, $user_token);  //Items, ID's only
        if ($oldItems == false) {
            return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Wrong token given. Please check and try again!');
        } 
                   
        //get the missing item details (i.e. "wholesaler_code") from db
        $details_API = details_from_API($oldItems, $item_list, $user_url, $user_token);
               
       
        //compare lists    NOTE: this function needs editing!!
        //returns two arrays of the matching items from each (combined into one array)
        $comparison_result = compareLists($newItemsInNewArrays, $details_API);
       //echo 'comparison result: <br>';
       //print_r($comparison_result);
       
 
        //combine the matching items to be displayed on UI        
        $items_totheUI = itemsCombined($comparison_result);

        $response= $this->view->render($response, 'searchresults.php', ["content" =>$items_totheUI]); 
        return $response;
    }
    //END file upload & comparison -section 

});


$app->post('/update', function ($request, $response) {  
    $itemList = FigRequestCookies::get($request, 'itemList');   
    $userUrl = FigRequestCookies::get($request, 'userUrl');
    $userToken = FigRequestCookies::get($request, 'userToken');
   
    $givenItemList = $itemList->getValue();
    $givenURL = urldecode($userUrl->getValue());
    $givenToken = $userToken->getValue();
    
   
    //read the user credentials
    $data = $request->getParsedBody();   
    
    $userselection = filter_var($data['selected_items'], FILTER_SANITIZE_STRING);      
    $finalResult = updateOldItem($userselection, $givenItemList, $givenURL, $givenToken);
        
    
    if ($finalResult != null) {
       $response= $this->view->render($response, 'theEnd.html'); 
        return $response;
    }    
            
});


function authCheck($user_url) {
    require_once '../app/api/url_allowed.php';    

    // Is the given URL correct (in the allowed list)?
    if (!in_array($user_url, $allowed)) {
        return false;
    } else {
        return true;  //needed?
    }   
}
/**
 * Moves the uploaded file to the upload directory and assigns it a unique name
 * to avoid overwriting an existing uploaded file.
 *
 * @param string $directory: directory to which the file is moved
 * @param UploadedFile $uploaded: file uploaded file to move
 * @return string filename of moved file
 */
function moveUploadedFile($directory, UploadedFile $uploadedFile) {
    //$extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $extension = pathinfo($uploadedFile->getClientFilename());
    $filename = bin2hex(random_bytes(4)); //use this if file has no extension
    //$filename = sprintf('%s.%0.8s', $basename, $extension);
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}


function fileToArray($filename) {      
    $url = '../uploads/' . $filename;    
    $uploadedarray = file($url);   
    return $uploadedarray;    
}

// 1st query to find the items(item ID's) belonging to the required item list:
function getItems($itemlistnbr, $user_url, $user_token) { 
    try {
        //initialize a curl session
        $ch = curl_init();        
        
        //options        
        $token_value = array("Authorization: Token {$user_token}");
        //the url below is to be used, when real user accounts are in use     
        //$url = "{$user_url}/item/?item_list__is={$itemlistnbr}"; 
  
        $url = $user_url;     
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, $token_value); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);

        //execute
        $curl_response = curl_exec($ch);       
        
        if(curl_errno($ch)){            
            throw new Exception(curl_error($ch));            
        }

        //close the curl session
        curl_close($ch);

        //handle the return data
        $curl_response = json_decode($curl_response, true); 
        if (in_array("Invalid token.", $curl_response)) {            
            return false;
        }
      
        $nbrofPages_fromAPI = $curl_response['num_pages'];      

        //get ALL item data, fetch every page to get all the results 
        $tulosjono = array();
        //get all items from db/API, one page at a time
        for($i=1; $i<=$nbrofPages_fromAPI; $i++) {

            $ch = curl_init();  
                        
            $token_value = array("Authorization: Token {$user_token}");
            //this to be used in the final version            
            //$url = "{$user_url}/item/?item_list__is={$itemlistnbr}&&page={$i}";
           
            $url = "{$user_url}/?page={$i}";    
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, $token_value);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);
            //execute
            $curl_response = curl_exec($ch);
            if(curl_errno($ch)){
                throw new Exception(curl_error($ch));
            }
            //close the curl session
            curl_close($ch);
            //handle the return data
            $curl_response = json_decode($curl_response, true); 

            // Put all the item ID's into an array
            foreach($curl_response["results"] as $item) {               
                array_push($tulosjono, $item["id"]); 
            }
        }
            
        return $tulosjono;

    } catch(Exception $e) {
        // do something on exception
        echo 'no data received, resulting error: ' . $e;
    }
      
} //END getItems


//to get the missing details of the items (wholesaler codes & price)
function details_from_API($oldItems, $item_list, $user_url, $user_token) {    
    $itemDetails = array();
    foreach($oldItems as $singleItem) {
        try {
            $ch = curl_init();
            $token_value = array("Authorization: Token {$user_token}");
            //this is to be used in the final version
            //$url = "{$user_url}/item/{$singleItem}/?item_list__is={$item_list}";            
           
            $url = "{$user_url}/{$singleItem}/"; 
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, $token_value);  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);
            
            $curl_response = curl_exec($ch);
            
            if(curl_errno($ch)){
                throw new Exception(curl_error($ch));
            }        
            curl_close($ch);
            
            //handle the return data, put all item details into an array
            $curl_response = json_decode($curl_response, true);     
            array_push($itemDetails, $curl_response);
        
        } catch(Exception $e) {
            // do something on exception
            echo 'no data from API, resulting error: ' . $e;
        }
    }  
    return $itemDetails;

}  //END: details_from_API


function compareLists ($newItemsInNewArrays, $oldItems) {
   
   //for storing matching items  
    $match_newItems = array();
    $match_OldItems = array();

    foreach($newItemsInNewArrays as $justOneItem) { 
        foreach($oldItems as $singleItem) {
            //comparison: if item code equals with old item's "wholesaler_code"  => MATCH FOUND!
            if($justOneItem[1] == $singleItem['wholesaler_code']) { 
                //add to the matching items -list
                array_push($match_newItems, $justOneItem);
                array_push($match_OldItems, $singleItem);             
            }
        }            
    }    
    return array($match_newItems, $match_OldItems);
}


function showResults($match_newItems, $match_OldItems) { //not in use
    echo '<br>Number of matches found: ' . count($match_newItems).'<br>';
    //echo '<br> Matching items: <br>';
    for ($i=0; $i<count($match_newItems); $i++) {
        echo '<br> Matching item: ' . $i .':<br>';
        echo 'NEW item code:' . $match_newItems[$i][1] .'<br>';
        echo 'NEW item name:' . $match_newItems[$i][0] .'<br>';
        echo 'NEW item price:' . $match_newItems[$i][2] .'<br>';
        echo 'Existing item name: ' . $match_OldItems[$i][2] .'<br>';
        echo 'Existing item id: ' . $match_OldItems[$i][0] .'<br>';         
    } 
}

//function itemsCombined($items_new, $items_old) {    
function itemsCombined($comparison_result) {
    $items_new = $comparison_result[0];
    $items_old = $comparison_result[1];

    $items_totheUI = array();
    for ($i=0; $i<count($items_new); $i++) {    
        array_push($items_totheUI, [
            'NEW item ID' => $items_new[$i][1],
            'NEW item name' => $items_new[$i][0],
            'Existing item name' => $items_old[$i]['name'],
            'NEW price' => $items_new[$i][2],            
            'Existing purchase price' => $items_old[$i]['wholesale_price'],
            'Markup %' =>$items_old[$i]['margin_percent'],
            'Selling price current' => $items_old[$i]['price'],
            'Selling price after change' => round(((float)$items_new[$i][2]) + ((float)$items_new[$i][2]) * ((float)($items_old[$i]['margin_percent']) / 100),2), //= new price * Markup % 
            'ExistingID' => $items_old[$i]['id']
        ]);
    }    
    return $items_totheUI;
}


function updateOldItem($userselection, $givenItemList, $givenURL, $givenToken) {
    //$userselection contains £-signs for the prices, so let's get rid of those 
    $poundremoved = str_replace("£","",$userselection);
        
    //put the results into an array for further processing             
    $itemsForUpdate = explode(",",$poundremoved);
   
    $updateresults = array();
    
    for ($i=0; $i<count($itemsForUpdate); $i = $i+2) {
    
        $itemID = $itemsForUpdate[$i];
        $newPrice = $itemsForUpdate[$i+1];
     
        $data = array(
            "wholesale_price" => $newPrice,
            "instructions" => "price updated 22May"             
        );

        try {
            $ch = curl_init();
            $token_value = array("Authorization: Token {$givenToken}");
            //$url = "{$user_url}/item/{$singleItem}/?item_list__is={$givenItemList}"; 

            //this url format is now only for testing - for final version, pls use the one above
            $url = "{$givenURL}/{$itemID}/"; 
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);  
            curl_setopt($ch, CURLOPT_HTTPHEADER, $token_value);  
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_POST, TRUE); //tarvitaanko?
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 2);            

            $curl_response = curl_exec($ch);
            
            if(curl_errno($ch)){
                throw new Exception(curl_error($ch));
            }        
            curl_close($ch);
            //testing            
            return $curl_response;                                                   
                
        } catch(Exception $e) {
            // do something on exception
            echo 'Ooops, something went wrong: ' . $e;
        }                      
    }
}

// Run app
$app->run();