<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//Add assignment
// 1 = sucess
// 3 = unexpected error
$app->post('/api/assignment/add', function(Request $request, Response $response){
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
            $sql = "INSERT INTO `assignment` 
                    (`user_id`, `team`, `address`, `remark`, `date`, `postcode`, `pka`, `pa`) 
                    VALUES
                    (:user_id, :team, :address, :remark, :date, :postcode, :pka, :pa)";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':team', $data->data->team, PDO::PARAM_STR);            
            $stmt->bindParam(':address', $data->data->address, PDO::PARAM_STR);
            $stmt->bindParam(':remark', $data->data->remark, PDO::PARAM_STR);
            $stmt->bindParam(':date', $data->data->date, PDO::PARAM_STR);
            $stmt->bindParam(':postcode', $data->data->postcode, PDO::PARAM_STR);
            $stmt->bindParam(':pka', $data->data->pka, PDO::PARAM_INT);
            $stmt->bindParam(':pa', $data->data->pa, PDO::PARAM_INT);            
            
            $stmt->execute();

            $sql = "SELECT LAST_INSERT_ID() AS id";
            $stmt = $db->prepare($sql);
            $stmt->execute();        

            $assignment_id = $stmt->fetch(PDO::FETCH_OBJ);


            foreach($data->data2 as $row){
                $sql = "INSERT INTO `assignment_admin` (`user_id`, `assignment_id`) VALUES (:user_id, :assignment_id)";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':user_id', $row, PDO::PARAM_INT);
                $stmt->bindParam(':assignment_id', $assignment_id->id, PDO::PARAM_INT);
                $stmt->execute();   
            }

            echo '{ "status": "1",
                    "data"  : ' . json_encode($assignment_id) . ' }
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

//Update assignment
// 1 = sucess
// 3 = unexpected error
$app->post('/api/assignment/update', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();

            // //Upate the assignment table   
            $sql = "UPDATE `assignment` SET 
                        `team` = :team,
                        `address` = :address,
                        `remark` = :remark,
                        `date` = :date,
                        `postcode` = :postcode,
                        `edited_by` = :edited_by,
                        `pka` = :pka,
                        `pa` = :pa
                    WHERE `assignment_id` = :assignment_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':team', $data->data->team, PDO::PARAM_STR);            
            $stmt->bindParam(':address', $data->data->address, PDO::PARAM_STR);
            $stmt->bindParam(':remark', $data->data->remark, PDO::PARAM_STR);
            $stmt->bindParam(':date', $data->data->date, PDO::PARAM_STR);
            $stmt->bindParam(':postcode', $data->data->postcode, PDO::PARAM_STR);
            $stmt->bindParam(':edited_by', $data->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':assignment_id', $data->data->assignment_id, PDO::PARAM_INT);        
            $stmt->bindParam(':pka', $data->data->pka, PDO::PARAM_INT);            
            $stmt->bindParam(':pa', $data->data->pa, PDO::PARAM_INT);            
                
            
            $stmt->execute();

            $user_ids = implode(',',$data->data2);

            //update the assignment_admin table
            $sql = "DELETE FROM `assignment_admin`
                    WHERE `user_id` NOT IN (";
            $comma = '';
            for($i = 0; $i < count($data->data2); $i++){
                $sql .= $comma . ':user_id_' . $i;
                $comma = ',';
            }
            $sql .= ") AND `assignment_id` = :assignment_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':assignment_id', $data->data->assignment_id, PDO::PARAM_INT);                
            for($i = 0; $i < count($data->data2); $i++){
                $stmt->bindParam(':user_id_' . $i, $data->data2[$i]);
            }
            $stmt->execute();

            foreach($data->data2 as $row){
                $sql = "INSERT IGNORE INTO `assignment_admin` (`user_id`, `assignment_id`) VALUES (:user_id, :assignment_id)";
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':user_id', $row, PDO::PARAM_INT);
                $stmt->bindParam(':assignment_id', $data->data->assignment_id, PDO::PARAM_INT);
                $stmt->execute();   
            }

            echo '{ "status": "1" }';
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

//Get assignment list
$app->post('/api/assignment/assignmentList', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $sql = "SELECT `a`.`assignment_id`, `a`.`address`, `a`.`team`, `a`.`postcode`, `a`.`date`, `pka`.`full_name` AS pka_full_name, `pa`.`full_name` AS pa_full_name
                    FROM `assignment` AS `a`
                    LEFT JOIN `user` AS `pka`
                        on `a`.`pka` = `pka`.`user_id`
                    LEFT JOIN `user` AS `pa`
                        on `a`.`pa` = `pa`.`user_id`
                    WHERE `deleted_date` IS NULL
                    ORDER BY `a`.`date` DESC";
            $stmt = $db->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_OBJ);
            echo json_encode($users);
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

//Get assignment from assignment_id
$app->post('/api/assignment/get/{id}', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //execute statement
            $sql = "SELECT `a`.*, `u`.`full_name`, `a`.`pka`, `pka`.`full_name` AS pka_full_name, `a`.`pa`, `pa`.`full_name` AS pa_full_name 
                    FROM `assignment` as `a` 
                    INNER JOIN `user` AS `u` 
                        ON `a`.`user_id` = `u`.`user_id` 
                    LEFT JOIN `user` AS `pka`
                        on `a`.`pka` = `pka`.`user_id`
                    LEFT JOIN `user` AS `pa`
                        on `a`.`pa` = `pa`.`user_id`
                    WHERE `assignment_id` = :assignment_id";
            
            $stmt = $db->prepare($sql);
            $assignment_id = $request->getAttribute('id');            
            $stmt->bindParam(':assignment_id', $assignment_id, PDO::PARAM_STR);
            $stmt->execute();

            $assignment = $stmt->fetch(PDO::FETCH_OBJ);
            echo '{ "status": "1",
                    "data"  : ' .json_encode($assignment). ' }
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

$app->post('/api/assignment/getListByDate', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //execute statement
            $sql = "SELECT `a`.`assignment_id`, `a`.`address`, `a`.`pka`, `pka`.`full_name` AS pka_full_name, `a`.`pa`, `pa`.`full_name` AS pa_full_name 
                    FROM `assignment` as `a` 
                    INNER JOIN `user` AS `u` 
                        ON `a`.`user_id` = `u`.`user_id` 
                    LEFT JOIN `user` AS `pka`
                        on `a`.`pka` = `pka`.`user_id`
                    LEFT JOIN `user` AS `pa`
                        on `a`.`pa` = `pa`.`user_id`
                    WHERE `date` = :date";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':date', $data->data->date, PDO::PARAM_STR);            
            $stmt->execute();

            $assignments = $stmt->fetchAll(PDO::FETCH_OBJ);
            return $response->withJson([
                'status' => '1',
                'data' => $assignments
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

//delete assignment
$app->post('/api/assignment/delete/{id}', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute       .
            $assignment_id = $request->getAttribute('id');    
            $sql = "UPDATE `assignment` SET `deleted_date` = SYSDATE(), `deleted_by` = :user_id WHERE `assignment_id` = :assignment_id";
            $stmt = $db->prepare($sql);        
            $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_STR);                    
            $stmt->bindParam(':assignment_id', $assignment_id, PDO::PARAM_STR);        
            $stmt->execute();

            echo '{ "status": "1" }';
        }
        catch(PDOException $e){
            GenError::unexpectedError($e);
        }
        finally{ $db = null; }
    }
    else{
        echo '{ "status"    : "0",
                "message"   : "Unauthorized access!" }
        ';
    }

});

//Get assignment list
$app->post('/api/assignment_admin/getList/{id}', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();
            //execute statement
            $assignment_id = $request->getAttribute('id');

            $sql = "SELECT `a`.`user_id`, `u`.`full_name` 
                    FROM `assignment_admin` AS `a` 
                    INNER JOIN `user` AS `u` 
                        ON `a`.`user_id` = `u`.`user_id` 
                    WHERE `a`.`assignment_id` = :assignment_id";
                    
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':assignment_id', $assignment_id, PDO::PARAM_INT);        
            $stmt->execute();
            $assignment_admin = json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
            
            echo '{ "status": "1",
                    "data"  : '. $assignment_admin.' }';
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

//Get assignment list
$app->post('/api/assignment/getPDKAssignmentList', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            //get the assignment ids dated today
            $db = $db->connect();
            $sql = "SELECT `assignment_id` FROM `assignment` WHERE `date` = CURDATE() AND deleted_date IS NULL";
            $stmt = $db->query($sql);
            $assignment_ids = $stmt->fetchAll(PDO::FETCH_OBJ);

            //check if the assignment
            $assignment = array();
            foreach($assignment_ids as $val){
                $sql = "SELECT EXISTS (SELECT * FROM `assignment_admin` WHERE `user_id`=:user_id AND `assignment_id`=:assignment_id) AS `exists`";
            
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_INT);        
                $stmt->bindParam(':assignment_id', $val->assignment_id, PDO::PARAM_INT);    
                $stmt->execute();                    
                $assignment_admin = $stmt->fetch(PDO::FETCH_OBJ);

                if($assignment_admin->exists == "1"){
                    $sql = "SELECT `a`.`assignment_id`, `a`.`user_id`, `a`.`postcode`, `u`.`full_name`, `a`.`team`, `a`.`address`, `a`.`remark`, `a`.`date`, `a`.`date_extend`, `pka`.`full_name` AS pka_full_name, `pa`.`full_name` AS pa_full_name
                            FROM `assignment`  AS `a` 
                            INNER JOIN `user` AS `u` 
                                ON `a`.`user_id` = `u`.`user_id` 
                            LEFT JOIN `user` AS `pka`
                                on `a`.`pka` = `pka`.`user_id`
                            LEFT JOIN `user` AS `pa`
                                on `a`.`pa` = `pa`.`user_id`
                            WHERE `a`.`assignment_id`=:assignment_id";
            
                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':assignment_id', $val->assignment_id, PDO::PARAM_INT);    
                    $stmt->execute();                    
                    array_push($assignment, $stmt->fetch(PDO::FETCH_OBJ));
                }
                
            }

            echo '{ "status": "1",
                    "data"  : '. json_encode($assignment) .' 
                }';
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
