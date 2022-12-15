<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use App\Models\User;

class LoginApiRequest extends FormRequest
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
            'email' => ['required','email',  function ($attribute, $value, $fail) {
                $checkEmailVarified =  User::where('email',$value)->pluck('email_verified_at')->first();
                if ($checkEmailVarified == null) {
                    $fail(trans("messages.verifyYourEmail"));
                }
            }],
            'password' => 'required|min:6'
        ];
    }

    public function messages()
    {
        return [
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
