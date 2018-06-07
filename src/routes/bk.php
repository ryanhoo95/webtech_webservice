<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//login
$app->post('/api/login', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    
    try{
        //get DB object and connect
        $db = $db->connect();
        //execute statement
        $sql = "SELECT `token`, `user_id` FROM `user`
                WHERE `matric_no` = :matric_no AND `password` = :pass";

        $stmt = $db->prepare($sql);
        $password = md5($param->password);   
    
        $stmt->bindParam(":matric_no", $param->matric_no, PDO::PARAM_STR);
        $stmt->bindParam(":pass", $password, PDO::PARAM_STR);

        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_OBJ);

        if(!empty($user)) {
            $user->token = md5(time());

            $sql2 = "UPDATE `user` SET `token` = :token
                     WHERE `user_id` = :user_id";

            $stmt2 = $db->prepare($sql2);

            $stmt2->bindParam(':token', $user->token);
            $stmt2->bindParam(':user_id', $user->user_id);

            $stmt2->execute();

            return $response->withJson([
                'status' => 'success',
                'data' => $user,
            ])->withStatus(200);
        }
        else {
            return $response->withJson([
                'status' => 'fail',
                'message' => 'User not found',
                'pass' => $param->password,
                'matric_no' => $param->matric_no,
                'password' => $password
            ])->withStatus(200);
        }
    }
    catch(PDOException $e){
        GenError::unexpectedError($e);
    }
    finally{ $db = null; }
    
});

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