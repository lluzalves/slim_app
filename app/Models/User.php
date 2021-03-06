<?php

namespace App\Models;

use DateTime;
use PHPMailer\PHPMailer\PHPMailer;

class User extends BaseModel
{

    public $timestamps = true;
    protected $currentUser;
    protected $fillable = ['name', 'password'];

    static function user()
    {
        return new User();
    }

    public function output()
    {
        $output = [];
        $output['id'] = $this->id;
        $output['email'] = $this->email;
        $output['token_expiration'] = $this->token_expiration;
        $output['name'] = $this->name;
        $output['prontuario'] = $this->prontuario;
        $output['user_uri'] = '/users/' . $this->id;
        $output['role'] = $this->role;
        $output['created_at'] = $this->created_at->toDateTimeString();;
        $output['updated_at'] = $this->updated_at->toDateTimeString();;

        return $output;
    }


    public function create($request)
    {
        $user = new User();

        $user->name = $request->getParsedBodyParam('name', '');
        $user->email = $request->getParsedBodyParam('email', '');
        $user->role = $request->getParsedBodyParam('role' . '');
        $user->prontuario = $request->getParsedBodyParam('prontuario', '');
        $user->password = password_hash($request->getParsedBodyParam('password', ''), PASSWORD_BCRYPT);
        $user->token = $token = bin2hex(random_bytes(64));
        $user->token_expiration = date('Y-m-d+23:59:59');

        if (empty($user->name) or empty($user->email) or empty($user->password)) {
            return null;
        }

        $user->save();

        return $user;
    }

    public function tokenOutput()
    {
        $output = [];
        $output['email'] = $this->email;
        $output['token'] = $this->token;

        return $output;
    }

    public function verify($token)
    {
        $user = User::where('token', '=', $token)->take(1)->get();

        $this->currentUser = $user[0];

        if (empty($this->currentUser)) {
            return false;
        }

        return ($user[0]->exists()) ? true : false;
    }


    private function isTokenExpired($currentUser)
    {
        $date = new DateTime($currentUser->token_expiration);

        return $date->format('Y-m-d') < date('Y-m-d');
    }


    public function retrieveUserByEmail($email)
    {
        $user = User::where('email', '=', $email)->take(1)->get();

        $this->currentUser = $user[0];

        return $this->currentUser;
    }

    public function retrieveUserByProntuario($prontuario)
    {
        $user = User::where('prontuario', '=', $prontuario)->take(1)->get();

        $this->currentUser = $user[0];

        return $this->currentUser;
    }

    public function remove($id)
    {
        $user = User::where('id', '=', $id)->take(1)->get();

        if ($user[0]->exists()) {
            return $user->delete();
        }
        return $user[0]->exists();
    }

    public function generateToken($email)
    {
        $user = User::where('email', '=', $email)->take(1)->get();

        $user[0]->token = bin2hex(random_bytes(64));

        if ($user[0]->save()) {
            return $user[0]->token;
        }

        return empty(true);
    }

    public function getUser($token)
    {
        return User::where('token', '=', $token)->take(1)->get();
    }

    public function getUserById($id)
    {
        return User::where('id', '=', $id)->take(1)->get();
    }

    public function updateEmail($token, $newEmail)
    {
        $user = User::where('token', '=', $token)->take(1)->get();

        if (!empty($user[0])) {
            $user[0]->email = $newEmail;
            return $user[0]->save();
        } else {
            return false;
        }

    }

    public function updateToken($token, $password)
    {
        $user = User::where('token', '=', $token)->take(1)->get();

        $user->password = password_hash($password, PASSWORD_BCRYPT);

        return $user->save();
    }

    public function updatePassword($email)
    {
        $bytes = random_bytes(8);
        $password = bin2hex($bytes);
        $user = User::user()->retrieveUserByEmail($email);
        $newpassword = password_hash($password, PASSWORD_BCRYPT);
        $user->password = $newpassword;
        $user->save();

        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'ifspdocs@gmail.com';
            $mail->Password = 'Ifsp8686!';
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            $mail->setFrom('ifspdocs@gmail.com', 'ifdocs admin');
            $mail->addAddress($user->email);

            $mail->Subject = 'ifdocs - Oi, sua nova senha chegou';
            $mail->Body = "A sua nova senha : " . $password;
            $mail->send();
            return true;

        } catch (Exception $e) {
            $error = $mail->ErrorInfo;
            return false;
        }
    }

    public function isAdmin($email)
    {
        $user = User::where('email', '=', $email)->take(1)->get();

        return $user[0]->role === 'admin';
    }
}