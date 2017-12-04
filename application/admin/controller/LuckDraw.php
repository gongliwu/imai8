<?php
/**
 * 抽奖
 * Date: 2015-09-09
 */

namespace app\admin\controller;

use think\Page;

class LuckDraw extends Base
{
    public function index()
    {
        $keyword = I('keyword');
        $where   = $keyword ? "t.goods_name like '%$keyword%' " : "";

        $count = M('luck_draw')->alias('l')->join('__LUCK_TPL__ t', 't.tpl_id = l.tpl_id')->where($where)->count();

        $Page     = $pager     = new Page($count, 10);
        $drawList = M('luck_draw')->alias('l')
                        ->join('__LUCK_TPL__ t', 't.tpl_id = l.tpl_id')
                        // ->join('__ORDER__ o', 'l.luck_id=o.luck_id', 'left')
                        ->where($where)
                        ->field("l.*,t.goods_id,t.goods_name,t.original_img,t.periods,t.pay_integral")
                        ->order('luck_id DESC')
                        ->limit($Page->firstRow . ',' . $Page->listRows)
                        ->select();

        // $luck_id_list = array();
        // foreach ($drawList as $draw) {
        //     $luck_id_list[] = $draw['luck_id'];
        // }
        // $order_list = M('Order')->where('luck_id', array('in', $luck_id_list))
        //                         ->order('luck_id DESC')->select();
        // $luck_id_dict = array();
        // foreach ($order_list as $order) {
        //     $luck_id_dict[$order['luck_id']] = $order['shipping_status'];
        // }

        // foreach ($drawList as $draw) {
        //     $draw['shipping_status'] = 0;
        //     if (isset($luck_id_dict[$draw['luck_id']])) {
        //         $draw['shipping_status'] = $luck_id_dict[$draw['luck_id']];
        //     }
        // }

        $show = $Page->show();
        $this->assign('pager', $pager);
        $this->assign('show', $show);
        $this->assign('list', $drawList);
        return $this->fetch();
    }

    // 根据商品id查找商品
    public function ajaxSearchGoods()
    {
        if (IS_POST) {
            $goods_id = I("goods_id/d");
            $goods    = M('goods')->where('goods_id', $goods_id)->find();
            if (!$goods) {
                return ['status' => -500, 'msg' => '商品不存在'];
            }
            return ['status' => 1, 'msg' => '', 'goods' => $goods];
        }

        return ['status' => -1, 'msg' => '请求格式不对'];
    }

    // 根据抽奖id查找参与抽奖人数
    public function getUsersByLuckId()
    {
        $luck_id   = I("luck_id/d");
        $luck_draw = M("luck_draw")->where('luck_id', $luck_id)->find();
        if (!$luck_draw) {
            return $this->error("这个抽奖不存在");
        }

        $count = M("luck_draw_user")->where("luck_id", $luck_id)->count();
        if ($count <= 0) {
            return $this->error("暂时还没有人参与");
        }

        $keyword = I('keyword');
        $where   = $keyword ? "u.nickname like '%$keyword%' or u.mobile like '%$keyword%'" : "";

        $user_count = M('luck_draw_user')->alias('l')
            ->join('__USERS__ u', 'u.user_id = l.user_id')
            ->where($where)->where("l.luck_id", $luck_id)->group('l.user_id,l.add_time')->count();

        $Page = $pager = new Page($user_count, 10);

        $users = M('luck_draw_user')->alias('l')
            ->join('__USERS__ u', 'u.user_id = l.user_id')
            ->where($where)->where("l.luck_id", $luck_id)
            ->field("l.add_time,u.*,count(l.user_id) AS joined_times")
            ->group('l.user_id,l.add_time')->limit($Page->firstRow . ',' . $Page->listRows)
            ->order('add_time desc')->select();

        $show = $Page->show();
        $this->assign('pager', $pager);
        $this->assign('luck_draw', $luck_draw);
        $this->assign('users', $users);
        $this->assign('show', $show);
        return $this->fetch('users');
    }

    // 洗牌 抽奖
    private function washLuck($luck_id)
    {
        $luck_sn_list = M('luck_draw_user')->where('luck_id', $luck_id)->order('id DESC')->select();
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
    private function addLuckDraw($luck_draw)
    {
        $luck_tpl = M('luck_tpl')->where('tpl_id', $luck_draw['tpl_id'])->find();
        $data     = [
            'tpl_id'       => $luck_draw['tpl_id'],
            'period'       => $luck_draw['period'] + 1,
            'add_time'     => time(),
            'is_auto_open' => $luck_tpl['is_auto_open'],
            'total_times'  => $luck_tpl['total_times'],
        ];

        $luck_id = M('luck_draw')->insertGetId($data);
        $this->create_sn($luck_draw['tpl_id'], $luck_id);
    }

    // 生成抽奖号码
    private function create_sn($tpl_id, $luck_id)
    {
        $tpl       = M("luck_tpl")->where('tpl_id', $tpl_id)->find();
        $luck_draw = M("luck_draw")->where('luck_id', $luck_id)->find();
        $times     = $tpl['total_times'];
        $start     = 10000000;
        for ($i = 1; $i < $times + 1; $i++) {
            $start += 1;
            $data = [
                'luck_id'  => $luck_id,
                'luck_sn'  => $start,
                'add_time' => time(),
            ];
            M("luck_sn")->insert($data);
        }
    }

    // 手动开奖
    public function openLuck()
    {
        $luck_id   = I("luck_id/d");
        $luck_draw = M("luck_draw")->where('luck_id', $luck_id)->find();
        if (!$luck_draw) {
            return $this->error("这个抽奖设置不存在");
        }

        $count = M("luck_draw_user")->where("luck_id", $luck_id)->count();
        if ($count <= 0) {
            return $this->error("暂时还没有人参与");
        }

        $keyword = I('keyword');
        $where   = $keyword ? "u.nickname like '%$keyword%' or u.mobile like '%$keyword%'" : "";

        /**
        $user_count = M('luck_draw_user')->alias('l')
            ->join('__USERS__ u', 'u.user_id = l.user_id')
            ->where($where)->where("l.luck_id", $luck_id)->group('l.user_id,l.add_time')->count();

        $Page = $pager = new Page($user_count, 10);

        $users = M('luck_draw_user')->alias('l')
            ->join('__USERS__ u', 'u.user_id = l.user_id')
            ->where($where)->where("l.luck_id", $luck_id)
            ->field("l.add_time,l.order_id,u.*,count(l.user_id) AS joined_times")
            ->group('l.user_id,l.add_time')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $show = $Page->show();
        $this->assign('pager', $pager);
        $this->assign('luck_draw', $luck_draw);
        $this->assign('users', $users);
        $this->assign('show', $show);
        return $this->fetch('openLuck');
        **/
        $users = M('luck_draw_user')->alias('l')
            ->join('__USERS__ u', 'u.user_id = l.user_id')
            ->where($where)->where("l.luck_id", $luck_id)
            ->field("l.add_time,l.order_id,u.*,count(l.user_id) AS joined_times")
            ->group('l.user_id,l.add_time')->order('l.add_time desc')->select();

        $user_count = count($users);

        $this->assign('luck_draw', $luck_draw);
        $this->assign('users', $users);
        $this->assign('user_count', $user_count);
        return $this->fetch('openLuck');
    }

    public function ajaxOpenLuck()
    {
        $luck_id  = I("luck_id/d");
        $user_id  = I("user_id/d");
        $order_id = I("order_id");

        $luck_draw = M("luck_draw")->where('luck_id', $luck_id)->find();
        if (!$luck_draw) {
            return ['status' => -500, 'msg' => '这个抽奖设置不存在'];
        }

        if ($luck_draw['status'] == 1) {
            return ['status' => -500, 'msg' => '已经开过奖了'];
        }

        $luck_tpl = M('luck_tpl')->where('tpl_id', $luck_draw['tpl_id'])->find();

        if (($luck_draw['is_auto_open'] !== 1) and ($luck_draw['joined_times'] !== $luck_draw['total_times'])) {
            return ['status' => -400, 'msg' => '达不到开奖条件'];
        }

        $good_luck_user_id = $user_id;

        $where           = ['luck_id' => $luck_id, 'user_id' => $user_id, 'order_id' => $order_id];
        $luck_draw_users = M("luck_draw_user")->where($where)->order('id DESC')->select();
        $luck_draw_user  = $luck_draw_users[0];

        // 中奖号码
        $good_luck_sn = $luck_draw_user['luck_sn'];

        // 中奖用户
        $user = M('users')->where('user_id', $good_luck_user_id)->find();

        // 中奖用户参与人次统计
        $good_luck_joined_times = M('luck_draw_user')->where($where)->count();
        $good_luck_total_times  = M('luck_draw_user')->where(['luck_id' => $luck_id, 'user_id' => $user_id])->count();

        $where = ['luck_id' => $luck_id, 'luck_sn' => $good_luck_sn];
        M("luck_draw_user")->where($where)->update(['status' => 2]);

        M('luck_draw')->where('luck_id', $luck_id)->update([
            'luck_sn'                => $good_luck_sn,
            'status'                 => 1,
            'good_luck_user_id'      => $good_luck_user_id,
            'good_luck_nickname'     => $user['nickname'],
            'good_luck_head_pic'     => $user['head_pic'],
            'good_luck_joined_times' => $good_luck_joined_times,
            'good_luck_total_times'  => $good_luck_total_times,
            'luck_result_time'       => time(),
        ]);

        #M("OrderGoods")->where('luck_id', $luck_id)->update(['luck_status' => 2]);
        M("OrderGoods")->where('luck_id', $luck_id)->update(['luck_status' => 1]);
        M("OrderGoods")->where('order_id', $order_id)->update(['luck_status' => 2]);

        // 插入下一期抽奖记录
        if ($luck_draw['period'] < $luck_tpl['periods']) {
            $this->addLuckDraw($luck_draw);
        }

        return ['status' => 1, 'msg' => '开奖成功！'];
    }

    /**
     * 删除
     */
    public function del()
    {
        $id = I('post.del_id');
        if ($id) {
            $luck_draw = M('luck_draw')->where('luck_id', $id)->find();

            if ($luck_draw['joined_times'] > 0) {
                exit(json_encode(['status' => -100, 'msg' => '已经有人参与抽奖，不能删除模板！']));
            }
            M('luck_draw')->where("luck_id = $id")->delete();
            exit(json_encode(['status' => 1, 'msg' => '删除成功']));
        } else {
            exit(json_encode(['status' => -1, 'msg' => '删除失败']));
        }

    }
}
