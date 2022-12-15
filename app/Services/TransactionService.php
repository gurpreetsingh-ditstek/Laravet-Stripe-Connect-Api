<?php

namespace App\Services;

use App\Models\{User, Transaction, TransactionDetails};
use Symfony\Component\HttpFoundation\Response;
use Auth;
use Illuminate\Support\Facades\Validator;
use Exception;
use Carbon\Carbon;
use Log;

class TransactionService
{
    protected $success;
    protected $failure;
    protected $obj;

    static protected $stripe;

    static protected $partial_payment = 15;

    public function __construct($obj)
    {
        $this->obj = $obj;
        $this->success = Response::HTTP_OK;
        $this->failure = Response::HTTP_BAD_REQUEST;
        self::$stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
    }

    public static function convertToCents($dollars = 0)
    {
        $cents = $dollars * 100;
        return round($cents);
    }

    public static function convertToDecimal($dollars = 0)
    {
        return (float) $dollars / 100;
    }

    public static function roundDecimal($value)
    {
        return intval(
            strval(floatval(
                preg_replace("/[^0-9.]/", "", $value)
            ) * 100)
        );
    }

    public function calculateRatio($phase1, $phase2, $egift, $partial_paid = 0)
    {
        $phase1 = $phase1 - $partial_paid;
        $totalPhaseAmt = $phase1 + $phase2;
        $phase1Percentage = round($phase1 / $totalPhaseAmt * 100);
        $phase2Percentage = 100 - $phase1Percentage;
        $ratioOfPhase1 = round($egift * $phase1Percentage / 100);
        $ratioOfPhase2 = round($egift * $phase2Percentage / 100);
        $newPhase1Amount = $phase1 - $ratioOfPhase1;
        $newPhase2Amount = $phase2 - $ratioOfPhase2;
        return [
            'Phase1' => $newPhase1Amount,
            'Phase2' => $newPhase2Amount
        ];
    }

    //Share client_id with frontend
    public function getStripeClientId()
    {
        $data = [
            'stript_client_id' => env("STRIPE_CLIENT_ID"),
            'stripe_redirect_url' => env("STRIPE_REDIRECT_URL")
        ];
        return prepareApiResponse(trans("messages.success"), $this->success, $data);
    }

    //Get account_id from connect code
    public function createConnectAccount($request)
    {
        $validator = Validator::make($request->all(), [
            "stripe_connect_code" => "required",
        ]);
        if ($validator->fails()) {
            return prepareApiResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            $connectAccount = \Stripe\OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $request->stripe_connect_code,
            ]);

            //Save connected ID into User table
            User::updateOrCreate(['id' => Auth::id()], ['stripe_connect_id' => $connectAccount->stripe_user_id]);
            // Access the connected account id in the response
            $connected_account_id = $connectAccount->stripe_user_id;
            $response = [
                'message' => 'Connect account created successfully!',
                'connected_account_id' => $connected_account_id
            ];
            return prepareApiResponse("Connect Account", $this->success, $response);
        } catch (Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }

    public function payment($request)
    {
        $validator = Validator::make($request->all(), [
            "token" => "required",
            "amount" => "required",
            "currency" => "required",
            "order_id" => "required",
            "paymentAmountType" => "required",
            "total_amount" => "required"
        ]);
        if ($validator->fails()) {
            return prepareApiResponse($validator->errors()->first(), Response::HTTP_BAD_REQUEST);
        }

        try {
            if (Auth::user()->stripe_id != null && Auth::user()->stripe_id != '') {
                $getCustomerId = Auth::user()->stripe_id;
            } else {
                //Create new customer ID for patient
                $getCustomer = $this->createCustomerId($request->token);
                if ($getCustomer['status'] == 200) {
                    $getCustomerId = $getCustomer->id;
                } else {
                    return prepareApiResponse($getCustomer['message'], $getCustomer['status']);
                }
            }

            $user_connect_id = User::whereId($request->paidTo)->pluck('stripe_connect_id')->first();

            if ($user_connect_id == null) {
                return prepareApiResponse("Missing user connect id.", $this->success);
            }
            // Create payment intent
            $paymentIntent = self::$stripe->paymentIntents->create([
                'amount' => $request->amount,
                'currency' => $request->currency,
                'payment_method_types' => ['card'],
                'capture_method' => 'automatic',
                'customer' => $getCustomerId,
                'confirm' => true,
                'metadata' => [
                    'user_connect_id' => $user_connect_id,
                ],
            ]);
            if ($paymentIntent->status == 'succeeded') {
                //Get Balance Transaction ID
                $balanceTransactionId = $paymentIntent->charges->data[0]->balance_transaction;

                //Get Transaction Details
                $amountDetails = $this->balanceTransaction($balanceTransactionId);
                $chargeId = $amountDetails['ChargeId'];
                $stripeFees = $amountDetails['StripeFees'];
                $applicationFees = $amountDetails['ApplicationFees'];
                $transferAmountToUser = $this->transferAmount($request->amount, $request->currency, $chargeId, $user_connect_id, $type = null);

                $message = 'Payment completed successfully.';
                if ($transferAmountToUser) {
                    $transferId = $transferAmountToUser->id;

                    $transactionSave = $this->saveTransaction($request->orderId, $paymentIntent->status = '3', $user_connect_id, $getCustomerId, $paymentIntent->id, $balanceTransactionId, $chargeId, $transferId, $request->amount, $stripeFees, $applicationFees);

                    if (!$transactionSave) {
                        $message = "Transaction not saved successfully.";
                    }
                    return prepareApiResponse($message, $this->success, $transactionSave);
                } else {
                    return prepareApiResponse("Amount not transferred to Doctor. Please contact to the admin.", $this->failure);
                }
            }
        } catch (Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }

    //Get Balance transaction
    public function balanceTransaction($balanceTransactionId)
    {
        $balanceTransaction = self::$stripe->balanceTransactions->retrieve(
            $balanceTransactionId,
            []
        );
        $applicationFees = intval((($balanceTransaction->amount / 100) * 0.1) * 100) / 100;

        $doctorPayment = ($balanceTransaction->net / 100) - $applicationFees;

        return [
            'TotalAmount' => $balanceTransaction->amount,
            'NetAmount' => $balanceTransaction->net / 100,
            'StripeFees' => $balanceTransaction->fee / 100,
            'ApplicationFees' => $applicationFees,
            'doctor_payment' => (int) ($doctorPayment * 100),
            'ChargeId' => $balanceTransaction->source
        ];
    }

    public function transferAmount($amount, $currency, $chargeId, $user_connect_id, $type)
    {
        try {
            if (!$type) {
                \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
                return \Stripe\Transfer::create([
                    "amount" => $amount,
                    "currency" => $currency,
                    "destination" => $user_connect_id,
                    "source_transaction" => $chargeId,
                ]);
            } else {
                \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
                return \Stripe\Transfer::create([
                    "amount" => (int) self::convertToCents($amount),
                    "currency" => $currency,
                    "destination" => $user_connect_id,
                    'metadata' => [
                        'source_transaction' => $chargeId,
                    ]
                ]);
            }
        } catch (\Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }

    //Save payment intent inside the transaction table
    public function saveTransaction($userOrderId, $paymentIntentStatus, $doctorConnectId, $paymentIntentCustomer, $paymentIntentId, $balanceTransactionId, $chargeId, $transferId, $amount, $stripeFees, $applicationFees, $transactionType = '1', $egiftId = NULL)
    {
        $transaction = new Transaction();
        $transaction->user_id = Auth::id();
        $transaction->user_order_id = $userOrderId;
        $transaction->egift_id = $egiftId;
        $transaction->transaction_status = $paymentIntentStatus;
        $transaction->transaction_type = $transactionType;
        $transaction->save();

        if ($transaction) {
            $transactionDetailData = [
                'transaction_id' => $transaction->id,
                'sender_account_id' => $paymentIntentCustomer,
                'receiver_account_id' => $doctorConnectId,
                'payment_intent_id' => $paymentIntentId,
                'balance_transaction_id' => $balanceTransactionId,
                'charge_id' => $chargeId,
                'transfer_id' => $transferId,
                'save_status' => $paymentIntentStatus,
                'amount_paid' => self::convertToDecimal($amount),
                'stripe_fees' => $stripeFees,
                'application_fees' => $applicationFees
            ];

            return TransactionDetails::insert($transactionDetailData);
        }
    }

    //Get customer ID for patient
    public function createCustomerId($token)
    {
        try {
            $customer = self::$stripe->customers->create([
                'source' => $token,
                'email' => Auth::user()->email,
                'name' => Auth::user()->name,
                'description' => "User account",
                'metadata' => [
                    'unique_code' => Auth::user()->unique_code,
                ],
            ]);
            if ($customer->id) {
                User::where('id', Auth::id())->update(['stripe_id' => $customer->id]);
            }
            return $customer;
        } catch (\Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }

    //Create stripe connect account login link
    public function createConnectLoginLink($request)
    {
        try {
            $user_connect_id = Auth::user()->stripe_connect_id;
            if ($user_connect_id != null) {
                $link = self::$stripe->accounts->createLoginLink(
                    $doctor_conneuser_connect_idct_id,
                    []
                );
                return prepareApiResponse("Connect account login link.", $this->success, $link);
            }
            return prepareApiResponse("This doctor has no stripe connect account.", $this->failure);
        } catch (\Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }

    //Create token
    public function createToken($request)
    {
        try {
            $response = self::$stripe->tokens->create([
                'card' => [
                    'number' => '4242424242424242',
                    'exp_month' => 8,
                    'exp_year' => 2023,
                    'cvc' => '314',
                ],
            ]);

            return prepareApiResponse("Payment cancel successfully.", $this->success, $response);
        } catch (\Exception $ex) {
            return prepareApiResponse($ex->getMessage(), $this->failure);
        }
    }
}
