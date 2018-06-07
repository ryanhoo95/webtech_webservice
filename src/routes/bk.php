<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//Get all users
$app->get('/api/usersaa', function(Request $request, Response $response){
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

//Get user
$app->post('/api/user', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user) {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $sql = "SELECT * FROM `user`";
            $stmt = $db->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $response->withJson([
                'status' => '1',
                'data' => $users,
                'param' => $user->name
            ])->withStatus(200);
        }
        catch(PDOException $e){
            GenError::unexpectedError($e);
        }
        finally{ $db = null; }
    }
    else {
        GenError::unauthorizedAccess();
    }
    
});