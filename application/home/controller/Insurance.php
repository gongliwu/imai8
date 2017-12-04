<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\home\controller;
use think\Page;
require_once ROOT_PATH . "extend/requests/Requests.php";

// Requests
\Requests::register_autoloader();

class Insurance extends Base {
    private $is_wechat = 1;
    private $key = '天王盖地虎，宝塔镇河妖';
    private $user_id = 0;
    private $user = array();

    /**
     * 析构流函数
     */
    public function  __construct()
    {
        parent::__construct();
        
        // 判断是否为微信浏览器
        $agent = (isset($_SERVER["HTTP_USER_AGENT"])) ? $_SERVER["HTTP_USER_AGENT"] : '';
        $agent = strtolower($agent);
        \Think\Log::record($agent, 'INFO', true);
        if (strpos($agent, 'micromessenger') === false) {
            $this->is_wechat = 0;
        }

        $_user = session('user');
        $this->user_id = $_user['user_id'];
        $this->user = M('users')->where("user_id", $this->user_id)->find();
    }

    /**
     * 保险详情
     */
    public function detail()
    {
        $car_num = session('insurance_car_num');
        $car_type = session('insurance_car_type');
        $insurance_amount = session('insurance_insurance_amount');

        $car_num = ($car_num == 7) ? $car_num : 5;
        $car_type = ($car_type == 2) ? $car_type : 1;
        $insurance_amount = (in_array($insurance_amount, array(10,20,30,40,50))) ? $insurance_amount : 10;

        $this->assign('car_num', $car_num);
        $this->assign('car_type', $car_type);
        $this->assign('insurance_amount', $insurance_amount);

        return $this->fetch();
    }


    /**
     * 填写投保信息
     */
    public function create()
    {
        // 保存汽车信息
        $car_num          = I('car_num/d');
        $car_type         = I('car_type/d');
        $insurance_amount = I('insurance_amount/d');
        $price            = I('price/d',0);
        if ($car_num != 0 && $car_type != 0 && $insurance_amount != 0) {
            session('insurance_car_num', $car_num);
            session('insurance_car_type', $car_type);
            session('insurance_insurance_amount', $insurance_amount);
        }

        $name   = session('insurance_name');
        $id_no  = session('insurance_id_no');
        $car_no = session('insurance_car_no');
        $email  = session('insurance_email');
        
		$this->assign('price', $price);
        $this->assign('car_num', $car_num);
        $this->assign('car_type', $car_type);
        $this->assign('insurance_amount', $insurance_amount);
        $this->assign('name', $name);
        $this->assign('id_no', $id_no);
        $this->assign('car_no', $car_no);
        $this->assign('email', $email);

        return $this->fetch();
    }


    /**
     * 确认保单信息
     */
    public function confirm()
    {

        // 从session中获取 car_num, car_type, insurance_amount
        $car_num          = session('insurance_car_num');
        $car_type         = session('insurance_car_type');
        $insurance_amount = session('insurance_insurance_amount');
        
        // 保存保险信息
        $name   = I('name/s');
        $id_no  = I('id_no/s');
        $car_no = I('car_no/s');
        $email  = I('email/s');
        $insurance_info = array(
            'car_num'          => $car_num,
            'car_type'         => $car_type,
            'insurance_amount' => $insurance_amount,
            'name'             => $name,
            'id_no'            => $id_no,
            'car_no'           => $car_no,
            'email'            => $email,
        );

        foreach ($insurance_info as $key => $val) {
            \Think\Log::record("[Confirm] [param] {$key} => {$val}", 'INFO', true);
            if (!isset($val) || empty($val)) {
                header("Location: ./detail.html");
                exit();
            }

            session('insurance_'.$key, $val);
        }

        $car_type_text = ($car_type == 1) ? '家庭自用车' : '非营业客车';
        $tomorrow = date('Y-m-d', strtotime('+1 days'));
        $nextyear = date('Y-m-d', strtotime('+1 year'));
        $birthday = substr($id_no, 6, 4) . '-' . substr($id_no, 10, 2) . '-' . substr($id_no, 12, 2);
        $order_code = intval(substr($id_no, 14, 3));
        $sex = ($order_code%2 == 1) ? '男' : '女'; 

        $price_config = array(
            '10-5'=>150,
            '10-7'=>210,
            '20-5'=>300,
            '20-7'=>420,
            '30-5'=>450,
            '30-7'=>630,
            '40-5'=>600,
            '40-7'=>840,
            '50-5'=>750,
            '50-7'=>1050
        );
        $price_key = strval($insurance_amount).'-'.strval($car_num);
        $price = $price_config[$price_key];

        $this->assign('car_num', $car_num);
        $this->assign('car_type', $car_type);
        $this->assign('insurance_amount', $insurance_amount);
        $this->assign('name', $name);
        $this->assign('id_no', $id_no);
        $this->assign('car_no', $car_no);
        $this->assign('email', $email);
        $this->assign('mobile', session('insurance_mobile', ''));
        $this->assign('price', $price);
        $this->assign('car_type_text', $car_type_text);
        $this->assign('tomorrow', $tomorrow);
        $this->assign('nextyear', $nextyear);
        $this->assign('birthday', $birthday);
        $this->assign('sex', $sex);

        return $this->fetch();
    }


    /**
     * 生成订单
     */
    public function neworder()
    {   
        date_default_timezone_set("Asia/Shanghai");

        $car_num          = session('insurance_car_num');
        $car_type         = session('insurance_car_type');
        $insurance_amount = session('insurance_insurance_amount');
        $name             = session('insurance_name');
        $id_no            = session('insurance_id_no');
        $car_no           = session('insurance_car_no');
        $email            = session('insurance_email');

        // 订单编号，如：insurance-2016112906461234
        $now = date('YmdHis');
        $order_sn = 'insurance-' . $now . strval(mt_rand(1000, 9999));

        // 价格
        $price_config = array(
            '10-5'=>150,
            '10-7'=>210,
            '20-5'=>300,
            '20-7'=>420,
            '30-5'=>450,
            '30-7'=>630,
            '40-5'=>600,
            '40-7'=>840,
            '50-5'=>750,
            '50-7'=>1050
        );
        $price_key = strval($insurance_amount).'-'.strval($car_num);
        $price = $price_config[$price_key];

        $tomorrow = date('Y-m-d', strtotime('+1 days'));
        $nextyear = date('Y-m-d', strtotime('+1 year'));

        $data = array(
            'order_sn' => $order_sn,
            'order_time' => time(),
            'order_amount' => $price,
//          'order_amount' => 0.01,
            'uid' => $this->user_id,
            'nickname' => '',
            'avatar' => '',
            'mobile' => $this->user['mobile'],
            'name' => $name,
            'id_no' => $id_no,
            'car_no' => $car_no,
            'email' => $email,
            'car_num' => $car_num,
            'car_type' => $car_type,
            'insurance_amount' => $insurance_amount,
            'insurance_status' => 1, // 0:默认 1:未获取保单号 2:获取保单号成功 3:生成保单号失败
            // 'insurance_begin_date' => $tomorrow, // 保险起保日期
            // 'insurance_end_date' => $nextyear, // 终保日期
            'pay_status' => 1,
            'order_status'=>1,
        );
        $order_id = M('insurance_order')->insertGetId($data);

        \Think\Log::record('[NewOrder] unset session……', 'INFO', true);
        \Think\Log::record("[CreateOrder] before car_num:{$car_num}", 'INFO', true);
        session('insurance_car_num', null);
        session('insurance_car_type', null);
        session('insurance_insurance_amount', null);
        session('insurance_name', null);
        session('insurance_id_no', null);
        session('insurance_car_no', null);
        session('insurance_email', null);
        \Think\Log::record("[CreateOrder] after car_num:{$car_num}", 'INFO', true);

        header('Location: ./pay.html?order_id='.strval($order_id));
        exit();
    }
	/*
     * 订单支付页面
     */
    public function pay(){

        $order_id = I('order_id/d');
        $order = M('insurance_order')->where("order_id", $order_id)->find();

        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] > 1){
            $order_detail_url = U("Home/Insurance/order_detail",array('id'=>$order_id));
            header("Location: $order_detail_url");
            exit;
        }
        //如果是预售订单，支付尾款
//      if($order['pay_status'] == 2 && $order['order_prom_type'] == 4){
//          $pre_sell_info = M('goods_activity')->where(array('act_id'=>$order['order_prom_id']))->find();
//          $pre_sell_info = array_merge($pre_sell_info,unserialize($pre_sell_info['ext_info']));
//          if($pre_sell_info['retainage_start'] > time()){
//              $this->error('还未到支付尾款时间'.date('Y-m-d H:i:s',$pre_sell_info['retainage_start']));
//          }
//          if($pre_sell_info['retainage_end'] < time()){
//              $this->error('对不起，该预售商品已过尾款支付时间'.date('Y-m-d H:i:s',$pre_sell_info['retainage_start']));
//          }
//      }
        $payment_where = array(
            'type'=>'payment',
            'status'=>1,
            'scene'=>array('in',array(0,2))
        );
        if($order['order_prom_type'] == 4){
            $payment_where['code'] = array('neq','cod');
        }
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));

        return $this->fetch();
    }

    /**
     * 订单详情
     */
    public function orderdetail()
    {

        $order_id = I('order_id/d');
        $ispay = I('ispay/d');

        $order = M('insurance_order')->where(array('order_id'=>$order_id, 'uid'=>$this->user_id))->find();
        if (!$order) {
            echo '订单找不到';
            exit();
        }

        session('order_id', $order_id);
        $openid = $_SESSION['openid'];
        if ($order['order_status'] == 1 && !isset($order['insurance_begin_date']) && empty($order['insurance_begin_date'])) {
            $order['insurance_begin_date'] = date('Y-m-d', strtotime('+1 days'));
            $order['insurance_end_date'] = date('Y-m-d', strtotime('+1 year'));
        }
        
        $this->assign('order', $order);
        $this->assign('openid', $openid);
        $this->assign('ispay', $ispay);
        $this->assign('is_wechat', $this->is_wechat);
		$this->assign('active','insurance_order_list');
        return $this->fetch();
    }


    /**
     * api订单详情
     */
    public function apiorderdetail()
    {
        $order_id = I('order_id/d');
        $order = M('insurance_order')->where(array('order_id'=>$order_id, 'uid'=>$this->user_id))->find();

        if ( empty($order) ) {
            header('Content-Type:application/json');
            echo json_encode(array('ret'=>1001, 'msg'=>'找不到订单'));
            exit();
        }

        header('Content-Type:application/json');
        echo json_encode($order);
        exit();
    }


    /**
     * 取消订单
     */
    public function cancelorder()
    {
        $order_id = I('order_id/d');

        $where = array('order_id'=>$order_id, 'uid'=>$this->user_id, 'order_status'=>1);
        $data = array('order_status'=>4);
        M('insurance_order')->where($where)->update($data);
		
        $this->success("已取消");
    }


    /**
     * 微信支付回调处理
     */
    public function notify()
    {

        \Think\Log::record("[Notify] welcome haha.", 'INFO', true);

        $order_sn = I('order_sn');
        $sign = I('sign');
        $pay_method = I('pay_method');

        \Think\Log::record("[Notify][Info] order_sn:{$order_sn} sign:{$sign} pay_method:{$pay_method}", 'INFO', true);

        // 订单号判断
        if (!$order_sn && strlen($order_sn) != 29) {
            \Think\Log::record("[Notify][ErrorOrderSn] order_sn:{$order_sn}", 'INFO', true);
            echo 'error.';
            exit();
        }

        // 签名判断
        $mysign = md5($order_sn. $pay_method . $this->key);
        if ($sign != $mysign) {
            \Think\Log::record("[Notify][ErrorSign] sign:{$sign} => mysign:{$mysign}", 'INFO', true);
            echo "error sign.";
            exit();
        }

        $order = M('insurance_order')->where(array('order_sn'=>$order_sn))->find();

        // 找不到订单
        if (empty($order) || !isset($order)) {
            \Think\Log::record("[Notify] Order Not Found. order_sn:{$order_sn}", 'INFO', true);
            echo 'Order Not Found.';
            exit();
        }

        // 已经更新支付状态 并且 发送邮件成功
        if ($order['order_status'] == 3) {
            \Think\Log::record("[Notify] [Paid] Order Has Paid. order_sn:{$order_sn}", 'INFO', true);
            echo 'ok';
            exit();
        } 

        // 更新支付状态
        if ($order['pay_status'] != 2 && ($pay_method == 'weixin' || $pay_method == 'alipay')) {
            \Think\Log::record("[Notify] [Paid] update order paid. order_sn:{$order_sn}", 'INFO', true);

            $data = array('pay_status' => 2, 'pay_time' => time(), 'order_status' => 2, 'pay_method' => $pay_method);
            M('insurance_order')->where(array('order_id'=>$order['order_id']))->update($data);

            // 更新支付状态
            $order['pay_status'] = 2;
        }

        /** ???
        // 获取保单号
        if ($order['pay_status'] == 2 && empty($order['insurance_no'])) {
            \Think\Log::record("[Notify] [RequestChinalifeApi] order_sn:{$order_sn} start.......", 'INFO', true);

            $chinalife_api_url = 'http://218.17.219.109:8888/ThirdPartPlat/execute.action';
            $request_arr = array(
                'REQUEST_CODE' => 'V2',
                'REQUEST_TYPE' => 'Request',
                'USER'         => '38', // 测试环境填写38，正式环境填写20
                'PASSWORD'     => '123456',
                'PROD_ID'      => '2703',
                'DEPT_ID'      => '8000',
                'TRANS_ID'     => strval($order['order_id']),   // 交易码，由向日葵提供，每个业务对应一个唯一的交易码，用于区别重复数据
                'TRANS_DATE'   => strval(date('YmdHis')),       // 业务交易日期,日期格式:YYYYMMDDHHMMSS
                'BASE_PART'    => array(
                    'APP_NME'          => $order['name'],       // 投保人姓名
                    'APP_TEL'          => $order['mobile'],     // 投保人联系电话
                    'APP_CERT_TYPE'    => '01',                 // 投保人证件类型
                    'APP_CERT_TYPE_ID' => $order['id_no'],      // 投保人证件id
                    'APP_GENDER'       => '',                   // 投保人性别 F&M
                    'APP_NATION'       => '',                   // 国籍
                    'APP_ADDRESS'      => '',                   // 联系地址
                    'APP_POST_CODE'    => '',                   // 邮政编码
                    'APP_CAREER'       => '',                   // 职业名称
                    'EFF_TM'           => date('Ymd', strtotime('+1 days')) . '000000', // 保险起保时间,格式为：YYYYMMDDHHMMSS，HHMMSS必须全部为0
                    'SCHEME'           => 'A',                  // 投保方案,对应产品方案中的A-E（此字段固定传值A即可）
                    'CLAUSE_NAME'      => '',                   // 条款名称，默认“机动车驾乘人员团体意外伤害保险”
                    'VEHICLE_TYPE'     => '',                   // 被保险人准驾车型
                    'CAPACITY'         => $order['car_num'],    // 核定载客数，可选范围为1-7，核定载客数不允许超过7个座位。
                    'DRIVER_KIND'      => $order['car_type'],   // 驾驶员类别，家庭自用车或非营业客车,1-家庭自用车，2-非营业客车
                    'LICENSENO'        => $order['car_no'],     // 车牌号码，必录项
                    'ACCEPT_NUM'       => '1',                  // 购买保险份数,默认1份，最高5份，传值范围1-5
                ),
            );
            $request_text = json_encode($request_arr);
            \Think\Log::record("[Notify] [RequestChinalifeApi] order_sn:{$order_sn}, request_text:{$request_text}", 'INFO', true);

            $res = \Requests::post($chinalife_api_url, array(), $request_text);
            \Think\Log::record("[Notify] [RequestChinalifeApi] order_sn:{$order_sn}, response_text:{$res->body}", 'INFO', true);
            $resjson = json_decode($res->body, true);

            $ERROR_CODE = intval($resjson['ERROR_CODE']);
            if ($ERROR_CODE != 0) {
                \Think\Log::record("[Notify] [RequestChinalifeApi] order_sn:{$order_sn}, ERROR_CODE:{$ERROR_CODE}", 'INFO', true);
                
                $where = array('order_id'=>$order['order_id'], 'insurance_no'=>'', 'insurance_status'=>1);
                $data = array('insurance_no' => '', 'insurance_status' => 3);
                M('insurance_order')->where($where)->update($data);

                echo "error";
                exit();
            }

            $POL_NO = $resjson['POL_NO'];
            $POL_URL_ADDR = $resjson['POL_URL_ADDR'];

            \Think\Log::record("[Notify] [RequestChinalifeApi] [Sucess] order_sn:{$order_sn}, POL_NO:{$POL_NO}, POL_URL_ADDR:{$POL_URL_ADDR}", 'INFO', true);

            $data = array('insurance_no'=>$POL_NO, 'insurance_status'=>2, 'insurance_url'=>$POL_URL_ADDR, 'insurance_begin_date'=>date('Y-m-d', strtotime('+1 days')), 'insurance_end_date'=>date('Y-m-d', strtotime('+1 year')));
            M('insurance_order')->where(array('order_id'=>$order['order_id']))->update($data);

            $order['insurance_status'] = 2;
            $order['insurance_no'] = $POL_NO;
            $order['insurance_url'] = $POL_URL_ADDR;

        }
        **/
        
        # ??? if ($order['email_status'] != 1 && $order['insurance_status'] == 2) {
        if ($order['email_status'] != 1) {
            \Think\Log::record("[Notify] [RequestChinalifeApi] [Sucess] [Email] [Start] order_sn:{$order_sn}, email:{$order['email']}", 'INFO', true);

            $this->sendemail($order);

            \Think\Log::record("[Notify] [RequestChinalifeApi] [Sucess] [Email] [End] order_sn:{$order_sn}, email:{$order['email']}", 'INFO', true);

            echo "ok";
            exit();
        }

        echo "error";
        exit();

    }


    /**
     * 支付款项，并且发送邮件成功
     */
    private function sendemail($order)
    {
        if ($order['email_status'] == 1) {
            return;
        }

        $insurance_url = $order['insurance_url'];
        $subject = '中国人寿财险车上人员意外险电子保单';
        $content = "<p>恭喜，您的保险已购买成功！可点击如下地址查看电子保单。</p>
<p><a href=\"{$insurance_url}\" style=\"color: #fefefe; background-color: #009B62; text-decoration: none; width: 200px; line-height: 30px; font-size: 16px; display: block; text-align: center;\">查看电子保单</a></p>
<p>您也可以复制如下链接地址查看电子保单</p>
<pre>{$insurance_url}</pre>";

        send_email($order['email'], $subject, $content);

        M('insurance_order')->where(array('order_id'=>$order['order_id']))->update(array('email_status'=>1, 'order_status'=>3));
    }


    /**
     * 我的全部订单
     */
    public function orders()
    {
    		if(session('?user'))
        {
        		$user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user',$user);  //覆盖session 中的 user
        		$this->user = $user;
        		$this->user_id = $user['user_id'];
        		$this->assign('user',$user); //存储用户信息
        		$this->assign('user_id',$this->user_id);
        }else{
        		
            $this->redirect('Home/User/login');
        		
        }
    		
        $where = array('uid' => $this->user_id);
        
        $count      = M('insurance_order')->where($where)->order('order_id DESC')->count();
        $Page       = new Page($count,3);
        $show       = $Page->show();
        $order_list = M('insurance_order')->where($where)->order('order_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
		$this->assign('active','insurance_order_list');
        $this->assign('order_list', $order_list);
        $this->assign('page', $show);
        return $this->fetch();
    }


    /**
     * 支付成功页面
     */
    public function successful()
    {   
        $order_id = I('order_id/d');
        $email = I('email/s');

        if ( empty($email) ) {
            $order = M('insurance_order')->where(array('order_id'=>$order_id, 'uid'=>$this->user_id))->find();
            $email = $order['email'];
        }

        $this->assign('order_id', $order_id);
        $this->assign('email', $email);

        return $this->fetch('msg');
    }


    /**
     * 自动取消订单
     * 每天 00:01 分执行一次
     */
    public function autocancelorder()
    {   
        $begin_str = date('Y-m-d H:i:s');
        $begin_time = time();
        $before_24hour_time = $begin_time - 24*60*60;

        $order_list = M('insurance_order')->where(array('order_status'=>1, 'order_time <= '=> $before_24hour_time))->select();
        $order_count = count($order_list);

        $order_index = 1;
        foreach ($order_list as $order) {
            \Think\Log::record("[AutoCancelOrder] [OrderDetail] {$order_index}/{$order_count} order_id:{$order['order_id']}, order_status:{$order['order_status']}, insurance_no:{$order['insurance_no']}", 'INFO', true);

            $order_index++;
            M('insurance_order')->where(array('order_id' => $order['order_id']))->update(array('order_status' => 4));
        }

        $end_str = date('Y-m-d H:i:s');
        $diff_time = time() - $begin_time;
        \Think\Log::record("[AutoCancelOrder] [Report] begin:{$begin_str}, end:{$end_str}, diff_time:{$diff_time}, order_count:{$order_count}", 'INFO', true);
        echo "[AutoCancelOrder] [Report] begin:{$begin_str}, end:{$end_str}, diff_time:{$diff_time}, order_count:{$order_count}";
    }


    /**
     * 版权页面
     */
    public function copyright()
    {
        return $this->fetch();
    }


    /**
     * ??
     */
    public function autoorder()
    {
        $order_list = M('insurance_order')->where(array('order_status'=>2))->select();
        $order_count = count($order_list);

        \Think\Log::record("[AutoOrder] order_count:{$order_count}", 'INFO', true);

        $index = 0;
        foreach ($order_list as $order) {
            $index++;
            \Think\Log::record("[AutoOrder] {$index}/{$order_count} order_id:{$order['order_id']}, order_sn:{$order['order_sn']}", 'INFO', true);

            $sign = md5($order['order_sn'] . $this->key);
            $url = U('Mobile/insurance/notify', array('order_sn'=>$order['order_sn'], 'pay_method'=>'', 'sign'=>$sign));
            $res = \Requests::get($url);

            \Think\Log::record("[AutoOrder] [CallApi] order_id:{$order['order_id']}, order_sn:{$order['order_sn']}, {$url}, {$res->body}", 'INFO', true);
        }

        \Think\Log::record("[AutoOrder] Goodbye!!!", 'INFO', true);
        echo 'ok';
        exit();
    }


    public function test() {
        # openid test
        #var_dump($_SESSION['openid']);exit;

        # requests test
        #require_once ROOT_PATH . "extend/requests/Requests.php";
        #\Requests::register_autoloader();
        #$response = \Requests::get('http://api.crobshop.kapokcloud.com/goods/detail?goods_id=395');
        #echo '<pre>';var_dump($response);exit;
        #$data = json_decode($response->body);
        #echo '<pre>';var_dump($data->data->sku);exit;

        var_dump($_SERVER ['HTTP_HOST']);exit;
    }
}