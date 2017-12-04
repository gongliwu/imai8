<?php
/**
 * 团购券
 */
namespace app\home\controller;
use think\Page;
use app\home\logic\CertificateCodeLogic;
use app\home\logic\UsersLogic;
use think\Db;

class CertificateCode extends Base {
  public $codeLogic; // 团购券逻辑操作类

  /**
   * 初始化函数
   */
  public function _initialize() {
      parent::_initialize();
      $this->codeLogic = new CertificateCodeLogic();
//    if(session('?user'))
//    {
//      $user = session('user');
//            $user = M('users')->where("user_id", $user['user_id'])->find();
//            session('user',$user);  //覆盖session 中的 user
//      $this->user = $user;
//      $this->user_id = $user['user_id'];
//      $this->assign('user',$user); //存储用户信息
//
//    }else{
//      $this->error('请先登陆',U('Home/User/login'));
//    }
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
        $this->error('请先登陆',U('Home/User/login'));
      }
    $goods_id = I("goods_id/d");
    $goods_spec = I("goods_spec/a",array()); // 商品规格
    
    $goods = M('Goods')->find($goods_id);
    $goods['goods_num'] = I("goods_num/d");
//  echo '<pre>';var_dump($goods_id,$goods_spec,$goods['goods_num']);exit;
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
  
  
  public function addOrder(){
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

    $car_price = $this->_calculate(I("pay_type/d",0));
    $goods = $this->codeLogic->buyList($goods_id,$goods_num,$this->user,$spec_key);
    $goods['spec_key'] = $spec_key;
    $goods['spec_key_name'] = $spec_key_name;
    $result = $this->codeLogic->addOrder($this->user,$car_price,$goods); // 添加订单

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
    $result = $this->codeLogic->calculate_price($this->user,$goods,$pay_points,$user_money,$pay_type);
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
  }
	
	/**
   * 消费券列表 - 页面
   * * $author lxl
   * $time 2017-1
   */
  public function GoodsList()
  {
  	$where = 'suppliers_id > 0 and store_count > 0';
    $orderby = 'goods_id desc';
  	$goods_list_count = M('goods')->where($where)->order($orderby)->count();   //总页数
    $page = new Page($goods_list_count, 6);
    $coupon_list = M('goods')->where($where)->order($orderby)->limit($page->firstRow . ',' . $page->listRows)->select();
    
    $this->assign('list', $coupon_list);
    $this->assign('page', $page->show());
    $this->assign('goods_list_count',$goods_list_count);
    $this->assign('nowPage',$page->nowPage);// 当前页
    $this->assign('totalPages',$page->totalPages);//总页数
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
    $this->assign('active', 'cer_order_list');
    $this->assign('active_status', I('get.type'));
    

    return $this->fetch();
  }
  
  /*
     * 订单详情
     */
    public function order_detail(){
	    	if(session('?user'))
	    {
	    		$user = session('user');
	    		$user = M('users')->where("user_id", $user['user_id'])->find();
	        session('user',$user);  //覆盖session 中的 user
	        $this->user = $user;
	        $this->user_id = $user['user_id'];
	
	    }else{
	    		$this->error('请先登陆',U('Home/User/login'));
	    }
        $id = I('get.id/d');

        $map['order_id'] = $id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性

        if(!$order_info){
            $this->error('没有获取到订单信息');
            exit;
        }
        //获取订单商品
        $model = new UsersLogic();
        $data = $model->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        if($order_info['order_prom_type'] == 4){
            $pre_sell_item =  M('goods_activity')->where(array('act_id'=>$order_info['order_prom_id']))->find();
            $pre_sell_item = array_merge($pre_sell_item,unserialize($pre_sell_item['ext_info']));
            $order_info['pre_sell_is_finished'] = $pre_sell_item['is_finished'];
            $order_info['pre_sell_retainage_start'] = $pre_sell_item['retainage_start'];
            $order_info['pre_sell_retainage_end'] = $pre_sell_item['retainage_end'];
            $order_info['pre_sell_deliver_goods'] = $pre_sell_item['deliver_goods'];
        }else{
            $order_info['pre_sell_is_finished'] = -1;//没有参与预售的订单
        }
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];
        //获取订单进度条
        $sql = "SELECT action_id,log_time,status_desc,order_status FROM ((SELECT * FROM __PREFIX__order_action WHERE order_id = :id AND status_desc <>'' ORDER BY action_id) AS a) GROUP BY status_desc ORDER BY action_id";
        $bind['id'] = $id;
        $items = DB::query($sql,$bind);
        $items_count = count($items);
        $region_list = get_region_list();

        $invoice_no = M('DeliveryDoc')->where("order_id", $id)->getField('invoice_no',true);
        $order_info[invoice_no] = implode(' , ', $invoice_no);
        //获取订单操作记录
        $order_action = M('order_action')->where(array('order_id'=>$id))->select();
        // 团购券订单
        $certificate_code_list = [];
        if ($order_info['order_type'] == 2) {
            $certificate_code_list = M('CertificateCode')->where('order_id', $id)->select();
        }
        
        $this->assign('order_status',C('ORDER_STATUS'));
        $this->assign('shipping_status',C('SHIPPING_STATUS'));
        $this->assign('certificate_code_list', $certificate_code_list);
        $this->assign('pay_status',C('PAY_STATUS'));
        $this->assign('region_list',$region_list);
        $this->assign('order_info',$order_info);
        $this->assign('order_action',$order_action);
        $this->assign('active','cer_order_list');
        return $this->fetch();
    }

}
