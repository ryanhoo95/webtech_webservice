<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\UploadedFile;

function uploadFile($directory, $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);
    
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}

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
    
    if($user) {
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
                        (`roomtype_name`, `description`, `price`, `available_room_num`, `status`) 
                    VALUES 
                        (:roomtype_name, :description, :price, :available_room_num, 1)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':roomtype_name', $param->roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $param->description, PDO::PARAM_STR);    
            $stmt->bindParam(':price', $param->price, PDO::PARAM_STR);                    
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
                    `roomtype_name` = :roomtype_name, `description` = :description, `price` = :price, `available_room_num` = :available_room_num, `status` = :status
                    WHERE `roomtype_id` = :roomtype_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':roomtype_id', $param->roomtype_id, PDO::PARAM_INT);            
            $stmt->bindParam(':roomtype_name', $param->roomtype_name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $param->description, PDO::PARAM_STR);    
            $stmt->bindParam(':price', $param->price, PDO::PARAM_STR);                    
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

$app->post('/api/disableRoomType', function(Request $request, Response $response){
    $db = new db();
    $param = json_decode($request->getBody());
    $user = GenError::authorizeUser($param->token);
    
    if($user && $user->user_type == "0") {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            $sql = "UPDATE `roomtype` SET
                    `status` = 0
                    WHERE `roomtype_id` = :roomtype_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':roomtype_id', $param->roomtype_id, PDO::PARAM_INT);            
            $stmt->execute();

            if($stmt->rowCount() == 1){
                //update all application of this roomtype to 2 (rejected) 
                $sql = "UPDATE `application` SET
                        `status` = 2
                        WHERE `roomtype_id` = :roomtype_id";

                $stmt1 = $db->prepare($sql);
                $stmt1->bindParam(':roomtype_id', $param->roomtype_id, PDO::PARAM_INT);            
                $stmt1->execute();

                return $response->withJson([
                    'status' => 'success'
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

$app->post('/api/uploadRoomPic', function(Request $request, Response $response){
    $db = new db();
    $user = GenError::authorizeUser($request->getParam('token'));
    
    //get the picture
    $directory = dirname( __DIR__, 2 ) . '/uploads';
    
    if($user && $user->user_type == "0") {
        $files = $request->getUploadedFiles();
    
        if (empty($files['file'])) {
            throw new \RuntimeException('Expected a image a file');
        }

        $file = $files['file'];
    
        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = uploadFile($directory, $file);

            try{
                //get DB object and connect
                $db = $db->connect();
        
                //prepare state and execute     
                $sql = "UPDATE `roomtype` SET
                    `image` = :image
                    WHERE `roomtype_id` = :roomtype_id";

                $stmt = $db->prepare($sql);
                $stmt->bindParam(':roomtype_id', $request->getParam('roomtype_id'), PDO::PARAM_INT);            
                $stmt->bindParam(':image', $filename, PDO::PARAM_STR);                               

                $stmt->execute();

                return $response->withJson([
                    'status' => 'sucess',
                    'filename' => $filename,
                    'roomtype_id' => $request->getParam('roomtype_id')
                ])->withStatus(200);
            }
            catch(PDOException $e){
                GenError::unexpectedError($e);
            }
            finally{ $db = null; }  
        }
        else{
            return $response->withJson([
                'status' => 'fail',
                'error' => 'uploaded file is invalid'
            ])->withStatus(200);
        }
    }
    else{
        GenError::unauthorizedAccess();
    }
});