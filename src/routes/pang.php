<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->post('/api/getAllApplications', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //execute statement
            $sql = "SELECT * FROM `application`";
            $stmt = $db->query($sql);
            $applications = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => 'success',
                'data' => $applications,
            ])->withStatus(200);
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

$app->post('/api/approveApplication', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //get room number 
            $sql = "SELECT `a`.`roomtype_id`, `r`.`available_room_num`
                    FROM `application` AS `a`
                    INNER JOIN `roomtype` AS `r`
                        ON `a`.`roomtype_id` = `r`.`roomtype_id`
                    WHERE `a`.`application_id` = :application_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':application_id', $param->application_id, PDO::PARAM_INT);
            $stmt->execute();

            $application = $stmt->fetch(PDO::FETCH_OBJ);
            $assigned_room = $application->roomtype_id . "-" . $user->user_id;
            $updated_available_row = $application->available_room_num - 1;

            if((!empty($application->roomtype_id) || !empty($application->user_id)) && $updated_available_row >= 0) {
                //update application status to approve
                $sql = "UPDATE `application` SET
                        `status` = 1, `assigned_room` = :assigned_room       
                        WHERE `application_id` = :application_id";

                $stmt = $db->prepare($sql);
                $stmt->bindParam(':application_id', $param->application_id, PDO::PARAM_INT);                        
                $stmt->bindParam(':assigned_room', $assigned_room, PDO::PARAM_STR);            

                $stmt->execute();

                if($stmt->rowCount() == 1){
                    //update available room number
                    $sql = "UPDATE `roomtype` SET
                    `available_room_num` = :available_room_num
                    WHERE `roomtype_id` = :roomtype_id";

                    $stmt1 = $db->prepare($sql);
                    $stmt1->bindParam(':roomtype_id', $application->roomtype_id, PDO::PARAM_INT);                        
                    $stmt1->bindParam(':available_room_num', $updated_available_row, PDO::PARAM_INT);            

                    $stmt1->execute();

                    if($stmt1->rowCount() == 1){
                        return $response->withJson([
                            'status' => 'sucess',
                        ])->withStatus(200);
                    }

                    else{
                        return $response->withJson([
                            'status' => 'fail',
                            'error' => 'fail to update roomtype row'
                        ])->withStatus(200);
                    }
                }
                else{
                    return $response->withJson([
                        'status' => 'fail',
                        'error' => 'fail to update application row'
                    ])->withStatus(200);
                }
            }
            else{
                if($updated_available_row < 0){
                    return $response->withJson([
                        'status' => 'fail',
                        'error' => 'No available room'
                    ])->withStatus(200);
                }
                else{
                    return $response->withJson([
                        'status' => 'fail',
                        'error' => 'fail to assigned room number'
                    ])->withStatus(200);
                }
            }
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

$app->post('/api/rejectApplication', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //update application status to approve
            $sql = "UPDATE `application` SET `status` = 2 WHERE `application_id` = :application_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':application_id', $param->application_id, PDO::PARAM_INT);                        

            $stmt->execute();

            if($stmt->rowCount() == 1){
                return $response->withJson([
                    'status' => 'sucess',
                ])->withStatus(200);
            }
            else{
                return $response->withJson([
                    'status' => 'fail',
                    'error' => 'fail to update row'
                ])->withStatus(200);
            }
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

$app->post('/api/getAllRoomTypes', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //execute statement
            $sql = "SELECT * FROM `roomtype`";
            $stmt = $db->query($sql);
            $roomtypes = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => 'success',
                'data' => $roomtypes,
            ])->withStatus(200);
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

$app->post('/api/addRoomType', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            $sql = "INSERT INTO `roomtype` 
                        (`roomtype_name`, `description`, `price`, `image`, `available_room_num`, `status`) 
                    VALUES 
                        (:roomtype_name, :description, :price, :image, :available_room_num, 1)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':roomtype_name', $param->roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $param->description, PDO::PARAM_STR);    
            $stmt->bindParam(':price', $param->price, PDO::PARAM_STR);                    
            $stmt->bindParam(':image', $param->image, PDO::PARAM_STR);
            $stmt->bindParam(':available_room_num', $param->available_room_num, PDO::PARAM_INT);

            $stmt->execute();

            return $response->withJson([
                'status' => 'success',
            ])->withStatus(200);
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

$app->post('/api/updateRoomType', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            $sql = "UPDATE `roomtype` SET
                    `roomtype_name` = :roomtype_name, `description` = :description, `price` = :price, `image` = :image, `available_room_num` = :available_room_num, `status` = :status
                    WHERE `roomtype_id` = :roomtype_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':roomtype_id', $param->roomtype_id, PDO::PARAM_INT);            
            $stmt->bindParam(':roomtype_name', $param->roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $param->description, PDO::PARAM_STR);    
            $stmt->bindParam(':price', $param->price, PDO::PARAM_STR);                    
            $stmt->bindParam(':image', $param->image, PDO::PARAM_STR);
            $stmt->bindParam(':available_room_num', $param->available_room_num, PDO::PARAM_INT);   
            $stmt->bindParam(':status', $param->status, PDO::PARAM_INT);                               

            $stmt->execute();

            return $response->withJson([
                'status' => 'success',
            ])->withStatus(200);
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