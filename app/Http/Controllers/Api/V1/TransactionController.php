<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    protected $service;
    public function __construct()
    {
        $this->success = Response::HTTP_OK;
        $this->failure = Response::HTTP_BAD_REQUEST;
        $this->service = new TransactionService(new User());
    }

    //Share client_id with frontend
    public function getStripeClientId() {
        $response = $this->service->getStripeClientId();
        return response()->send($response['message'], $response['status'], $response['data']);
    }

    //Get account_id from connect code
    public function createConnectAccount(Request $request) {
        $response = $this->service->createConnectAccount($request);
        return response()->send($response['message'], $response['status'], $response['data']);
    }

    //Create payment intent with customer token
    public function Payment(Request $request) {
        $response = $this->service->Payment($request);
        return response()->send($response['message'], $response['status'], $response['data']);
    }

    //Create stripe connect account login link
    public function createConnectLoginLink(Request $request) {
        $response = $this->service->createConnectLoginLink($request);
        return response()->send($response['message'], $response['status'], $response['data']);
    }

    //Create token
    public function createToken(Request $request) {
        $response = $this->service->createToken($request);
        return response()->send($response['message'], $response['status'], $response['data']);
    }
}

?>
