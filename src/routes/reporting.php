<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//Get ind list by user_id
$app->post('/api/dailyReport/get', function(Request $request, Response $response){
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
            $assignment_ids = implode(',', $data->assignment_ids);
            $sql = "SELECT
                        COUNT(*) AS `total`,
                        `assignment_id`,
                        SUM(CASE WHEN `p_close` = 0 THEN 1 ELSE 0 END ) AS `checked`,
                        SUM(CASE WHEN (`area_inspection` = 0 AND `p_close` = 1) THEN 1 ELSE 0 END) `close`,
                        SUM(CASE WHEN (`area_inspection` = 0 AND `p_empty` = 1) THEN 1 ELSE 0 END) `empty`,
                        SUM(CASE WHEN (`area_inspection` = 1) THEN 1 ELSE 0 END) `surrounding`,
                        SUM(CASE WHEN 1 THEN 1 ELSE 0 END) `visited`,
                        SUM(EXISTS (SELECT * FROM `exhibit` WHERE `ind`.`ind_id` = `exhibit`.`ind_id`)) `premise_positive_compound`,
                        SUM(CASE 
                                WHEN 
                                    (`area_inspection` = 1 OR 
                                    (`p_close` = 1 OR `p_empty` = 1)) AND
                                    (`no_out_breeding` > 0 OR `no_in_breeding` > 0) 
                                    THEN 1 ELSE 0 END
                            ) `area_positive_surrounding`,
                        SUM(CASE 
                                WHEN 
                                    (`area_inspection` = 1 OR 
                                    (`p_close` = 1 OR `p_empty` = 1)) AND
                                    (`no_out_breeding` > 0 OR `no_in_breeding` > 0) 
                                THEN (`no_out_breeding` + `no_in_breeding`) ELSE 0 END
                            ) `container_positive_surrounding`,
                        SUM(CASE WHEN `no_in_breeding` > 0 OR `no_out_breeding` > 0 THEN 1 ELSE 0 END) `total_breeding`,
                        SUM(`no_pot_out_breeding` + `no_pot_in_breeding`) `total_pot_breeding`,
                        SUM(CASE WHEN NOT(`abating_amount` = 0) THEN 1 ELSE 0 END) `total_abating`,                        
                        SUM(CASE WHEN (NOT(`abating_amount` = 0) AND `abating_measure_type` = 0) THEN `abating_amount` ELSE 0 END) `abating_amount_g`,
                        SUM(CASE WHEN (NOT(`abating_amount` = 0) AND `abating_measure_type` = 1) THEN `abating_amount` ELSE 0 END) `abating_amount_ml`,
                        SUM(CASE WHEN (`area_inspection` = 0) THEN `no_fever` ELSE 0 END) `ACD`,
                        SUM(EXISTS (SELECT * FROM `sek8` WHERE `ind`.`ind_id` = `sek8`.`ind_id`)) `s8`,
                        SUM(EXISTS (SELECT * FROM `sek5` WHERE `ind`.`ind_id` = `sek5`.`ind_id`)) `s5`
                    FROM `ind`
                    WHERE `deleted_date` IS NULL AND `assignment_id` IN (";       

            $comma = '';
            for($i = 0; $i < count($data->assignment_ids); $i++){
                $sql .= $comma . ':assignment_id_' . $i;
                $comma = ',';
            }

            $sql .=") GROUP BY `assignment_id`";

            // SUM() `container_positive_compound`,

            $stmt = $db->prepare($sql);
            for($i = 0; $i < count($data->assignment_ids); $i++){
                $stmt->bindParam(':assignment_id_' . $i, $data->assignment_ids[$i]);
            }
            $stmt->execute();

            $report_data = $stmt->fetchAll(PDO::FETCH_OBJ);

            $sql = "SELECT 
                        `i`.`assignment_id`,
                        COUNT(*) AS `container_positive_compound`
                    FROM `ind` AS `i`
                    JOIN(
                        SELECT `e`.`ind_id`, `e`.`exhibit_id`, `ei`.`exhibit_item_id` FROM `exhibit` AS `e`
                        JOIN `exhibit_item` AS `ei`
                        ON `ei`.`exhibit_id` = `e`.`exhibit_id`
                        WHERE `ei`.`deleted_date` IS NULL
                    ) AS `e_data`
                    ON `i`.`ind_id` = `e_data`.`ind_id`
                    WHERE `i`.`deleted_date` IS NULL AND `i`.`assignment_id` IN (";
            
            $comma = '';
            for($i = 0; $i < count($data->assignment_ids); $i++){
                $sql .= $comma . ':assignment_id_' . $i;
                $comma = ',';
            }
            
            $sql .=")GROUP BY `i`.`assignment_id`";

            $stmt = $db->prepare($sql);
            for($i = 0; $i < count($data->assignment_ids); $i++){
                $stmt->bindParam(':assignment_id_' . $i, $data->assignment_ids[$i]);
            }
            $stmt->execute();

            $report_data2 = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => '1',
                'data' => $report_data,
                'data2' => $report_data2
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
$app->post('/api/dailyReport/getList', function(Request $request, Response $response){
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
            $sql = "SELECT `dr`.*, `u`.`full_name` AS `created_by`
                    FROM `daily_report` AS `dr`
                    INNER JOIN `user` AS `u`
                        ON `dr`.`user_id` = `u`.`user_id`
                    WHERE `dr`.`deleted_date` IS NULL
                    ORDER BY `dr`.`created_date` DESC";

            $stmt = $db->query($sql);
            $reports = $stmt->fetchAll(PDO::FETCH_OBJ);

            return $response->withJson([
                'status' => '1',
                'data' => $reports
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
