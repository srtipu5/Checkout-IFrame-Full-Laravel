<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Session;
use Illuminate\Support\Str;

class BkashController extends Controller
{
    private $base_url;

    public function __construct()
    {
        $this->base_url = env('BKASH_BASE_URL'); 
    }

    public function authHeaders(){
        return array(
            'Content-Type:application/json',
            'Authorization:' .$this->grant(),
            'X-APP-Key:'.env('BKASH_APP_KEY')
        );
    }
         
    public function curlWithBody($url,$header,$method,$body_data_json){
        $curl = curl_init($this->base_url.$url);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_POSTFIELDS, $body_data_json);
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function curlWithoutBody($url,$header,$method){
        $curl = curl_init($this->base_url.$url);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function grant()
    {
        $header = array(
                'Content-Type:application/json',
                'username:'.env('BKASH_USER_NAME'),
                'password:'.env('BKASH_PASSWORD')
                );

        $header_data_json=json_encode($header);

        $body_data = array('app_key'=> env('BKASH_APP_KEY'), 'app_secret'=>env('BKASH_APP_SECRET'));
        $body_data_json=json_encode($body_data);
        $response = $this->curlWithBody('/checkout/token/grant',$header,'POST',$body_data_json);
    

        $token = json_decode($response)->id_token;

        return $token;
    }
    
    public function confirm(Request $request)
    {
        return view('Iframe.confirm');
    }

    public function payment(Request $request)
    {
        return view('Iframe.pay')->with([
            'amount' => $request->amount,
        ]);
    }

    public function createPayment(Request $request)
    {   
        $allRequest = $request->all();

        if(!$allRequest['amount'] || $allRequest['amount'] < 1){
            return redirect()->route('bkash-payment');     
        }

        $header =$this->authHeaders();
        $body_data = array(
            'amount' => $allRequest['amount'],
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => "Inv".Str::random(8)
        );
        $body_data_json=json_encode($body_data);

        $response = $this->curlWithBody('/checkout/payment/create',$header,'POST',$body_data_json);

        return $response;
    }

    public function executePayment(Request $request)
    {
        $paymentID = $request->paymentID;

        $header =$this->authHeaders();

        $response = $this->curlWithoutBody('/checkout/payment/execute/'.$paymentID,$header,'POST');
        
        $arr = json_decode($response,true);

        if(array_key_exists("errorCode",$arr) && $arr['errorCode'] != '0000'){
            Session::put('errorMessage', $arr['errorMessage']);
        }else if(array_key_exists("message",$arr)){
            // if execute api failed to response
            sleep(1);
            $response = $this->queryIframe($paymentID);
            $arr = json_decode($response,true);
        }

        if(isset($arr['trxID'])){
            // your database operation
            Session::put('response','bKash Response: '.$response);
        }

        return $response;
    }

    public function queryIframe($paymentID){

        $header =$this->authHeaders();

        $response = $this->curlWithoutBody('/checkout/payment/query/'.$paymentID,$header,'GET');

         return $response;
    }

    public function query(Request $request){

        $header =$this->authHeaders();

        $response = $this->curlWithoutBody('/checkout/payment/query/'.$request->paymentID,$header,'GET');

         return $response;
    }

    public function successPayment(Request $request)
    {
        return view('Iframe.success')->with([
            'response' => Session::get('response')
        ]);
    }
    
    public function failPayment(Request $request)
    {
        return view('Iframe.fail')->with([
            'errorMessage' => Session::get('errorMessage')
        ]);
    }

    public function getRefund(Request $request)
    {
        return view('Iframe.refund');
    }

    public function refundPayment(Request $request)
    {
        $header =$this->authHeaders();

        $body_data = array(
            'paymentID' => $request->paymentID,
            'amount' => $request->amount,
            'trxID' => $request->trxID,
            'sku' => 'sku',
            'reason' => 'Quality issue'
        );
     
        $body_data_json=json_encode($body_data);

        $response = $this->curlWithBody('/checkout/payment/refund',$header,'POST',$body_data_json);

        $res_array = json_decode($response,true);
        
       if(isset($res_array['refundTrxID'])){
        // your database insert operation    
        $message = "Refund successful.bKash refund trx ID : ".$res_array['refundTrxID'];
    }else{
        $message = "Refund Failed !!";
    }
        return view('Iframe.refund')->with([
            'response' => $message,
        ]);
    }

}
