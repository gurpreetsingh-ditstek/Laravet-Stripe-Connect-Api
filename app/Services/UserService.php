<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtp;
use App\Models\{User, UserOtp};
use DB;
use Auth;

class UserService
{
    protected $success;
    protected $failure;
    protected $obj;

    public function __construct($obj)
    {
        $this->obj = $obj;
        $this->success = Response::HTTP_OK;
        $this->failure = Response::HTTP_BAD_REQUEST;
    }

    public function register($request)
    {
        $user = User::updateOrCreate(
            ["email" => $request['email']],
            [
                "email" => $request['email'],
                "name" => $request['name'],
                "password" => Hash::make($request['password']),
            ]
        );

        $otp = $this->generateOtp($user);

        // send OTP
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'otp' => $otp
        ];
        $status = $this->success;
        $status = $this->sendOTP($status, $data); // send OTP and returns status

        $message = trans("messages.otpSuccess");
        if ($status != $this->success) {
            $message = trans("messages.failedToSendOtp");
        }

        return prepareApiResponse($message, $status, $user);
    }

    public function generateOtp($user)
    {
        $userOtp = UserOtp::updateOrCreate(
            ["user_id" => $user->id],
            ["otp" => $this->generateBarcodeNumber()]
        );

        return $userOtp->otp;
    }

    public function generateBarcodeNumber()
    {
        $number = mt_rand(10000, 99999); // better than rand()

        // call the same function if the barcode exists already
        if ($this->numberExists($number)) {
            return $this->generateBarcodeNumber();
        }

        // otherwise, it's valid and can be used
        return $number;
    }

    public function numberExists($number)
    {
        return UserOtp::whereOtp($number)->exists();
    }

    public function loginVerifyOtp($request)
    {
        $user = User::whereEmail($request['email'])->first();
        UserOtp::where([["otp", $request['otp']], ["user_id", $user->id]])->delete();
        $status = $this->success;
        $user->email_verified_at = date("Y-m-d H:i:s");
        $user = DB::transaction(function () use ($user) {
            $user->save();
            $user->token = $user->createToken('token')->accessToken;
            return $user;
        });
        return prepareApiResponse(trans('messages.verifyotp'), $status, $user);
    }

    public function login($data)
    {
        if (Auth::attempt(["email" => $data['email'], "password" => $data['password']])) {
            $user = Auth::user();
            $otp = $this->generateOtp($user);
            // send OTP
            $data = [
                'email' => $user->email,
                'otp' => $otp,
                'name' => $user->name
            ];
            $status = $this->success;
            $status = $this->sendOTP($status,$data); // send OTP and returns status
            $message = trans("messages.otpSuccess");
            if ($status != $this->success) {
                $message = trans("messages.failedToSendOtp");
            }
        } else {
            $status = $this->failure;
            $message = trans("messages.invalidCredentials");
            $user = array();
        }
        return prepareApiResponse($message, $status, $user);
    }

    public function sendOTP($status, $data = []) // send OTP and returns status
    {
        Mail::to($data['email'])->send(new SendOtp($data));
        // check for failures
        if (Mail::failures()) {
            $status = 4;
            return Mail::failures();
        }
        return $status;
    }
}
