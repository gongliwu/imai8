<?php
/**
 *供应商订单列表
 *
 */
namespace app\admin\controller;
use think\AjaxPage;
use think\Db;
use think\Page;

class SupplierOrder extends Base{
  /*
   * 初始化操作
   */
  public function _initialize() {
      parent::_initialize();
      C('TOKEN_ON',false); // 关闭表单令牌验证
  }

  public function index(){
    /**
    $keyword = I('keyword');
    $where = $keyword ? " goods_name like '%$keyword%' " : "";
    $model = M("certificate_code");
    $count = $model->where('pay_time > 0')->where($where)->count();
    $Page = $pager = new Page($count,10);
    $partner_list = $model->where('pay_time > 0')
                          ->where($where)
                          ->order("`add_time` asc")
                          ->limit($Page->firstRow.','.$Page->listRows)
                          ->select();
    $show  = $Page->show();
    $this->assign('pager',$pager);
    $this->assign('show',$show);
    $this->assign('list',$partner_list);
    **/
    return $this->fetch();
  }

  /**
   * 消费券列表页面
   */
  public function ajax_cc_list() {
      $admin_id = session('admin_id');
      $admin = M('admin')->where("admin_id = {$admin_id}")->find();
      $suppliers_id = $admin['suppliers_id'];

      $keyword = I('keyword');
      $where = $keyword ? " cc.code_sn like '%$keyword%' " : "";
      $model = M("certificate_code");
      $count = $model->alias('cc')->field('cc.*')->join('__GOODS__ g','cc.goods_id = g.goods_id','LEFT')->where("g.suppliers_id = {$suppliers_id}")->where('cc.pay_time > 0')->where($where)->count();
      $Page  = new AjaxPage($count,10);

      //  搜索条件下 分页赋值
      //foreach($condition as $key=>$val) {
      //    $Page->parameter[$key]   =   urlencode($val);
      //}
      $Page->parameter['keyword'] = urlencode($keyword);

      $ccList = $model->alias('cc')->field('cc.*')->join('__GOODS__ g','cc.goods_id = g.goods_id','LEFT')->where("g.suppliers_id = {$suppliers_id}")->where('cc.pay_time > 0')->where($where)->order('cc.pay_time desc')->limit($Page->firstRow.','.$Page->listRows)->select();

      $show = $Page->show();
      $this->assign('ccList',$ccList);
      $this->assign('page',$show);// 赋值分页输出
      $this->assign('pager',$Page);
      return $this->fetch();
  }

  // 消费团购券
  public function ajaxPayCode(){
    $admin_id = session('admin_id');
    $admin = M('admin')->where("admin_id = {$admin_id}")->find();
    $suppliers_id = $admin['suppliers_id'];

    if(IS_POST)
    {
            $data = input('post.');

            $code = M("certificate_code")->where(array('code_sn'=>$data['code_sn']))->find();

            $goods = M("goods")->where("goods_id = {$code['goods_id']}")->find();
            if ($goods['suppliers_id'] != $suppliers_id) {
              $this->error("凭证码不存在!!!",U('Admin/SupplierOrder/index'));
              exit;
            }

            if( ! empty($code) ){
              M("certificate_code")->where(array('code_sn'=>$data['code_sn']))
                                  ->update(array('pay_time' => time()));
              $this->success("操作成功!!!",U('Admin/SupplierOrder/index'));
              exit;
            }

            $this->error("凭证码不存在!!!",U('Admin/SupplierOrder/index'));
            exit;

    }

   return $this->fetch('index');
  }


  public function order_list(){
    echo "string";
    die();
  }

}
