<?php
/**
 * 抽奖
 * Date: 2015-09-09
 */

 namespace app\admin\controller;
 use think\Page;

 class LuckTpl extends Base {
   public function index(){
     $keyword = I('keyword');
     $where = $keyword ? "goods_name like '%$keyword%' " : "";
     $count = M('luck_tpl')->where($where)->count();

     $Page = $pager = new Page($count,10);
     $drawList = M('luck_tpl')->where($where)->order('tpl_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();

     $show  = $Page->show();
     $this->assign('pager',$pager);
     $this->assign('show',$show);
     $this->assign('list',$drawList);
     return $this->fetch();
   }

   public function add(){
     if(IS_POST)
     {
       $data = input('post.');
       if((int)$data['goods_id'] == 0 ){
         $this->error("商品id必填项，必须填数字");
         return;
       }

       if((int)$data['total_times'] == 0 ){
         $this->error("总需人次必填项，必须填数字");
         return;
       }

       if((int)$data['pay_integral'] == 0 ){
         $this->error("每次消耗积分必填项，必须填数字");
         return;
       }

       if((int)$data['start_period'] <= 0 ){
         $this->error("开始期数必填项，必须填数字，大于零");
         return;
       }

       if((int)$data['periods'] <= 0 ){
         $this->error("总期数必填项，必须填数字，大于零");
         return;
       }

       $data['add_time'] = time();
       $data['original_img'] = $data['img_path'];

       $tpl_id = M("luck_tpl")->insertGetId($data);

       $luck = array(
         'tpl_id' => $tpl_id,
         'period' => $data['start_period'],
         'add_time' => time(),
         'is_auto_open' => $data['is_auto_open'],
         'total_times' => $data['total_times'],
       );
       $luck_id = M("luck_draw")->insertGetId($luck);

       $this->create_sn($tpl_id,$luck_id);

       $this->success("操作成功!!!",U('Admin/LuckTpl/index',array('p'=>input('p'))));
       exit;
     }
     $this->assign('original_img',"");
     return $this->fetch();
   }

   // 根据商品id查找商品
   public function ajaxSearchGoods(){
     if(IS_POST)
     {
       $goods_id = I("goods_id/d");
       $goods = M('goods')->where('goods_id',$goods_id)->find();
       if(!$goods){
         return array('status' => -500,'msg' => '商品不存在');
       }
       return array('status' => 1,'msg' => '','goods'=> $goods);
     }

     return array('status'=> -1,'msg'=>'请求格式不对');
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

   public function edit(){
     $id = I('tpl_id');
     if(IS_POST)
     {
             $data = input('post.');
             M("luck_tpl")->update($data);

             $this->success("操作成功!!!",U('Admin/luck_tpl/index'));
             exit;
     }

    $luck_tpl = M("luck_tpl")->find($id);
    $this->assign('luck_tpl',$luck_tpl);
    return $this->fetch('edit');
   }

   /**
    * 删除
    */
   public function del()
   {
       $id = I('post.del_id');
      //  if ($id) {
      //    $luck_draw = M('luck_draw')->where('tpl_id',$id)->find();
      //
      //    if($luck_draw){
      //      exit(json_encode(array('status'=> -100,'msg'=>'设置已经被抽奖列表使用！')));
      //    }
      //    M('luck_tpl')->where("tpl_id = $id")->delete();
      //    exit(json_encode(array('status'=> 1,'msg'=>'删除成功')));
      //  }else{
      //    exit(json_encode(array('status'=>-1,'msg'=>'删除失败')));
      //  }

   }
 }
