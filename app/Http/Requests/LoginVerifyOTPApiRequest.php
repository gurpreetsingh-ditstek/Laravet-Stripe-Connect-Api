<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use App\Models\{User,UserOtp};

class LoginVerifyOTPApiRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "email" => ['required','email',  function ($attribute, $value, $fail) {
                $checkEmailVarified =  User::where('email',$value)->pluck('email_verified_at')->first();
                if ($checkEmailVarified == null) {
                    $fail(trans("messages.uniqueEmail"));
                }
            }],
            "otp" => ["required", "integer", function ($attribute, $value, $fail) {
                $resp = $this->checkOtp($value, null, 60); //3rd parameter denotes time of expiry
                switch ($resp) {
                    case 0:
                        $fail(trans("messages.invalidOtp"));
                        break;
                    case 2:
                        $fail(trans("messages.otpExpired"));
                        break;
                }
            }],
        ];
    }

    private function checkOtp($otp, $user_id = null, $time = 300)
    {
        $userId_exists = ($user_id === null) ? false : true;
        $userOtp =  UserOtp::when($userId_exists, function ($q) use ($otp, $user_id) {
            $q->where([["otp", $otp], ["user_id", $user_id]]);
        }, function ($q) use ($otp) {
            $q->where("otp", $otp);
        })->first();

        if (!$userOtp) {
            return 0;
        } else {
            $differenceInSeconds =  $this->calculateDifferenceInSeconds($userOtp->updated_at, date('Y-m-d H:i:s'));
            if ($differenceInSeconds > $time) return 2;
            return 1; //success
        }
    }

    public function calculateDifferenceInSeconds($start_time, $end_time)
    {
        $timeFirst  = strtotime($start_time);
        $timeSecond = strtotime($end_time);
        return ($timeSecond - $timeFirst);
    }

    public function messages()
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email is not correct'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success'   => 400,
            'message'   => 'Validation errors',
            'data'      => $validator->errors()
        ]));
    }
}
