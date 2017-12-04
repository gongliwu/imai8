<?php

namespace app\mobile\controller;

use think\Page;
use app\home\logic\UsersLogic;

class CertificateCode extends MobileBase {
  public $codeLogic; // 团购券逻辑操作类
  public $user_id = 0;
  public $user = array();

  /**
   * 初始化函数
   */
  public function _initialize() {
      parent::_initialize();
      $this->codeLogic = new \app\home\logic\CertificateCodeLogic();

  }

  // 购买团购券
  public function buy(){
    if(session('?user'))
      {
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user',$user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user',$user); //存储用户信息
       }else{
         $this->error('请先登陆',U('Mobile/User/login'));
       }

    $goods_id = I("goods_id/d");

    $goods_spec = I("goods_spec/a",array()); // 商品规格

    $goods = M('Goods')->find($goods_id);
    $goods['goods_num'] = I("goods_num/d");


    $result = $this->_getSpec($goods_id,$goods['goods_num'],$goods_spec);

    if($result['status'] < 1){
      $this->error($result['msg']);
    }
    $spec_key_name = $result['spec_key_name'];
    $spec_key = $result['spec_key'];

    $suppliers = M('suppliers')->find($goods['suppliers_id']);

    $this->assign('v', $goods);
    $this->assign('goods_id',$goods_id);
    $this->assign('goods_num',I("goods_num/d"));
    $this->assign('spec_key_name',$spec_key_name);
    $this->assign('spec_key',$spec_key);
    $this->assign('suppliers',$suppliers);
    return $this->fetch();
  }

  // 确认购买团购券
  public function confirmBuy(){
  	 if(session('?user'))
      {
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user',$user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user',$user); //存储用户信息
       }else{
         $this->error('请先登陆',U('Mobile/User/login'));
       }
    $goods_id = I("goods_id/d");
    $goods_num = I("new_goods_num/d"); // goods_num 购买商品数量
    $spec_key = I("spec_key");
    $spec_key_name = I("spec_key_name"); // 商品规格

    $goods = $this->codeLogic->buyList($goods_id,$goods_num,$this->user,$spec_key);

    $suppliers = M('suppliers')->find($goods['suppliers_id']);

    $this->assign('v', $goods);
    $this->assign('spec_key',$spec_key);
    $this->assign('spec_key_name',$spec_key_name);
    $this->assign('suppliers',$suppliers);
    return $this->fetch('confirm_buy');
  }

  private function _getSpec($goods_id,$goods_num,$goods_spec){
    $specGoodsPriceList = M('SpecGoodsPrice')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->getField("key,key_name,price,store_count,sku"); // 获取商品对应的规格价钱 库存 条码
    $goods = M('Goods')->where("goods_id", $goods_id)->cache(true,TPSHOP_CACHE_TIME)->find(); // 找出这个商品

    if(!empty($specGoodsPriceList) && empty($goods_spec)) // 有商品规格 但是前台没有传递过来
        return array('status'=>-1,'msg'=>'请选择商品规格');
    if($goods_num <= 0)
        return array('status'=>-2,'msg'=>'购买商品数量不能为0');
    if(empty($goods))
        return array('status'=>-3,'msg'=>'购买商品不存在');
    if(($goods['store_count'] < $goods_num))
        return array('status'=>-4,'msg'=>'商品库存不足','result'=>'');
    if($goods['prom_type'] > 0 && $user_id == 0)
        return array('status'=>-101,'msg'=>'购买活动商品必须先登录','result'=>'');
    if(!empty($specGoodsPriceList) && empty($goods_spec)) // 有商品规格 但是前台没有传递过来
        return array('status' => 0,'msg'=>'请选择商品规格');

    foreach($goods_spec as $key => $val) // 处理商品规格
        $spec_item[] = $val; // 所选择的规格项

    if(!empty($spec_item)) // 有选择商品规格
    {

        sort($spec_item);
        $spec_key = implode('_', $spec_item);

        if($specGoodsPriceList[$spec_key]['store_count'] < $goods_num)
            return array('status'=> 0,'msg'=>'商品库存不足');
    }
    $result = array('status' => 1,'msg' => '');
    $result['spec_key'] = "{$spec_key}";

    $result['spec_key_name'] = "{$specGoodsPriceList[$spec_key]['key_name']}";
    // var_dump($specGoodsPriceList);
    // var_dump($result);

    return $result;
  }

  /**
   * ajax 提交订单
   */
  public function addOrder(){
      if(session('?user')) {
          $user = session('user');
          $user = M('users')->where("user_id", $user['user_id'])->find();
          session('user',$user);  //覆盖session 中的 user
          $this->user = $user;
          $this->user_id = $user['user_id'];
          $this->assign('user',$user); //存储用户信息
      }else{
          $this->error('请先登陆',U('Mobile/User/login'));
      }

      $goods_id = I("goods_id/d");
      $goods_num = I("goods_num/d"); // goods_num 购买商品数量
      $spec_key = I("spec_key");
      $spec_key_name = I("spec_key_name"); // 商品规格
      $pay_type = I("pay_type/d", 0);

      $car_price = $this->_calculate(I("pay_type/d",0));
      $goods = $this->codeLogic->buyList($goods_id,$goods_num,$this->user,$spec_key);
      $goods['spec_key'] = $spec_key;
      $goods['spec_key_name'] = $spec_key_name;
      $result = $this->codeLogic->addOrder($this->user,$car_price,$goods, '', $pay_type); // 添加订单

      exit(json_encode($result));

  }

  private function _calculate($pay_type){

  	if(session('?user'))
      {
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user',$user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user',$user); //存储用户信息
       }else{
         $this->error('请先登陆',U('Mobile/User/login'));
       }
    $goods_id = I("goods_id/d");
    $goods_num = I("goods_num/d"); // goods_num 购买商品数量
    $spec_key = I("spec_key");
    $spec_key_name = I("spec_key_name"); // 商品规格

    $goods = $this->codeLogic->buyList($goods_id,$goods_num,$this->user,$spec_key);

   $pay_points =  0; //  使用积分
   $user_money =  0; //  使用余额

    $user_money = $user_money ? $user_money : 0;
    $result = $this->codeLogic->calculate_price(
                                    $this->user,
                                    $goods,
                                    $pay_points,
                                    $user_money,
                                    $pay_type);
    if($result['status'] < 0)
      exit(json_encode($result));

    $car_price = array(
        'balance'      => $result['result']['user_money'], // 使用用户余额
        'pointsFee'    => $result['result']['integral_money'], // 积分支付
        'payables'     => number_format($result['result']['order_amount'], 2, '.', ''), // 应付金额
        'goodsFee'     => $result['result']['goods_price'],// 商品价格
    );

    return $car_price;

  }

  /**
   * 计算订单商品价格
   */
  public function calculatePrice(){
    $car_price = $this->_calculate(I("pay_type/d"));

    $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$car_price); // 返回结果状态
    exit(json_encode($return_arr));

  }

  // ajax 请求获取购买列表
  public function ajaxBuyList(){
  	 if(session('?user'))
      {
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user',$user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user',$user); //存储用户信息
       }else{
         $this->error('请先登陆',U('Mobile/User/login'));
       }
    $goods_id = I("goods_id/d");
    $post_goods_num = I("goods_num/d"); // goods_num 购买商品数量
    $new_goods_num = I("new_goods_num/d");
    $spec_key = I("spec_key");  // 商品规格
    $spec_key_name = I("spec_key_name"); // 商品规格

    if($new_goods_num){
      $post_goods_num = $new_goods_num;
    }

    $goods = $this->codeLogic->buyList($goods_id,$post_goods_num,$this->user,$spec_key);

    $total_fee = $goods['goods_num'] * $goods['member_goods_price'];
    $total_points = $goods['goods_num'] * $goods['member_exchange_integral'];

    $suppliers = M('suppliers')->find($goods['suppliers_id']);

      $this->assign('v', $goods);
      $this->assign('total_fee', $total_fee);
      $this->assign('total_points',$total_points);
      $this->assign('spec_key',$spec_key);
      $this->assign('spec_key_name',$spec_key_name);
      $this->assign('goods_num', $post_goods_num);
      $this->assign('suppliers', $suppliers);
      return $this->fetch('ajax_buy_list');

//  $data = array('v' => $goods, 'total_fee' => $total_fee, 'spec_key_name' => $spec_key_name, 'goods_num' => $post_goods_num);
//  $return_arr = array('status'=>1,'msg'=>'计算成功','result'=>$data); // 返回结果状态
//  exit(json_encode($return_arr));
  }

  /*
   * 订单支付页面
   */
  public function pay(){
    $order_id = I('order_id/d');
    $order = M('Order')->where("order_id", $order_id)->find();
    // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
    if($order['pay_status'] == 1){
        $order_detail_url = U("Mobile/User/order_detail",array('id'=>$order_id));
        header("Location: $order_detail_url");
        exit;
    }

    if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
        //微信浏览器
        if($order['order_prom_type'] == 4){
            //预售订单
            $payment_where['code'] = 'weixin';
        }else{
            $payment_where['code'] = array('in',array('weixin','cod'));
        }
    }else{
        if($order['order_prom_type'] == 4){
            //预售订单
            $payment_where['code'] = array('neq','cod');
        }
        $payment_where['scene'] = array('in',array('0','1'));
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
        //判断当前浏览器显示支付方式
        if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
            unset($paymentList[$key]);
        }
    }

    $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
    $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
    $this->assign('paymentList',$paymentList);
    $this->assign('bank_img',$bank_img);
    $this->assign('order',$order);
    $this->assign('bankCodeList',$bankCodeList);
    $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
    return $this->fetch();

  }

  /**
   * 消费券列表 - 页面
   * * $author lxl
   * $time 2017-1
   */
  public function GoodsList()
  {
      return $this->fetch();
  }

  /**
   * 消费券列表 - ajax
   * * $author lxl
   * $time 2017-1
   */
  public function AjaxGoodsList()
  {
      $p = I('p/d', 1);

      $where = 'suppliers_id > 0 and store_count > 0';
      $orderby = 'goods_id desc';
      $coupon_list = M('goods')->where($where)->order($orderby)->page($p,10)->select();

      $this->assign('coupon_list', $coupon_list);
      return $this->fetch();
  }

  // 订单列表
  public function order_list(){
  	 if(session('?user'))
      {
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user',$user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user',$user); //存储用户信息
       }else{
         $this->error('请先登陆',U('Mobile/User/login'));
       }
    $user = session('user');
    $user = M('users')->where("user_id", $user['user_id'])->find();
    $this->user_id = $user['user_id'];

    $where = ' user_id=' . $this->user_id . ' and order_type = 2';
    //条件搜索
    if(I('get.type')){
        $get_type = I('get.type');

        if ($get_type == 'WAITCCOMMENT') {
          $where .= ' and order_status < 2 and pay_status = 1 ';
        } else {
          $where .= C(strtoupper($get_type));
        }
    }
    $count = M('order')->where($where)->count();
    $Page = new Page($count, 10);
    $show = $Page->show();
    $order_str = "order_id DESC";
    $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

    //获取订单商品
    $model = new UsersLogic();

    foreach ($order_list as $k => $v) {
        $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
        $data = $model->get_order_goods($v['order_id']);
        $order_list[$k]['goods_list'] = $data['result'];
        $total_integral = 0;
        foreach ($data['result'] as $key => $value) {
          $total_integral += $value['luck_total_integral'];
          
          	$goods = M('goods')->where("goods_id",$value['goods_id'])->select();
//        	echo '<pre>';var_dump($goods);exit;
             $exchange_integral = $goods[0]['exchange_integral'];
             $order_list[$k]['goods_list'][$key]["exchange_integral"] = $exchange_integral;
        }

        $order_list[$k]['total_integral'] = $total_integral;
    }
    $this->assign('order_status', C('ORDER_STATUS'));
    $this->assign('shipping_status', C('SHIPPING_STATUS'));
    $this->assign('pay_status', C('PAY_STATUS'));
    $this->assign('page', $show);
    $this->assign('lists', $order_list);
    $this->assign('active', 'order_list');
    $this->assign('active_status', I('get.type'));
    if ($_GET['is_ajax']) {
        return $this->fetch('ajax_order_list');
        exit;
    }

    return $this->fetch();
  }
  /*
     * 订单详情
     */
    public function order_detail()
    {
	    	if(session('?user'))
	      {
	        $user = session('user');
	        $user = M('users')->where("user_id", $user['user_id'])->find();
	        session('user',$user);  //覆盖session 中的 user
	        $this->user = $user;
	        $this->user_id = $user['user_id'];
	        $this->assign('user',$user); //存储用户信息
	       }else{
	         $this->error('请先登陆',U('Mobile/User/login'));
	       }
        $id              = I('get.id/d');
        $if_form_Code    = I('if_form_Code/d', 0);
        $map['order_id'] = $id;
        $map['user_id']  = $this->user_id;
        $order_info      = M('order')->where($map)->find();
        $order_info      = set_btn_order_status($order_info); // 添加属性  包括按钮显示属性 和 订单状态显示属性
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model                    = new UsersLogic();
        $data                     = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];

        $region_list            = get_region_list();
        $invoice_no             = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no', true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(['order_id' => $id])->select();

        // 消费券订单
        $certificate_code_list = [];
        if ($order_info['order_type'] == 2) {
            $certificate_code_list = M('CertificateCode')->where('order_id', $id)->select();
        }

        $this->assign('order_status', C('ORDER_STATUS'));
        $this->assign('shipping_status', C('SHIPPING_STATUS'));
        $this->assign('pay_status', C('PAY_STATUS'));
        $this->assign('region_list', $region_list);
        $this->assign('order_info', $order_info);
        $this->assign('order_action', $order_action);
        $this->assign('certificate_code_list', $certificate_code_list);
        $this->assign('if_form_Code', $if_form_Code);
        if (I('waitreceive')) { //待收货详情
            return $this->fetch('wait_receive_detail');
        }
        return $this->fetch();
    }

    /**
     * 能量积分支付
     */
    public function integral_pay() {
        if (!session('?user')) {
            $this->error('请先登陆',U('Mobile/User/login'));
            exit;
        }
        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        $user_id = $user['user_id'];


        $order_id = I('get.order_id/d');
        $order = M('order')->where(
          array('order_id'=>$order_id, 'user_id' => $user_id))->find();

        if (!$order) {
            $this->error('没有获取到订单信息');
            exit;
        }

        // 判断用户积分是否足够支付
        if ($user['pay_points'] < $order['integral']) {
            exit(json_encode(
                  array('ret'=>201122, 'msg'=>'能量不足，暂不能支付', 'data'=>array())));
        }

        // 更新支付状态
        update_pay_status($order['order_sn'], array('integral'=>'1'));

        // 减少积分
        M('users')->where("user_id", $user_id)->save(
            array('pay_points'=>$user['pay_points']-$order['integral']));

        // 记录积分日志
        $data4['user_id'] = $user_id;
        $data4['user_money'] = 0;
        $data4['pay_points'] = -$order['integral'];
        $data4['change_time'] = time();
        $data4['desc'] = '下单消费';
        $data4['order_sn'] = $order['order_sn'];
        $data4['order_id'] = $order_id;
        M("AccountLog")->add($data4);

        exit(json_encode(array('ret'=>0, 'msg'=>'ok')));

    }
}
