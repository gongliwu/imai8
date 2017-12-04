<?php
ini_set('date.timezone','Asia/Shanghai');
error_reporting(E_ERROR);

define('DS', DIRECTORY_SEPARATOR);
defined('ROOT_PATH') or define('ROOT_PATH', dirname(dirname(__DIR__)) . DS);
require_once ROOT_PATH . "wxpay/lib/WxPay.Api.php";
require_once ROOT_PATH . 'wxpay/lib/WxPay.Notify.php';
require_once ROOT_PATH . 'wxpay/example/log.php';
require_once ROOT_PATH . "extend/requests/Requests.php";

//初始化日志
$logHandler= new CLogFileHandler("../logs/".date('Y-m-d').'.log');
$log = Log::Init($logHandler, 15);

class PayNotifyCallBack extends WxPayNotify
{
	//查询订单
	public function Queryorder($transaction_id)
	{
		$input = new WxPayOrderQuery();
		$input->SetTransaction_id($transaction_id);
		$result = WxPayApi::orderQuery($input);
		Log::DEBUG("query:" . json_encode($result));
		if(array_key_exists("return_code", $result)
			&& array_key_exists("result_code", $result)
			&& $result["return_code"] == "SUCCESS"
			&& $result["result_code"] == "SUCCESS")
		{
			return true;
		}
		return false;
	}
	
	//重写回调处理函数
	public function NotifyProcess($data, &$msg)
	{
		Log::DEBUG("call back:" . json_encode($data));
		$notfiyOutput = array();
		
		if(!array_key_exists("transaction_id", $data)){
			$msg = "输入参数不正确";
			return false;
		}

		//查询订单，判断订单真实性
		if(!$this->Queryorder($data["transaction_id"])){
			$msg = "订单查询失败";
			return false;
		}

		$key = '天王盖地虎，宝塔镇河妖';
		$out_trade_no = $data['out_trade_no'];
		$sign = md5($out_trade_no . 'weixin' . $key);

		$url = "http://".$_SERVER ['HTTP_HOST']."/Mobile/insurance/notify.html?order_sn={$out_trade_no}&pay_method=weixin&sign={$sign}";

		Requests::register_autoloader();
		$res = \Requests::get($url);

		Log::DEBUG('[Notify] res-body:' . $res->body);

		if ($res->body != 'ok') {
			return false;
		}

		return true;
	}
}

Log::DEBUG("[Notify] begin notify");
$notify = new PayNotifyCallBack();
$notify->Handle(false);

