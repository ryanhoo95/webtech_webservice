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
                'message' => 'User not found.',
            ])->withStatus(200);
        }
        
    }
    catch(PDOException $e){
        GenError::unexpectedError($e);
    }
    finally{ $db = null; }
    
});

//logout
$app->post('/api/logout', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user) {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $sql = "UPDATE `user` SET `token` = :token
                     WHERE `user_id` = :user_id";

            $stmt = $db->prepare($sql);
            $token = null;

            $stmt->bindParam(':token', $token, PDO::PARAM_NULL);
            $stmt->bindParam(':user_id', $user->user_id, PDO::PARAM_INT);

            $stmt->execute();

            return $response->withJson([
                'status' => 'success',
                'message' => 'Logout successfully.',
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