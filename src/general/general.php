<?php
    class GenError{
        public static function unauthorizedAccess(){
            echo '{ "status"    : "fail",
                    "message"   : "Invalid session" }
            ';
        }

        public static function unexpectedError(PDOException $e){
            echo '{ "status"    : "fail",
                    "message"   : '.$e->getMessage().' }';
        }

        public static function authorizeUser($token) {
            $db = new db();

            try {
                //get DB object and connect
                $db = $db->connect();
                //execute statement
                $sql = "SELECT * FROM `user` WHERE `token` = :token";

                $stmt = $db->prepare($sql);
                $stmt->bindParam(':token', $token, PDO::PARAM_STR);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_OBJ);

                return $user;

            }
            catch(PDOException $e){
                GenError::unexpectedError($e);
            }
            finally{ $db = null; }
        }
    }
?>