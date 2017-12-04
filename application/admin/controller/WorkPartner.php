<?php
/**
 *合作伙伴
 * Date: 2015-09-09
 */

namespace app\admin\controller;
use think\Db;
use think\Page;

class WorkPartner extends Base {

    public function index(){
      $model = M("work_partner");
      $where = "";
      $keyword = I('keyword');
      $where = $keyword ? " name like '%$keyword%' " : "";
      $count = $model->where($where)->count();
      $Page = $pager = new Page($count,10);
      $partner_list = $model->where($where)->order("`add_time` asc")->limit($Page->firstRow.','.$Page->listRows)->select();
      $show  = $Page->show();
      $this->assign('pager',$pager);
      $this->assign('show',$show);
      $this->assign('list',$partner_list);
      return $this->fetch();
    }

    public function add()
    {
      if(IS_POST)
      {
              $data = input('post.');
              $data['add_time'] = time();
              M("work_partner")->insert($data);
              $this->success("操作成功!!!",U('Admin/WorkPartner/index',array('p'=>input('p'))));
              exit;
      }

     return $this->fetch('add');
    }

    public function edit()
    {
      $id = I('id');
      if(IS_POST)
      {
              $data = input('post.');
              M("work_partner")->update($data);

              $this->success("操作成功!!!",U('Admin/WorkPartner/index',array('p'=>input('p'))));
              exit;
      }

     $partner = M("work_partner")->find($id);
     $this->assign('partner',$partner);
     return $this->fetch('edit');
    }

    /**
     * 删除
     */
    public function del()
    {
        $partner_id = I('post.del_id');
        if ($partner_id) {
          M('work_partner')->where("id = $partner_id")->delete();
          exit(json_encode(1));
        }else{
          exit(json_encode(0));
        }

    }
}
