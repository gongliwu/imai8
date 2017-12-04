<?php

/**
 * 积分抽奖
 */
namespace app\home\controller;
use think\AjaxPage;
use think\Request;
use think\Page;
use app\home\logic\UsersLogic;

class LuckDraw extends Base {
  public $user_id = 0;
  public $user = array();
  public function _initialize() {
    parent::_initialize();
  }

  public function index(){

//	$p = I('p/d',1);
//  $status = I('status/d', 0);


//  $this->assign('status',$status);

    return $this->fetch();
  }

  //进行中
  public function ajaxLucks(){
  	$goods_list_count = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')->where('status', 0)->count();   //总页数
    $page = new AjaxPage($goods_list_count, 6);
    $lucks = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')
             ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods,t.pay_integral")
             ->where('status', 0)->order('l.luck_id DESC')->limit($page->firstRow . ',' . $page->listRows)->select();
    $this->assign('page', $page->show());
    $this->assign('goods_list_count',$goods_list_count);


  	$this->assign('lucks',$lucks);
  	return $this->fetch();
  }

  //已揭晓
  public function ajaxLucked(){
  	$goods_list_count = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')->where('status', 1)->count();   //总页数
    $page = new AjaxPage($goods_list_count, 6);
    $lucked = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')
             ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods,t.pay_integral")
             ->where('status', 1)->order('l.luck_id DESC')->limit($page->firstRow . ',' . $page->listRows)->select();

    $this->assign('page', $page->show());
    $this->assign('goods_list_count',$goods_list_count);


    $this->assign('lucked',$lucked);

  	return $this->fetch();
  }


  // 抽奖详情
  public function detail(){
    $luck_id = I("luck_id/d");
    $luck = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')
            ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods,t.pay_integral")
            ->where("l.luck_id",$luck_id)->find();
    if(!$luck){
      return $this->error("这个抽奖不存在");
    }

    $goods_images_list = M('GoodsImages')->where("goods_id", $luck['goods_id'])->select(); // 商品 图册
    $goods = M('Goods')->where("goods_id", $luck['goods_id'])->find();
    $goods_attr_list = M('GoodsAttr')->where("goods_id", $luck['goods_id'])->select(); // 查询商品属性表
    $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性

    // 中奖用户
    $win_user = M('users')->find($luck['good_luck_user_id']);



    $this->assign('luck',$luck);
    $this->assign('goods_images_list',$goods_images_list);//商品缩略图
    $this->assign('goods',$goods);
    $this->assign('goods_attr_list',$goods_attr_list);//属性列表
    $this->assign('goods_attribute',$goods_attribute);//属性值
    $this->assign('win_user', $win_user);

	  return $this->fetch();
  }

  // 立即参与抽奖
   // 立即参与抽奖
  public function joinLuck(){
    if(session('?user'))
    {
      $user = session('user');
      $user = M('users')->where("user_id", $user['user_id'])->find();
      session('user',$user);  //覆盖session 中的 user
      $this->user = $user;
      $this->user_id = $user['user_id'];
      return array('status'=> 1,'msg'=>"已登录",'user'=>$user);
     }else{
       return array('status'=> -2,'msg'=>"请先登陆");
     }


  }

  // 选择抽奖次数
  public function selLuckTimes(){
    $luck_id = I("luck_id/d");
    $joined_times = I("joined_times/d");
    $luck_draw = M('luck_draw')->where('luck_id',$luck_id)->find();
    $luck_tpl = M('luck_tpl')->where('tpl_id',$luck_draw['tpl_id'])->find();

    if(!$luck_draw){
      return array('status'=> -1,'msg'=>"这个抽奖不存在");
    }

    $total_joined_times = $joined_times + $luck_draw['joined_times'];
    if($total_joined_times > $luck_draw['total_times']){
      $rest_times = $luck_draw['total_times'] - $luck_draw['joined_times'];
      return array('status'=> -1,'msg'=>"本期您只可以参与".$rest_times."次");
    }
    $user = session('user');

    //计算积分是否够 总消耗积分 = 参与次数 * 每次消耗积分
    $needPoints= $joined_times * $luck_tpl['pay_integral'];
    $retPoints = $needPoints - $user['pay_points'];
    if($needPoints > $user['pay_points']){
      return array('status'=> -1,'msg'=>"您的积分不够，需要消耗".$needPoints."积分！还差".$retPoints."积分");
    }



    $luck_sn_list = M('luck_sn')->where('luck_id',$luck_id)->order('id DESC')->limit($joined_times)->select();

    $trans = M('luck_sn');
    $trans->startTrans();   // 开启事务
    $tran_result = true;
    try {   // 异常处理
      $order_goods = $this->addOrder($luck_draw,$luck_tpl,$user,$joined_times);
      foreach ($luck_sn_list as $key => $value) {
        $data = array(
           'luck_id' => $luck_id,
           'user_id' => $user['user_id'],
           'order_id'=> $order_goods['order_id'],
           'luck_sn' => $value['luck_sn'],
           'add_time'=> time(),
         );
         $luck_user_id= M('luck_draw_user')->insertGetId($data);

         if(!$luck_user_id){
           throw new Exception("获取抽奖码失败!");
         }

      }
    }catch (Exception $ex) {
      $tran_result = false;
    }

    if (!$tran_result) {
        $trans->rollback();
        // 更新失败
        return array('status'=> -500,'msg'=>"抽奖失败！");
    } else {
       $trans->commit();
    }

    // 用户已经用了这些抽奖号码，要删除掉
    foreach ($luck_sn_list as $key => $value) {
      M('luck_sn')->where("id",$value['id'])->delete();
    }
    // 计算用户剩下的积分
    $needPoints= $joined_times * $luck_tpl['pay_integral'];
    $retPoints = $user['pay_points'] - $needPoints;

    // M('users')->where('user_id',$user['user_id'])->update(array('pay_points' => $retPoints));
    accountLog($user['user_id'], 0 , $needPoints ,"抽奖消耗积分",0,0);
    M('order')->where("order_id", $order_goods['order_id'])->save(
      array('pay_status' => 1, 'pay_time' => time(),'pay_status' => 1)
    );

    // 更新已参与次数
    M('luck_draw')->where('luck_id',$luck_id)->update(array('joined_times'=> $total_joined_times));

    $good_luck_sn = '';
    // 总参与次数 == 总需次数 时，自动开奖
    if($total_joined_times === $luck_draw['total_times'] && $luck_draw['is_auto_open'] === 0){
      // 中奖号码
      $good_luck_sn = $this->washLuck($luck_id);
      //判断是否中奖
      $luck_status = 0;
      $good_luck_user_id = 0;
      $luck_user_all_list = M('luck_draw_user')->where('luck_id',$luck_id)->order('id DESC')->select();
      foreach ($luck_user_all_list as $key => $value) {
        if($good_luck_sn == $value['luck_sn']){
          $luck_status = 1;
          $good_luck_user_id = $value['user_id'];
          break;
        }
      }

      $where = array('luck_id' => $luck_id,'luck_sn' => $good_luck_sn);
      M("luck_draw_user")->where($where)->update(array('status'=>2));

      // 中奖用户
      $user = M('users')->where('user_id', $good_luck_user_id)->find();

      // 中奖记录
      $luck_draw_user = M('luck_draw_user')->where($where)->find();

      // 中奖用户参与人次统计
      $good_luck_joined_times = M('luck_draw_user')->where(array('luck_id'=>$luck_id, 'user_id'=>$good_luck_user_id, 'order_id'=>$luck_draw_user['order_id']))->count();
      $good_luck_total_times = M('luck_draw_user')->where(array('luck_id'=>$luck_id, 'user_id'=>$good_luck_user_id))->count();

      M('luck_draw')->where('luck_id',$luck_id)->update(array(
        'luck_sn' => $good_luck_sn,
        'status' => 1,
        'good_luck_user_id'=>$good_luck_user_id,
        'good_luck_nickname'=>$user['nickname'],
        'good_luck_head_pic'=>$user['head_pic'],
        'good_luck_joined_times'=>$good_luck_joined_times,
        'good_luck_total_times'=>$good_luck_total_times,
        'luck_result_time'=> time(),
      ));

      #M("OrderGoods")->where('luck_id',$luck_id)->update(array('luck_status' => 2));
      M("OrderGoods")->where('luck_id', $luck_id)->update(array('luck_status' => 1));
      M("OrderGoods")->where('order_id', $luck_draw_user['order_id'])->update(array('luck_status' => 2));

      // 下期自动开奖
      if($luck_draw['period']< $luck_tpl['periods']){
        $this->addLuckDraw($luck_draw);
      }
    }

    return array('status'=> 1,'msg'=>"抽奖成功！",
      'result'=>array('order_id'=>$order_goods['order_id'])
    );


  }

  // 洗牌 抽奖
  private function washLuck($luck_id){
    $luck_sn_list = M('luck_draw_user')->where('luck_id',$luck_id)->order('id DESC')->select();
    $results = array();
    if (function_exists('array_column')) {
        $results = array_column($luck_sn_list, 'luck_sn');
    }else{
        foreach ($luck_sn_list as $key => $value) {
          $results[] = $value['luck_sn'];
        }
    }

    shuffle($results);
    return $results[array_rand($results,1)];
  }
  // 下期自动开奖
  private function addLuckDraw($luck_draw){
    $luck_tpl = M('luck_tpl')->where('tpl_id',$luck_draw['tpl_id'])->find();
    $data = array(
      'tpl_id' => $luck_draw['tpl_id'],
      'period' => $luck_draw['period'] + 1,
      'add_time'=> time(),
      'is_auto_open' => $luck_tpl['is_auto_open'],
      'total_times' => $luck_tpl['total_times'],
    );
    $luck_id = M('luck_draw')->insertGetId($data);
    $this->create_sn($luck_draw['tpl_id'],$luck_id);
  }

  // 生成抽奖号码
  private function create_sn($tpl_id,$luck_id){
    $tpl = M("luck_tpl")->where('tpl_id',$tpl_id)->find();
    $luck_draw = M("luck_draw")->where('luck_id',$luck_id)->find();
    $times = $tpl['total_times'];
    $start = 10000000;
    for($i = 1;$i<$times+1;$i++){
      $start += 1;
      $data = array(
        'luck_id' => $luck_id,
        'luck_sn' => $start,
        'add_time'=> time(),
      );
      M("luck_sn")->insert($data);
    }
  }

  // 订单列表
  public function order_list(){
    $user = session('user');
    $user = M('users')->where("user_id", $user['user_id'])->find();
    $this->user_id = $user['user_id'];

    $where = ' user_id=' . $this->user_id . ' and luck_id != 0';
    //条件搜索
   if(I('get.type')){
        $where .= C(strtoupper(I('get.type')));

        # 中奖的订单
        $luck_order_id_list = array();
        $_where = array('user_id'=>$this->user_id, 'status'=>2);
        $ldu_list = M('luck_draw_user')->where($_where)->select();
        foreach ($ldu_list as $key => $ldu) {
          $luck_order_id_list[$key] = $ldu['order_id'];
        }

        # 筛选中奖的订单
        if ( ! empty($luck_order_id_list) ) {
          $luck_order_id_list = implode(',', $luck_order_id_list);

          $where .= ' and order_id in (' . $luck_order_id_list . ')';
        } else {
          $where .= ' and order_id in (0)';
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
        }

        $order_list[$k]['total_integral'] = $total_integral;
    }
    $this->assign('order_status', C('ORDER_STATUS'));
    $this->assign('shipping_status', C('SHIPPING_STATUS'));
    $this->assign('pay_status', C('PAY_STATUS'));
    $this->assign('page', $show);
    $this->assign('lists', $order_list);
    $this->assign('active', 'luck_order_list');
    $this->assign('active_status', I('get.type'));
    $this->assign('order_status',C('ORDER_STATUS'));
//  if ($_GET['is_ajax']) {
//      return $this->fetch('ajax_order_list');
//      exit;
//  }

    return $this->fetch();
  }
  /*
   * * 晒单列表
   * */
  public function show_luck()
  {

	return $this->fetch();
  }
  /*
   * * 订单列表
   * */
  public function ajax_order_list()
  {

  }
  // 添加订单
  private function addOrder($luck_draw,$luck_tpl,$user,$joined_times){
    $data = array(
      'order_sn'         => date('YmdHis').rand(1000,9999), // 订单编号
      'user_id'          =>$user['user_id'], // 用户id
      'mobile'           =>$user['mobile'],//'手机',
      'luck_id'          => $luck_draw['luck_id'],
      'order_type'       => 1,
      'add_time'         =>time(), // 下单时间
      'integral'         =>$joined_times * $luck_tpl['pay_integral'],
    );

    $order_id = M("Order")->insertGetId($data);
    if(!$order_id)
        return array('status'=>-8,'msg'=>'添加订单失败');

      // 记录订单操作日志
      $action_info = array(
          'order_id'        =>$order_id,
          'action_user'     =>$user['user_id'],
          'action_note'     => '您提交了订单，请等待系统确认',
          'status_desc'     =>'提交订单', //''
          'log_time'        =>time(),
      );
      M('order_action')->insertGetId($action_info);


      $goods = M('goods')->where('goods_id',$luck_tpl['goods_id'])->find();

      $data2 = array(
        'order_id' => $order_id,
        'goods_id' => $goods['goods_id'],
        'luck_id'  => $luck_draw['luck_id'],
        'goods_name'=> $goods['goods_name'],
        'goods_sn' => $goods['goods_sn'],
        'market_price'=> $goods['market_price'],
        'goods_price' => $goods['shop_price'],
        'member_goods_price' => $goods['member_goods_price'],
        'cost_price' => $goods['cost_price'],
        'give_integral'=> $goods['give_integral'],
        'prom_type' => $goods['prom_type'],
        'prom_id' => $goods['prom_id'],
        'luck_pay_integral' => $luck_tpl['pay_integral'],
        'luck_joined_times' => $joined_times,
        'luck_total_integral' => $luck_tpl['pay_integral'] * $joined_times,
        'luck_status' => 0
      );

      $order_goods_id = M("OrderGoods")->insertGetId($data2);


      return array('rec_id' =>$order_goods_id,'order_id'=>$order_id);

  }



  // 订单详情
  public function orderDetail(){
    if(!session('?user')){
      return $this->error('请先登陆',U('Mobile/User/login'));
    }
    $address_id = I('address_id/d');
    $order_id = I('order_id/d');

    $user = session('user');

    if($address_id)
        $address = M('user_address')->where("address_id", $address_id)->find();
    else
        $address = M('user_address')->where(['user_id'=>$user['user_id'],'is_default'=>1])->find();

    $order = M('order')->where('order_id',$order_id)->find();
    $order_goods = M('order_goods')->where('order_id',$order['order_id'])->find();
    $goods = M('goods')->where('goods_id',$order_goods['goods_id'])->find();


    $order_info = set_btn_order_status($order);
    $luck_draw = M('luck_draw')->where('luck_id',$order_goods['luck_id'])->find();
    $luck_tpl = M('luck_tpl')->where('tpl_id',$luck_draw['tpl_id'])->find();
    $luck_user = M('luck_draw_user')->where('luck_id',$luck_draw['luck_id'])->select();

    $user = M('users')->where("user_id", $user['user_id'])->find();
    $isLuck = 0;
    $good_luck_user = 0;
    // 中奖者
    if($luck_draw['good_luck_user_id'] !== 0){
      $good_user = M('luck_draw_user')->where(array(
        'luck_sn'=>$luck_draw['luck_sn'],'luck_id'=>$luck_draw['luck_id']
      ))->find();

      // var_dump($good_user);
      $good_luck_user = M('luck_draw_user')->alias('l')
               ->join('__USERS__ u','u.user_id = l.user_id')
               ->where(array("l.user_id"=>$good_user['user_id'],"l.luck_id"=>$good_user['luck_id']))
              // ->where(array("l.order_id"=>$order['order_id']))
               ->field("l.luck_sn, u.*")
               ->group('l.user_id')->find();

      $good_luck_user['luck_sn'] = $luck_draw['luck_sn'];
      //该用户是否中奖
      if($luck_draw['good_luck_user_id'] === $user['user_id']){
        $isLuck = 1;
        $delivery    = M('delivery_doc')->where("order_id", $order_id)->find();
        $order_info['invoice_no'] = $delivery['invoice_no'];
      }

    }


    $this->assign('is_luck',$isLuck);
    $this->assign('order_goods',$order_goods);
    $this->assign('good',$goods);
    $this->assign('order',$order);
    $this->assign('luck_draw',$luck_draw);
    $this->assign('luck_tpl',$luck_tpl);
    $this->assign('address',$address);
    $this->assign('order_info',$order_info);
    $this->assign('user_id',$user['user_id']);
    $this->assign('active','luck_order_list');
    $this->assign('good_luck_user',$good_luck_user);

    return $this->fetch();
  }

  // 我的抽奖号码
  public function myLuckSn(){
    $luck_id = I('luck_id/d');
    $user_id = I('user_id/d');
    $order_id = I('order_id/d');

    $where = array('luck_id'=>$luck_id, 'user_id'=>$user_id, 'order_id'=>$order_id);
    $luck_sn_list = M('luck_draw_user')->where($where)->order('id DESC')->select();
    $results = array();

    if (function_exists('array_column')) {
        $results = array_column($luck_sn_list, 'luck_sn');
    }else{
        foreach ($luck_sn_list as $key => $value) {
          $results[] = $value['luck_sn'];
        }
    }
    return array('status'=> 1,'luck_sn_list' =>$results);

  }

  // 填写订单地址
  public function addOrderAddress(){
    $address_id = I('address_id/d');
    $order_id = I('order_id/d');
    $address = M('user_address')->where("address_id", $address_id)->find();
    if($address_id ){
        $data = array(
          'consignee'        =>$address['consignee'], // 收货人
          'province'         =>$address['province'],//'省份id',
          'city'             =>$address['city'],//'城市id',
          'district'         =>$address['district'],//'县',
          'twon'             =>$address['twon'],// '街道',
          'address'          =>$address['address'],//'详细地址',
          'mobile'           =>$address['mobile'],//'手机',
          'zipcode'          =>$address['zipcode'],//'邮编',
          'email'            =>$address['email'],//'邮箱',
        );
        M('order')->where('order_id',$order_id)->update($data);
        return array('result'=>1,'msg'=>'填写订单地址成功');
    }
    return array('result'=>-1,'msg'=>'填写订单地址失败');
  }

  // 往期揭晓
  public function history_page() {
    $luck_id = I("luck_id/d");
    $this->assign('luck_id',$luck_id);
    return $this->fetch();
  }

  // 往期揭晓列表 - ajax
  public function ajax_history() {
    $luck_id = I("luck_id/d");
    $luck = M('luck_draw')->find($luck_id);
    if(!$luck){
      return $this->error("这个抽奖不存在");
    }
	// 中奖记录
    $count = M('luck_draw')
                      ->alias('l')
                      ->field('l.*, u.user_id, u.nickname, u.mobile, u.head_pic')
                      ->join('__USERS__ u','l.good_luck_user_id = u.user_id','LEFT')
                      ->where('tpl_id', $luck['tpl_id'])
                      ->where('status', 1)
                      ->order('l.luck_id DESC')
                      ->count();

    $page = new AjaxPage($count,6);
    $show = $page->show();
    // 中奖记录
    $history_list = M('luck_draw')
                      ->alias('l')
                      ->field('l.*, u.user_id, u.nickname, u.mobile, u.head_pic')
                      ->join('__USERS__ u','l.good_luck_user_id = u.user_id','LEFT')
                      ->where('tpl_id', $luck['tpl_id'])
                      ->where('status', 1)
                      ->order('l.luck_id DESC')
                      ->limit($page->firstRow.','.$page->listRows)
                      ->select();


    $this->assign('history_list', $history_list);
    $this->assign('page',$show);// 赋值分页输出
    return $this->fetch();
  }

  // 用户参与记录
  public function join_records(){
    $luck_id = I("luck_id/d");
    $p = I('p/d', 1);
    $luck = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')
            ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods")
            ->where("l.luck_id",$luck_id)->find();
    if(!$luck){
      return $this->error("这个抽奖不存在");
    }

	  $this->assign('luck_id',$luck_id);
    return $this->fetch();
  }

  public function ajax_join_records(){
    $luck_id = I("luck_id/d");
    $p = I('p/d', 1);
    $luck = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t','t.tpl_id = l.tpl_id')
            ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods")
            ->where("l.luck_id",$luck_id)->find();
    if(!$luck){
      return array('result'=> -1,'msg'=>'这个抽奖不存在');
    }

    $users = M('luck_draw_user')->alias('l')
             ->join('__USERS__ u','u.user_id = l.user_id')
             ->where($where)->where("l.luck_id",$luck_id)
             ->field("l.add_time,u.*,count(l.user_id) AS joined_times")
             ->group('l.user_id,l.order_id')->page($p,10)->select();

    $this->assign('users',$users);
    return $this->fetch();
  }

  public function test(){
    $str ="hello";
    $str = substr_cut($str);
    var_dump($str);
    die();
  }

}
