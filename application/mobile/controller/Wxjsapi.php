<?php 

namespace app\mobile\controller;

class Wxjsapi extends MobileBase
{

    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();

        ini_set('date.timezone','Asia/Shanghai');
        //error_reporting(E_ERROR);
        require_once ROOT_PATH . "wxpay/lib/WxPay.Api.php";
        require_once ROOT_PATH . "wxpay/example/WxPay.JsApiPay.php";
        require_once ROOT_PATH . "wxpay/example/log.php";
    }

    public function index()
    {
        $order_sn = I('order_sn/s');
        $total_fee = intval(floatval(I('total_fee')) * 100);
        $openId = I('openid/s');
        $notify_url = 'http://'.$_SERVER ['HTTP_HOST'].'/wxpay/example/notify.php';
        
        \Think\Log::record("[Notify] notify_url:{$notify_url}", 'INFO', true);

        //①、获取用户openid
        $tools = new \JsApiPay();

        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        $input->SetBody("中国人寿财险车上人员意外险");
        $input->SetAttach("");
        $input->SetOut_trade_no($order_sn);
        $input->SetTotal_fee($total_fee);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));
        $input->SetGoods_tag("保险");
        $input->SetNotify_url($notify_url);
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order = \WxPayApi::unifiedOrder($input);
        $jsApiParameters = $tools->GetJsApiParameters($order);
        ob_clean();
        header('Content-Type:application/json');
        echo $jsApiParameters;
        exit();
    }
}