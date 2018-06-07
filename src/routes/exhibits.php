<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//Add assignment
// 1 = sucess
// 3 = unexpected error
$app->post('/api/exhibit/add', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            $sql = "INSERT INTO `exhibit` 
                    (`po_full_name`, `po_ic_no`, `acceptance`) 
                    VALUES
                    (:po_full_name, :po_ic_no, :acceptance)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':po_full_name', $data->data->po_full_name, PDO::PARAM_STR);
            $stmt->bindParam(':po_ic_no', $data->data->po_ic_no, PDO::PARAM_STR);            
            $stmt->bindParam(':acceptance', $data->data->acceptance, PDO::PARAM_STR);
            
            $stmt->execute();

            $sql = "SELECT LAST_INSERT_ID() AS id";
            $stmt = $db->prepare($sql);
            $stmt->execute();        

            $exhibit_id = $stmt->fetch(PDO::FETCH_OBJ);

            echo '{ "status": "1",
                    "data"  : ' .json_encode($exhibit_id). ' }
            ';
        }
        catch(PDOException $e){
            GenError::unexpectedError($e);
        }
        finally{ $db = null; }

    }
    else{
        GenError::unauthorizedAccess();
    }
});