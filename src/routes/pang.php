<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//Get all users
$app->get('/api/users', function(Request $request, Response $response){
    $db = new db();                
    try{
        //get DB object and connect
        $db = $db->connect();
        //execute statement
        $sql = "SELECT * FROM `user`";
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        return $response->withJson([
            'status' => '1',
            'data' => $users
        ])->withStatus(200);
    }
    catch(PDOException $e){
        GenError::unexpectedError($e);
    }
    finally{ $db = null; }
});