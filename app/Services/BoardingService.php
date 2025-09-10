<?php

namespace app\Services;

use core\Database;
use app\Models;


class BoardingService
{
    private static function addToDatabase():bool {
        return true;
    }

    private function creditReferer($refererId):void {

    }

    public static function userLogin():bool {
        $user_data = array();

        $user_data['email'] = $_POST['email'];
        $user_data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $user = Models\User::findByEmail($user_data['email']);
        if($user){
            $user_password = $user['password_hash'];
            if(password_verify($_POST['password'], $user_password)){
                $_SESSION['is_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public static function registerUser():bool {
        $user_data = array();

        $user_data['referralCode'] = $_POST['referralCode'];
        $user_data['firstName'] = $_POST['firstName'];
        $user_data['lastName'] = $_POST['lastName'];
        $user_data['email'] = $_POST['email'];
        $user_data['phoneNumber'] = $_POST['phoneNumber'];
        $user_data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $result = Models\User::create($user_data);
        if(!$result){
            return false;
        }
        $_SESSION['is_logged_in'] = true;
        $_SESSION['user'] = $user_data['email'];

        return true;
    }

}