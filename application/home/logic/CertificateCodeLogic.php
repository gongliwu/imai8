<?php
/**
 * 团购券
 * Date: 2015-09-09
 */

 namespace app\home\logic;
 use think\Model;

 /**
  * 团购券 逻辑定义
  * Class CatsLogic
  * @package Home\Logic
  */
 class CertificateCodeLogic extends Model
 {

   /**
    * @param type $goods_id  商品id
    * @param type $goods_num   商品数量
    */
   function buyList($goods_id,$goods_num,$user,$spec_key='')
   {
     $goods = M('Goods')->find($goods_id);

     $goods['store_count'] = getGoodNum($goods['goods_id'],$spec_key); // 最多可购买的库存数量
     $goods['goods_num'] = $goods_num;

     /**
     $goods['member_goods_price'] = 0;
     if($goods['prom_type'] == 0){
       $goods['member_goods_price'] =  $goods['shop_price'] * $user['discount'];
     }
     **/
     $goods['member_goods_price'] = $goods['shop_price'];
     $goods['member_exchange_integral'] = $goods['exchange_integral'];
     if($spec_key){
       $sgp = M('spec_goods_price')->where(array('goods_id'=>$goods_id,'key'=>$spec_key))->find();
       if($sgp){
        $goods['member_goods_price'] = $sgp['price'];
        $goods['member_exchange_integral'] = $sgp['exchange_integral'];
       }
     }

     $goods['goods_fee'] = $goods['goods_num'] * $goods['member_goods_price'];
     $goods['exchange_integral_fee'] = $goods['goods_num'] * $goods['member_exchange_integral'];

     return $goods;
   }

   /**
    * 计算订单金额
    * @param type $user  用户
    * @param type $goods  购买的商品
    * @param type $pay_points 积分
    * @param type $user_money 余额
    */
   function calculate_price($user,$goods,$pay_points,$user_money,$pay_type)
   {
     // 如果传递过来的商品列表没有定义会员价
    //  if (!array_key_exists('member_goods_price', $goods)) {
    //      $user['discount'] = $user['discount'] ? $user['discount'] : 1; // 会员折扣 不能为 0
    //      $goods['member_goods_price'] = $goods['member_goods_price'] = $goods['shop_price'] * $user['discount'];
    //  }

     $goods['goods_fee'] = $goods['goods_num'] * $goods['member_goods_price'];    // 小计

     $goods['store_count'] = getGoodNum($goods['goods_id'], $goods['spec_key']); // 最多可购买的库存数量
     if ($goods['store_count'] <= 0)
         return array('status' => -10, 'msg' => $goods['goods_name'] . "库存不足,请重新下单", 'result' => '');

     $goods_price = $goods['goods_fee']; // 商品总价
     $cut_fee = $goods['goods_num'] * $goods['market_price'] - $goods['goods_num'] * $goods['member_goods_price']; // 共节约

     $use_percent_point = tpCache('shopping.point_use_percent');     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
     if($pay_points > 0 && $use_percent_point == 0){
         return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
     }

     if ($pay_points && ($pay_points > $user['pay_points']))
         return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
     if ($user_money && ($user_money > $user['user_money']))
         return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态

     $order_amount = $goods_price; // 应付金额 = 商品价格 + 物流费 - 优惠券

//     $user_money = ($user_money > $order_amount) ? $order_amount : $user_money;  // 余额支付原理等同于积分
//     $order_amount = $order_amount - $user_money; //  余额支付抵应付金额

     /*判断能否使用积分
      1..积分低于point_min_limit时,不可使用
      2.在不使用积分的情况下, 计算商品应付金额
      3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
      @{ */
     $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
     $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用


     if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
         return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
     }

    //  $pay_points = ($pay_points / tpCache('shopping.point_rate')); // 积分支付 100 积分等于 1块钱
    //  $pay_points = ($pay_points > $order_amount) ? $order_amount : $pay_points; // 假设应付 1块钱 而用户输入了 200 积分 2块钱, 那么就让 $pay_points = 1块钱 等同于强制让用户输入1块钱
    //  $order_amount = $order_amount - $pay_points; //  积分抵消应付金额


     $total_amount = $goods_price + $shipping_price;

     // $integral_money = 0;
     $integral_money = $goods['member_exchange_integral'] * $goods['goods_num'];
     if($pay_type == 1){
       $integral_money = $goods['member_exchange_integral'] * $goods['goods_num'];
       $total_amount = 0;
       $order_amount = 0;
       $cut_fee = 0;
     }

     //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
     $result = array(
         'total_amount' => $total_amount, // 商品总价
         'order_amount' => $order_amount, // 应付金额
         'goods_price' => $goods_price, // 商品总价
         'cut_fee' => $cut_fee, // 共节约多少钱
         'goods_num' => $goods['goods_num'], // 商品总共数量
         'integral_money' => $integral_money,  // 积分抵消金额
         'user_money' => $user_money, // 使用余额
         'goods' => $goods, // 商品列表 多加几个字段原样返回
     );
     return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
   }

   /**
    *  添加一个订单
    * @param type $user  用户
    * @param type $car_price 各种价格
    * @param type $user_note 用户备注
    * @return type $order_id 返回新增的订单id
    */
    public function addOrder($user,$car_price,$goods,$user_note='', $pay_type=0)
    {
      $user_id = $user['user_id'];
      // $user = M('users')->where("user_id", $user_id)->find();

      // 仿制灌水 1天只能下 50 单
      $order_count = M('Order')->where("user_id",$user['user_id'])->where('order_sn', 'like', date('Ymd')."%")->count();
      if($order_count >= 50)
        return array('status'=>-9,'msg'=>'一天只能下50个订单','result'=>'');

      // 插入订单 order
      $data = array(
              'user_id'          =>$user['user_id'], // 用户id
              'order_sn'         => date('YmdHis').rand(1000,9999), // 订单编号
              'mobile'           =>$user['mobile'],//'手机',
              'goods_price'      =>$car_price['goodsFee'],//'商品价格',
              'user_money'       =>$car_price['balance'],//'使用余额',
              'integral'         =>($car_price['pointsFee'] * tpCache('shopping.point_rate')), //'使用积分',
              'integral_money'   =>$car_price['pointsFee'],//'使用积分抵多少钱',
              'total_amount'     =>($car_price['goodsFee'] + $car_price['postFee']),// 订单总额
              'order_amount'     =>$car_price['payables'],//'应付款金额',
              'add_time'         =>time(), // 下单时间
              'user_note'        =>$user_note, // 用户下单备注
              'order_type'       =>2
      );

      $is_account_log = true;
      if($car_price['pointsFee']>0 && $user['pay_points'] < $car_price['pointsFee']) {
          $data['order_amount'] = $data['total_amount'];
          $order['order_amount'] = $data['total_amount'];
          $is_account_log = false;
      }

      $data['order_id'] = $order_id = M("Order")->insertGetId($data);
      $order = $data;
      if(!$order_id)
          return array('status'=>-8,'msg'=>'添加订单失败','result'=>NULL);

      // 记录订单操作日志
      $action_info = array(
          'order_id'        =>$order_id,
          'action_user'     =>$user_id,
          'action_note'     => '您提交了订单，请等待系统确认',
          'status_desc'     =>'提交订单', //''
          'log_time'        =>time(),
      );
      M('order_action')->insertGetId($action_info);

      $sgp = M('spec_goods_price')->where(array('goods_id'=>$goods['goods_id'],'key'=>$goods['spec_key']))->find();

      $data2['order_id']           = $order_id; // 订单id
      $data2['goods_id']           = $goods['goods_id']; // 商品id
      $data2['goods_name']         = $goods['goods_name']; // 商品名称
      $data2['goods_sn']           = $goods['goods_sn']; // 商品货号
      $data2['goods_num']          = $goods['goods_num']; // 购买数量
      $data2['market_price']       = $goods['market_price']; // 市场价
      $data2['goods_price']        = $goods['shop_price']; // 商品价               为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
      $data2['spec_key']           = $goods['spec_key']; // 商品规格
      $data2['spec_key_name']      = $goods['spec_key_name']; // 商品规格名称
      $data2['spec_exchange_integral'] = $sgp ? $sgp['exchange_integral'] : 0;  // 商品规格兑换积分
      $data2['member_goods_price'] = $goods['member_goods_price']; // 会员折扣价
      $data2['cost_price']         = $goods['cost_price']; // 成本价
      $data2['give_integral']      = $goods['give_integral']; // 购买商品赠送积分
      $data2['prom_type']          = $goods['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
      $data2['prom_id']            = $goods['prom_id']; // 活动id
      $order_goods_id              = M("OrderGoods")->insertGetId($data2);

      // 如果应付金额为0  可能是余额支付 + 积分  这里订单支付状态直接变成已支付
      if($data['order_amount'] == 0)
      {
          update_pay_status($order['order_sn'], array('integral'=>'1'));
      }

      if($pay_type == 1 && $car_price['pointsFee']>0 && $user['pay_points'] >= $car_price['pointsFee'])
        M('Users')->where("user_id", $user_id)->setDec('pay_points',($car_price['pointsFee'] * tpCache('shopping.point_rate'))); // 消费积分
      if($pay_type == 1 && $car_price['balance']>0)
        M('Users')->where("user_id", $user_id)->setDec('user_money',$car_price['balance']); // 抵扣余额

      $data4['user_id'] = $user_id;
      $data4['user_money'] = -$car_price['balance'];
      $data4['pay_points'] = -($car_price['pointsFee'] * tpCache('shopping.point_rate'));
      $data4['change_time'] = time();
      $data4['desc'] = '下单消费';
      $data4['order_sn'] = $order['order_sn'];
      $data4['order_id'] = $order_id;
      // 如果使用了积分或者余额才记录
      if($pay_type == 1 && $is_account_log === true && ($data4['user_money'] || $data4['pay_points'])) {
          M("AccountLog")->add($data4);
      }

      //分销开关全局
      // $distribut_switch = tpCache('distribut.switch');
      // if($distribut_switch  == 1 && file_exists(APP_PATH.'common/logic/DistributLogic.php'))
      // {
      //     $distributLogic = new \app\common\logic\DistributLogic();
      //     $distributLogic->rebate_log($order); // 生成分成记录
      // }
      // 如果有微信公众号 则推送一条消息到微信
      if($user['oauth']== 'weixin')
      {
          $wx_user = M('wx_user')->find();
          $jssdk = new \app\mobile\logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);
          $wx_content = "你刚刚下了一笔订单:{$order['order_sn']} 尽快支付,过期失效!";
          $jssdk->push_msg($user['openid'],$wx_content);
      }

      //用户下单, 发送短信给商家
      $res = checkEnableSendSms("3");
      $sender = tpCache("shop_info.mobile");

      if($res && $res['status'] ==1 && !empty($sender)){

          $params = array('consignee'=>$order['consignee'] , 'mobile' => $order['mobile']);
          $resp = sendSms("3", $sender, $params);
      }

      $result = array('order_id'=>$order_id,'pointsFee' =>$car_price['pointsFee'],'payables'=>$car_price['payables']);
      $ret_order_info = M('Order')->where('order_id', $order_id)->select();

      // 返回新增的订单id
      return array(
        'status'=>1,
        'msg'=>'提交订单成功',
        'result'=>$result,
        'order_info'=>$ret_order_info
      ); 
    }



 }
