<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Aws\S3\Exception\S3Exception;
use Slim\Http\UploadedFile;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Credentials\CredentialProvider;

//note to upload to EC2 instance
//1. do not forget to change \uploads to /uploads in $container['upload_directory'] = dirname( __DIR__, 2 ) . '\uploads'; 
//2. remove $provider for aws due to EC2 instance app uses IAM

$container = $app->getContainer();
$container['upload_directory'] = dirname( __DIR__, 2 ) . '/uploads'; 
$container['download_directory'] = dirname( __DIR__, 2 ) . '/downloads'; 

$container['bucketName'] = 'ptp-dvc';
// $provider = CredentialProvider::ini(); //for local

// Cache the results in a memoize function to avoid loading and parsing
// the ini file on every API operation.

// $provider = CredentialProvider::memoize($provider);  //for local
$container['s3'] = new S3Client([
    'version' => 'latest',
    'region'  => 'ap-southeast-1'
    // 'credentials' => $provider //for local
]);

function generateFileName($directory, $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
    $filename = sprintf('%s.%0.8s', $basename, $extension);
    
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}

function removeTemporaryFiles($directory){
    try{
        $files = glob($directory .DIRECTORY_SEPARATOR. "*"); // get all file names
        
        foreach($files as $file){ // iterate files
            if(is_file($file)){
                unlink($file); // delete file
            }
        }
    }
    catch(Exception $e){
        echo json_encode($e);
    }
}

$app->post('/api/upload/exhibit_item', function(Request $request, Response $response) {
    $db = new db();
    $directory = $this->get('upload_directory');
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    //extend execution time.
    ini_set('max_execution_time', 400);
    
    $user_id    = $request->getParam('user_id');
    $token      = $request->getParam('token'); 
    $exhibit_id =  $request->getParam('exhibit_id');    
    $systemToken= apiToken($user_id);
    
    if($token == $systemToken){
        $files = $request->getUploadedFiles();

        if (empty($files['file'])) {
            throw new \RuntimeException('Expected a newfile');
        }
    
        $file = $files['file'];
    
        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = generateFileName($directory, $file);
            
            try{
                $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key' => "exhibit/{$exhibit_id}/{$filename}",
                    'SourceFile' => $directory . DIRECTORY_SEPARATOR . $filename
                ]);

                try{
                    $code       =  $request->getParam('code');
                    $type       =  $request->getParam('type');
                    $s3_path    =  $filename;
                    
                    //get DB object and connect
                    $db = $db->connect();

                    //prepare state and execute     
                    $sql = "INSERT INTO `exhibit_item` 
                            (`exhibit_id`, `code`, `type`, `s3_path`) 
                            VALUES
                            (:exhibit_id, :code, :type, :s3_path)";

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':exhibit_id', $exhibit_id, PDO::PARAM_INT);
                    $stmt->bindParam(':code', $code, PDO::PARAM_STR);            
                    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
                    $stmt->bindParam(':s3_path', $s3_path, PDO::PARAM_STR);

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
            
            catch(S3Exception $e){
                return $response
                ->withJson([
                    'status' => '0',
                    'error' => $e->getMessage()
                ])
                ->withStatus(415);
            }
        }
        else{
            return $response
                ->withJson([
                    'status' => '0',
                    'error' => 'Nothing was uploaded'
                ])
                ->withStatus(415);
        }
    }
    else{
        GenError::unauthorizedAccess();
    }
});

$app->post('/api/upload/premise_location_drawing', function(Request $request, Response $response) {
    $db = new db();
    $directory = $this->get('upload_directory');
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');
    
    $user_id    = $request->getParam('user_id');
    $token      = $request->getParam('token'); 
    $exhibit_id =  $request->getParam('exhibit_id');    
    $systemToken= apiToken($user_id);
    
    if($token == $systemToken){
        $files = $request->getUploadedFiles();

        if (empty($files['file'])) {
            throw new \RuntimeException('Expected a newfile');
        }
    
        $file = $files['file'];
    
        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = generateFileName($directory, $file);
            
            try{
                $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key' => "exhibit/{$exhibit_id}/{$filename}",
                    'SourceFile' => $directory . DIRECTORY_SEPARATOR . $filename
                ]);

                try{
                    $premise_location_path = $filename;
                    
                    //get DB object and connect
                    $db = $db->connect();

                    //prepare state and execute 
                    $sql = "UPDATE `exhibit` 
                            SET `premise_location_path` = :premise_location_path
                            WHERE `exhibit_id` = :exhibit_id";

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':exhibit_id', $exhibit_id, PDO::PARAM_INT);
                    $stmt->bindParam(':premise_location_path', $premise_location_path, PDO::PARAM_STR);

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
            
            catch(S3Exception $e){
                return $response
                ->withJson([
                    'status' => '0',
                    'error' => $e->getMessage()
                ])
                ->withStatus(415);
            }
        }
        else{
            return $response
                ->withJson([
                    'status' => '0',
                    'error' => 'Nothing was uploaded'
                ])
                ->withStatus(415);
        }
    }
    else{
        GenError::unauthorizedAccess();
    }
});

$app->post('/api/upload/floor_plan_drawing', function(Request $request, Response $response) {
    $db = new db();
    $directory = $this->get('upload_directory');
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');
    
    $user_id    = $request->getParam('user_id');
    $token      = $request->getParam('token'); 
    $exhibit_id =  $request->getParam('exhibit_id');    
    $systemToken= apiToken($user_id);
    
    if($token == $systemToken){
        $files = $request->getUploadedFiles();

        if (empty($files['file'])) {
            throw new \RuntimeException('Expected a newfile');
        }
    
        $file = $files['file'];
    
        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = generateFileName($directory, $file);
            
            try{
                $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key' => "exhibit/{$exhibit_id}/{$filename}",
                    'SourceFile' => $directory . DIRECTORY_SEPARATOR . $filename
                ]);

                try{
                    $floor_plan_path = $filename;
                    
                    //get DB object and connect
                    $db = $db->connect();

                    //prepare state and execute 
                    $sql = "UPDATE `exhibit` 
                            SET `floor_plan_path` = :floor_plan_path
                            WHERE `exhibit_id` = :exhibit_id";

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':exhibit_id', $exhibit_id, PDO::PARAM_INT);
                    $stmt->bindParam(':floor_plan_path', $floor_plan_path, PDO::PARAM_STR);

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
            
            catch(S3Exception $e){
                return $response
                ->withJson([
                    'status' => '0',
                    'error' => $e->getMessage()
                ])
                ->withStatus(415);
            }
        }
        else{
            return $response
                ->withJson([
                    'status' => '0',
                    'error' => 'Nothing was uploaded'
                ])
                ->withStatus(415);
        }
    }
    else{
        GenError::unauthorizedAccess();
    }
});

$app->post('/api/getDrawingsURL', function(Request $request, Response $response) {
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            $exhibit_id =  $data->data->exhibit_id; 
            $floor_plan_path = $data->data->floor_plan_path; 
            $premise_location_path = $data->data->premise_location_path;             

            $floor_plan_result = $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key'    => "exhibit/{$exhibit_id}/{$floor_plan_path}",
                'ResponseContentDisposition' => 'attachment; filename="premiselocation.png"',
            ]);            

            $floor_plan_request = $s3->createPresignedRequest($floor_plan_result, '+10 minutes');
            // Get the actual presigned-url
            $floor_plan_presignedUrl = (string) $floor_plan_request->getUri();

            $premise_location_result = $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key'    => "exhibit/{$exhibit_id}/{$premise_location_path}",
                'ResponseContentDisposition' => 'attachment; filename="floorplan.png"',
            ]);            

            $premise_location_request = $s3->createPresignedRequest($premise_location_result, '+10 minutes');

            // Get the actual presigned-url
            $premise_location_presignedUrl = (string) $premise_location_request->getUri();

            return $response->withJson([
                'status' => '1',
                'floor_plan' => $floor_plan_presignedUrl,
                'premise_location' => $premise_location_presignedUrl
                // 'floor_plan' => '',
                // 'premise_location' => ''
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

$app->post('/api/getExhibitItemURL', function(Request $request, Response $response) {
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            $exhibit_id =  $data->data->exhibit_id; 
            $s3_path = $data->data->s3_path; 

            $result = $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key'    => "exhibit/{$exhibit_id}/{$s3_path}",
                'ResponseContentDisposition' => 'attachment; filename="exhibitItem.png"',                
            ]);            

            $request = $s3->createPresignedRequest($result, '+10 minutes');
            // Get the actual presigned-url
            $presignedUrl = (string) $request->getUri();

            return $response->withJson([
                'status' => '1',
                's3_path' => $presignedUrl
                // 's3_path' => ''
                
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

$app->post('/api/clearExhibitFolder', function(Request $request, Response $response) {
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);
    $exhibit_id = 19;

    if($token == $systemToken)
    {
        try{
            $result = $s3->deleteMatchingObjects($bucketName, "exhibit/{$exhibit_id}/");          

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

//update ind and clear exhibit folder in s3
$app->post('/api/ind/update', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            //update InD
            $sql = "UPDATE `ind` 
                    SET 
                        `area_inspection` = :area_inspection, `p_cooperation` = :p_cooperation, `p_close` = :p_close, `p_empty` = :p_empty, `p_shortAddr` = :p_shortAddr, `po_name` = :po_name, `po_id` = :po_id,
                        `no_familyMember` = :no_familyMember,`no_fever` = :no_fever, `no_out_breeding` = :no_out_breeding, `no_in_breeding` = :no_in_breeding, 
                        `container_type` = :container_type, `no_pot_out_breeding` = :no_pot_out_breeding, `no_pot_in_breeding` = :no_pot_in_breeding, `abating_amount` = :abating_amount, 
                        `abating_measure_type` = :abating_measure_type, `act_destroy` = :act_destroy, `act_education` = :act_education, `act_pamphlet` = :act_pamphlet, `coor_lat` = :coor_lat, `coor_lng` = :coor_lng
                    WHERE `ind_id` = :ind_id";

            $stmt = $db->prepare($sql);
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
            $stmt->bindValue(':abating_amount', $data->data->abating_amount);
            $stmt->bindValue(':abating_measure_type', (int)$data->data->abating_measure_type, PDO::PARAM_INT);
            $stmt->bindValue(':act_destroy', (int)$data->data->act_destroy, PDO::PARAM_INT);
            $stmt->bindValue(':act_education', (int)$data->data->act_education, PDO::PARAM_INT);
            $stmt->bindValue(':act_pamphlet', (int)$data->data->act_pamphlet, PDO::PARAM_INT);
            $stmt->bindValue(':coor_lat', $data->data->coor_lat);
            $stmt->bindValue(':coor_lng', $data->data->coor_lng);
            $stmt->bindValue(':ind_id', $data->data->ind_id, PDO::PARAM_INT);            

            $stmt->execute();

            $sek5_id = "";
            if(isset($data->sek5Data)){
                //update Seksyen 5  
                $sql = "UPDATE `sek5` 
                        SET `appointment_date` = :appointment_date, `remark` = :remark
                        WHERE `ind_id` = :ind_id";

                $stmt = $db->prepare($sql);
                $stmt->bindParam(':appointment_date', $data->sek5Data->appointment_date, PDO::PARAM_STR);            
                $stmt->bindParam(':remark', $data->sek5Data->remark, PDO::PARAM_STR);
                $stmt->bindValue(':ind_id', $data->data->ind_id, PDO::PARAM_INT);                            

                $stmt->execute();
            }
            
            $sek8_id = "";
            if(isset($data->sek8Data)){
                //update Seksyen 8
                $sql = "UPDATE `sek8` 
                        SET 
                            `checking_date` = :checking_date, `chkbx1` = :chkbx1, `chkbx2` = :chkbx2, `chkbx3` = :chkbx3, `chkbx4` = :chkbx4, `chkbx5` = :chkbx5, 
                            `chkbx6` = :chkbx6, `chkbx7` = :chkbx7, `chkbx8` = :chkbx8, `chkbx9` = :chkbx9, `chkbx10` = :chkbx10, `chkbx11` = :chkbx11, `chkbx12` = :chkbx12, 
                            `chkbx13` = :chkbx13, `remark` = :remark 
                        WHERE `ind_id` = :ind_id";

                $stmt = $db->prepare($sql);
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
                $stmt->bindValue(':ind_id', $data->data->ind_id, PDO::PARAM_INT);            

                $stmt->execute();
            }

            if(isset($data->exhibitData)){
                $exhibit_id = $data->exhibitData->exhibit_id;

                //update the exhibit
                $sql = "UPDATE `exhibit` 
                        SET `po_full_name` = :po_full_name, `po_ic_no` = :po_ic_no, `acceptance` = :acceptance
                        WHERE `ind_id` = :ind_id AND `exhibit_id` = :exhibit_id";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':po_full_name', $data->exhibitData->po_full_name, PDO::PARAM_STR);
                $stmt->bindValue(':po_ic_no', $data->exhibitData->po_ic_no, PDO::PARAM_STR);            
                $stmt->bindValue(':acceptance', (int)$data->exhibitData->acceptance, PDO::PARAM_INT);
                $stmt->bindValue(':ind_id', $data->data->ind_id, PDO::PARAM_INT);   
                $stmt->bindValue(':exhibit_id', $exhibit_id, PDO::PARAM_INT);                                                                     

                $stmt->execute();

                //delete the folder of exhibit_id
                $path = "exhibit/{$exhibit_id}/";
                $result = $s3->deleteMatchingObjects($bucketName, "exhibit/{$exhibit_id}/");     

                //delete exhibit_item rows 
                $sql = "UPDATE `exhibit_item` 
                        SET `deleted_date` = NOW() 
                        WHERE `exhibit_id` = :exhibit_id";

                $stmt = $db->prepare($sql);
                $stmt->bindValue(':exhibit_id', $exhibit_id, PDO::PARAM_INT);                                                                     
                $stmt->execute();
            }

            echo '{ "status"    : "1" }';
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

$app->post('/api/upload/daily_report', function(Request $request, Response $response) {
    $db = new db();
    $directory = $this->get('upload_directory');
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');
    
    $user_id    = $request->getParam('user_id');
    $token      = $request->getParam('token'); 
    $systemToken= apiToken($user_id);
    
    if($token == $systemToken){
        $files = $request->getUploadedFiles();

        if (empty($files['file'])) {
            throw new \RuntimeException('Expected a newfile');
        }
    
        $file = $files['file'];
        $created_date = $request->getParam('created_date');

        if ($file->getError() === UPLOAD_ERR_OK) {
            $filename = generateFileName($directory, $file);
            
            try{
                $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key' => "daily_report/{$created_date}/{$filename}",
                    'SourceFile' => $directory . DIRECTORY_SEPARATOR . $filename
                ]);
                unlink($directory . DIRECTORY_SEPARATOR . $filename);
                try{
                    $daily_report_path = $filename;
                    
                    //get DB object and connect
                    $db = $db->connect();

                    //prepare state and execute 
                    $sql = "INSERT INTO `daily_report`
                                (`created_date`, `user_id`, `s3_path`) 
                            VALUES 
                                (SYSDATE(), :user_id, :s3_path)";

                    $stmt = $db->prepare($sql);
                    $stmt->bindParam(':s3_path', $daily_report_path, PDO::PARAM_STR);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
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
            
            catch(S3Exception $e){
                return $response
                ->withJson([
                    'status' => '0',
                    'error' => $e->getMessage()
                ])
                ->withStatus(415);
            }
        }
        else{
            return $response
                ->withJson([
                    'status' => '0',
                    'error' => 'Nothing was uploaded'
                ])
                ->withStatus(415);
        }
    }
    else{
        GenError::unauthorizedAccess();
    }
});

//update ind and clear exhibit folder in s3
$app->post('/api/dailyReport/delete', function(Request $request, Response $response){
    $db = new db();
    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    if($token == $systemToken)
    {
        try{
            //get DB object and connect
            $db = $db->connect();

            //prepare state and execute     
            //update InD
            $sql = "UPDATE `daily_report` 
                    SET  `deleted_date` = SYSDATE()
                    WHERE `daily_report_id` = :daily_report_id";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':daily_report_id', $data->daily_report_id, PDO::PARAM_INT);                                                                     
            $stmt->execute();

            //delete the report from s3
            if(!empty($data->s3_path) && !empty($data->created_date)){
                $path = "daily_report/{$data->created_date}/{$data->s3_path}";
                $result = $s3->deleteMatchingObjects($bucketName, $path); 

                return $response->withJson([
                    'status' => '1'
                ])->withStatus(200);
            }    

            else{
                return $response->withJson([
                    'status' => '0',
                    'error' => 'nothing was deleted from s3',
                ])->withStatus(415);
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

$app->post('/api/getDailyReport', function(Request $request, Response $response) {
    $s3 = $this->get('s3');
    $bucketName = $this->get('bucketName');

    $data = json_decode($request->getBody());
    $token = $data->token;
    $systemToken = apiToken($data->user_id);

    if($token == $systemToken)
    {
        try{
            $created_date =  $data->created_date;        
            $s3_path =  $data->s3_path;                                

            $daily_report_result = $s3->getCommand('GetObject', [
                'Bucket' => $bucketName,
                'Key'    => "daily_report/{$created_date}/{$s3_path}",
                'ResponseContentDisposition' => 'attachment; filename="dailyReport.xlsx"',
            ]);            

            $daily_report_request = $s3->createPresignedRequest($daily_report_result, '+10 minutes');
            // Get the actual presigned-url
            $daily_report_presignedUrl = (string) $daily_report_request->getUri();

            return $response->withJson([
                'status' => '1',
                'daily_report_uri' => $daily_report_presignedUrl
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