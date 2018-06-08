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
        $sql = "SELECT `token`, `user_id`, `user_type` FROM `user` 
                WHERE `username` = :username AND `password` = :pass";

        $stmt = $db->prepare($sql);
        $username = strtoupper($param->username);
        $password = md5($param->password);   
    
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);
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
                'message' => 'Invalid login credential.',
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

//register
$app->post('/api/register', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    
    try{
        //get DB object and connect
        $db = $db->connect();
        //execute statement
        $sql = "SELECT `matric_no` FROM `user` 
                WHERE (`matric_no` = :matric_no OR `username` = :username)";

        $stmt = $db->prepare($sql);  
    
        $matric_no = strtoupper($param->matric_no);
        $username = strtoupper($param->username);
        $stmt->bindParam(":matric_no", $matric_no, PDO::PARAM_STR);
        $stmt->bindParam(":username", $username, PDO::PARAM_STR);

        $stmt->execute();

        $existingUser = $stmt->fetchAll(PDO::FETCH_OBJ);

        if($existingUser) {
            return $response->withJson([
                'status' => 'fail',
                'message' => 'User is existing.',
            ])->withStatus(200);
        }
        else {
            $sql2 = "INSERT INTO user(`matric_no`, `username`, `password`, `user_type`, `gender`, `name`, `contact`, `token`) 
                     VALUES(:matric_no, :username, :password, :user_type, :gender, :name, :contact, :token)";
                     
            $password = md5($param->password);
            $token = md5(time());
            $user_type = 1;

            $stmt2 = $db->prepare($sql2);

            $stmt2->bindParam(":matric_no", $matric_no, PDO::PARAM_STR);
            $stmt2->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt2->bindParam(":password", $password, PDO::PARAM_STR);
            $stmt2->bindParam(":user_type", $user_type, PDO::PARAM_INT);
            $stmt2->bindParam(":gender", $param->gender, PDO::PARAM_INT);
            $stmt2->bindParam(":name", $param->name, PDO::PARAM_STR);
            $stmt2->bindParam(":contact", $param->contact, PDO::PARAM_STR);
            $stmt2->bindParam(":token", $token, PDO::PARAM_STR);

            $stmt2->execute();

            $sql3 = "SELECT LAST_INSERT_ID() AS user_id";
            $stmt3 = $db->prepare($sql3);
            $stmt3->execute();
            $user = $stmt3->fetch(PDO::FETCH_OBJ);
            $user->token = $token;
            $user->user_type = 1;

            return $response->withJson([
                'status' => 'success',
                'data' => $user
            ])->withStatus(200);
        }
        
    }
    catch(PDOException $e){
        GenError::unexpectedError($e);
    }
    finally{ $db = null; }
    
});

//get my applications
$app->post('/api/getMyApplications', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user) {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $sql = "SELECT `a`.`application_id`, `a`.`roomtype_id`, `a`.`date`, `a`.`status`, 
                    `a`.`assigned_room`, `r`.`roomtype_name` 
                    FROM `application` AS `a` 
                    INNER JOIN `roomtype` AS `r` 
                    ON `a`.`roomtype_id` = `r`.`roomtype_id` 
                    WHERE `a`.`user_id` = :user_id";

            $stmt = $db->prepare($sql);

            $stmt->bindParam(':user_id', $user->user_id, PDO::PARAM_INT);

            $stmt->execute();

            $myApplications = $stmt->fetchAll(PDO::FETCH_OBJ);

             foreach($myApplications as $myApplication) {
                 $oriDate = DateTime::createFromFormat('Y-m-d', $myApplication->date);
                 $myApplication->date = date_format($oriDate, "d M Y");
             }

            return $response->withJson([
                'status' => 'success',
                'data' => $myApplications,
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

//add application
$app->post('/api/addApplication', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user) {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $sql = "SELECT * FROM `application` WHERE `user_id` = :user_id 
                    AND (`status` = :pending OR `status` = :approve)";

            $stmt = $db->prepare($sql);

            $pending = 0;
            $approve = 1;

            $stmt->bindParam(':user_id', $user->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':pending', $pending, PDO::PARAM_INT);
            $stmt->bindParam(':approve', $approve, PDO::PARAM_INT);

            $stmt->execute();

            $existingApplications = $stmt->fetchAll(PDO::FETCH_OBJ);

            if(!empty($existingApplications)) {
                return $response->withJson([
                    'status' => 'fail',
                    'message' => 'Have pending/approved application.',
                ])->withStatus(200);
            }
            else {
                $sql2 = "INSERT INTO application(`user_id`, `roomtype_id`, `date`, `status`) 
                    VALUES(:user_id, :roomtype_id, :date, :status)";

                $stmt2 = $db->prepare($sql2);

                $date = date("Y-m-d");
                $status = 0;

                $stmt2->bindParam(':user_id', $user->user_id, PDO::PARAM_INT);
                $stmt2->bindParam(':roomtype_id', $param->roomtype_id, PDO::PARAM_INT);
                $stmt2->bindParam(':date', $date, PDO::PARAM_STR);
                $stmt2->bindParam(':status', $status, PDO::PARAM_INT);

                $stmt2->execute();

                return $response->withJson([
                        'status' => 'success',
                        'message' => 'Apply successfully.',
                    ])->withStatus(200);
                }
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

//cancel application
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