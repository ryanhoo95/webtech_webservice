<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->post('/api/ind/add', function(Request $request, Response $response){
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
            $sql = "INSERT INTO `ind` 
                        (`assignment_id`, `user_id`, `area_inspection`, `p_cooperation`, `p_close`, `p_empty`, `p_shortAddr`, `po_name`, `po_id`, `no_familyMember`, `no_fever`, 
                        `no_out_breeding`, `no_in_breeding`, `container_type`, `no_pot_out_breeding`, `no_pot_in_breeding`, `abating_measure_type`, `abating_amount`, `act_destroy`, 
                        `act_education`, `act_pamphlet`, `issue_date`, `coor_lat`, `coor_lng`) 
                    VALUES 
                        (:assignment_id, :user_id, :area_inspection, :p_cooperation, :p_close, :p_empty, :p_shortAddr, :po_name, :po_id, :no_familyMember, :no_fever, 
                        :no_out_breeding, :no_in_breeding, :container_type, :no_pot_out_breeding, :no_pot_in_breeding, :abating_measure_type, :abating_amount, :act_destroy, 
                        :act_education, :act_pamphlet, CURDATE(), :coor_lat, :coor_lng)";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':assignment_id', $data->data->assignment_id, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $data->user_id, PDO::PARAM_INT);    
            $stmt->bindValue(':area_inspection', (int)$data->data->area_inspection, PDO::PARAM_INT);                    
            $stmt->bindValue(':p_cooperation', (int)$data->data->p_cooperation, PDO::PARAM_INT);
            $stmt->bindValue(':p_close', (int)$data->data->p_close, PDO::PARAM_INT);
            $stmt->bindValue(':p_empty', (int)$data->data->p_empty, PDO::PARAM_INT);
            $stmt->bindValue(':p_shortAddr', $data->data->p_shortAddr, PDO::PARAM_STR);
            $stmt->bindValue(':po_name', $data->data->po_name, PDO::PARAM_STR);
            $stmt->bindValue(':po_id', $data->data->po_id, PDO::PARAM_STR);
            $stmt->bindValue(':no_familyMember', $data->data->no_familyMember, PDO::PARAM_INT);
            $stmt->bindValue(':no_fever', $data->data->no_fever, PDO::PARAM_INT);
            $stmt->bindValue(':no_out_breeding', $data->data->no_out_breeding, PDO::PARAM_INT);
            $stmt->bindValue(':no_in_breeding', $data->data->no_in_breeding, PDO::PARAM_INT);
            $stmt->bindValue(':container_type', $data->data->container_type, PDO::PARAM_STR);
            $stmt->bindValue(':no_pot_out_breeding', $data->data->no_pot_out_breeding, PDO::PARAM_INT);
            $stmt->bindValue(':no_pot_in_breeding', $data->data->no_pot_in_breeding, PDO::PARAM_INT);
            $stmt->bindValue(':abating_measure_type', (int)$data->data->abating_measure_type, PDO::PARAM_INT);
            $stmt->bindValue(':abating_amount', $data->data->abating_amount);            
            $stmt->bindValue(':act_destroy', (int)$data->data->act_destroy, PDO::PARAM_INT);
            $stmt->bindValue(':act_education', (int)$data->data->act_education, PDO::PARAM_INT);
            $stmt->bindValue(':act_pamphlet', (int)$data->data->act_pamphlet, PDO::PARAM_INT);
            $stmt->bindValue(':coor_lat', $data->data->coor_lat);
            $stmt->bindValue(':coor_lng', $data->data->coor_lng);

            $stmt->execute();

            $sql = "SELECT LAST_INSERT_ID() AS id";
            $stmt = $db->prepare($sql);
            $stmt->execute();        

            $ind_id = $stmt->fetch(PDO::FETCH_OBJ);

            $sek5_id = "";
            if(isset($data->sek5Data)){
                //Insert Seksyen 5  
                $sql = "INSERT INTO `sek5` 
                        (`ind_id`, `appointment_date`, `remark`, `issue_date`) 
                        VALUES
                        (:ind_id, :appointment_date, :remark, CURDATE())";

                $stmt = $db->prepare($sql);
                $stmt->bindParam(':ind_id', $ind_id->id, PDO::PARAM_INT);
                $stmt->bindParam(':appointment_date', $data->sek5Data->appointment_date, PDO::PARAM_STR);            
                $stmt->bindParam(':remark', $data->sek5Data->remark, PDO::PARAM_STR);

                $stmt->execute();

                $sql = "SELECT LAST_INSERT_ID() AS id";
                $stmt = $db->prepare($sql);
                $stmt->execute();        

                $sek5_id = $stmt->fetch(PDO::FETCH_OBJ);
            }
            
            $sek8_id = "";
            if(isset($data->sek8Data)){
                //Insert Seksyen 8
                $sql = "INSERT INTO `sek8` 
                (`ind_id`, `issue_date`, `checking_date`, `remark`,
                `chkbx1`, `chkbx2`, `chkbx3`, `chkbx4`, `chkbx5`, `chkbx6`, `chkbx7`, `chkbx8`, `chkbx9`, `chkbx10`, `chkbx11`, `chkbx12`, `chkbx13`) 
                VALUES
                (:ind_id, CURDATE(), :checking_date, :remark,
                :chkbx1, :chkbx2, :chkbx3, :chkbx4, :chkbx5, :chkbx6, :chkbx7, :chkbx8, :chkbx9, :chkbx10, :chkbx11, :chkbx12, :chkbx13)";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':ind_id', $ind_id->id, PDO::PARAM_INT);
                $stmt->bindValue(':checking_date', $data->sek8Data->checking_date, PDO::PARAM_STR);            
                $stmt->bindValue(':remark', $data->sek8Data->remark, PDO::PARAM_STR);
                $stmt->bindValue(':chkbx1', (int)$data->sek8Data->chkbx1, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx2', (int)$data->sek8Data->chkbx2, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx3', (int)$data->sek8Data->chkbx3, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx4', (int)$data->sek8Data->chkbx4, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx5', (int)$data->sek8Data->chkbx5, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx6', (int)$data->sek8Data->chkbx6, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx7', (int)$data->sek8Data->chkbx7, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx8', (int)$data->sek8Data->chkbx8, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx9', (int)$data->sek8Data->chkbx9, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx10', (int)$data->sek8Data->chkbx10, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx11', (int)$data->sek8Data->chkbx11, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx12', (int)$data->sek8Data->chkbx12, PDO::PARAM_INT);
                $stmt->bindValue(':chkbx13', (int)$data->sek8Data->chkbx13, PDO::PARAM_INT);

                $stmt->execute();

                $sql = "SELECT LAST_INSERT_ID() AS id";
                $stmt = $db->prepare($sql);
                $stmt->execute();        

                $sek8_id = $stmt->fetch(PDO::FETCH_OBJ);
            }

            $exhibit_id = "";
            if(isset($data->exhibitData)){
                //Insert Exhibit
                $sql = "INSERT INTO `exhibit` 
                (`ind_id`, `po_full_name`, `po_ic_no`, `acceptance`, `issue_date`) 
                VALUES
                (:ind_id, :po_full_name, :po_ic_no, :acceptance, CURDATE())";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':ind_id', $ind_id->id, PDO::PARAM_INT);                
                $stmt->bindValue(':po_full_name', $data->exhibitData->po_full_name, PDO::PARAM_STR);
                $stmt->bindValue(':po_ic_no', $data->exhibitData->po_ic_no, PDO::PARAM_STR);            
                $stmt->bindValue(':acceptance', (int)$data->exhibitData->acceptance, PDO::PARAM_INT);

                $stmt->execute();

                $sql = "SELECT LAST_INSERT_ID() AS id";
                $stmt = $db->prepare($sql);
                $stmt->execute();        

                $exhibit_id = $stmt->fetch(PDO::FETCH_OBJ);
            }

            echo '{ "status"    : "1",
                    "ind"       : ' .json_encode($ind_id). ',
                    "sek5"      : ' .json_encode($sek5_id). ',
                    "sek8"      : ' .json_encode($sek8_id). ',
                    "exhibit"   : ' .json_encode($exhibit_id). '
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

//Get ind list by user_id
$app->post('/api/ind/indList_pdk', function(Request $request, Response $response){
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
            $sql = "SELECT `ind_id`, `p_shortAddr`, `po_name`, `last_modified` FROM `ind` 
                    WHERE `user_id` = :user_id AND `issue_date` = CURDATE() AND `deleted_date` IS NULL";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_INT);
            $stmt->execute();

            $inds = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => '1',
                'data' => $inds
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

//Get ind list by user_id
$app->post('/api/ind/indList_web', function(Request $request, Response $response){
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
            $sql = "SELECT `i`.`ind_id`, `u`.`full_name`, `a`.`team`,`i`.`issue_date`, `i`.`p_shortAddr`, `i`.`po_name` 
                    FROM `ind` AS `i`
                    INNER JOIN `user` AS `u`
                        ON `i`.`user_id` = `u`.`user_id`
                    INNER JOIN `assignment` AS `a`
                        ON `i`.`assignment_id` = `a`.`assignment_id`
                    WHERE `i`.`deleted_date` IS NULL
                    ORDER BY `issue_date` DESC";

            $stmt = $db->query($sql);
            $inds = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => '1',
                'data' => $inds
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

//Get ind from ind_id
$app->post('/api/ind/get/{id}', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);
    $ind_id = $request->getAttribute('id');         
    
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();
            
            //select from ind table
            $sql = "SELECT 
                        `i`.`assignment_id`, `i`.`user_id`, `i`.`issue_date`, `i`.`area_inspection`, `i`.`p_cooperation`, `i`.`p_close`, `i`.`p_empty`, `i`.`p_shortAddr`, `i`.`po_name`, `i`.`po_id`, `i`.`no_familyMember`, 
                        `i`.`no_fever`, `i`.`no_out_breeding`, `i`.`no_in_breeding`, `i`.`container_type`, `i`.`no_pot_out_breeding`, `i`.`no_pot_in_breeding`, `i`.`abating_amount`, 
                        `i`.`abating_measure_type`, `i`.`act_destroy`, `i`.`act_education`, `i`.`act_pamphlet`, `i`.`coor_lat`, `i`.`coor_lng`, `u`.`full_name`
                    FROM `ind` AS `i`
                    INNER JOIN `user` AS `u` 
                        ON `i`.`user_id` = `u`.`user_id` 
                    WHERE `ind_id` = :ind_id";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':ind_id', $ind_id, PDO::PARAM_INT);
            $stmt->execute();

            $ind = $stmt->fetch(PDO::FETCH_OBJ);

            //select from exhibit table
            $sql = "SELECT `exhibit_id`, `issue_date`, `po_full_name`, `po_ic_no`, `acceptance`, `floor_plan_path`, `premise_location_path` 
                    FROM `exhibit`
                    WHERE `ind_id` = :ind_id";
            
            $stmt1 = $db->prepare($sql);
            $stmt1->bindParam(':ind_id', $ind_id, PDO::PARAM_INT);
            $stmt1->execute();

            $exhibit = $stmt1->fetch(PDO::FETCH_OBJ);

            $exhibitItems = false;
            if(isset($exhibit->exhibit_id)){

                //select from exhibitItem table
                $sql = "SELECT `exhibit_item_id`, `code`, `type`, `s3_path` 
                        FROM `exhibit_item`
                        WHERE `exhibit_id` = :exhibit_id AND `deleted_date` IS NULL";
                
                $stmt2 = $db->prepare($sql);
                $stmt2->bindParam(':exhibit_id', $exhibit->exhibit_id, PDO::PARAM_INT);
                $stmt2->execute();

                $exhibitItems = $stmt2->fetchAll(PDO::FETCH_OBJ);
            }

            //select from sek8 table
            $sql = "SELECT 
                        `sek8_id`, `checking_date`, `chkbx1`, `chkbx2`, `chkbx3`, `chkbx4`, `chkbx5`, `chkbx6`, `chkbx7`, `chkbx8`, `chkbx9`, `chkbx10`, 
                        `chkbx11`, `chkbx12`, `chkbx13`, `remark` 
                    FROM `sek8`
                    WHERE `ind_id` = :ind_id";
            
            $stmt3 = $db->prepare($sql);
            $stmt3->bindParam(':ind_id', $ind_id, PDO::PARAM_INT);
            $stmt3->execute();

            $sek8 = $stmt3->fetch(PDO::FETCH_OBJ);           
            
            //select from sek5 table
            $sql = "SELECT `sek5_id`, `appointment_date`, `remark`, `last_modified` 
                    FROM `sek5`
                    WHERE `ind_id` = :ind_id";
            
            $stmt4 = $db->prepare($sql);
            $stmt4->bindParam(':ind_id', $ind_id, PDO::PARAM_INT);
            $stmt4->execute();

            $sek5 = $stmt4->fetch(PDO::FETCH_OBJ);  

            return $response->withJson([
                'status' => '1',
                'ind_id' => $ind_id,
                'ind' => $ind,
                'exhibit' => $exhibit,
                'exhibitItems' => $exhibitItems,
                'sek8' => $sek8,
                'sek5' => $sek5
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

$app->post('/api/ind/delete/{id}', function(Request $request, Response $response){
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
            $ind_id = $request->getAttribute('id');    
            $sql = "UPDATE `ind` SET `deleted_date` = SYSDATE(), `deleted_by` = :user_id WHERE `ind_id` = :ind_id";
            $stmt = $db->prepare($sql);        
            $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_INT);                    
            $stmt->bindParam(':ind_id', $ind_id, PDO::PARAM_INT);        
            $stmt->execute();

            return $response->withJson([
                'status' => '1'
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