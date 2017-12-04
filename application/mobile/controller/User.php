<?php
/**
 * tpshop
 * ============================================================================
 *  版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 2015-11-21
 */
namespace app\mobile\controller;

use app\home\logic\UsersLogic;
use app\home\model\Message;
use think\db;
use think\Page;
use think\Request;
use think\Verify;

class User extends MobileBase
{

    public $user_id = 0;
    public $user    = [];

    /*
     * 初始化操作
     */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user); //覆盖session 中的 user
            $this->user    = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }
        $nologin = [
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express',
        ];
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            header("location:" . U('Mobile/User/login'));
            exit;
        }

        $order_status_coment = [
            'WAITPAY'      => '待付款 ', //订单查询状态 待支付
            'WAITSEND'     => '待发货',  //订单查询状态 待发货
            'WAITRECEIVE'  => '待收货',  //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价',  //订单查询状态 待评价
        ];
        $this->assign('order_status_coment', $order_status_coment);
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $goods_collect_count = M('goods_collect')->where("user_id", $this->user_id)->count();                                                                                                                                                  // 我的商品收藏
        $comment_count       = M('comment')->where("user_id", $this->user_id)->count();                                                                                                                                                        // 我的评论数
        $coupon_count        = M('coupon_list')->where("uid", $this->user_id)->count();                                                                                                                                                        // 我的优惠券数量
        $level_name          = M('user_level')->where("level_id", $this->user['level'])->getField('level_name');                                                                                                                               // 等级名称
        $order_count         = M('order')->where("user_id", $this->user_id)->count();                                                                                                                                                          //我的全部订单 (改)
        $count_return        = M('return_goods')->where("user_id=$this->user_id and status<2")->count();                                                                                                                                       //退换货数量
        $wait_pay            = M('order')->where("user_id=$this->user_id and pay_status =0 and order_status = 0  and pay_code != 'cod'")->count();                                                                                             //我的待付款 (改)
        $wait_receive        = M('order')->where("user_id=$this->user_id and order_status= 1 and shipping_status= 1")->count();                                                                                                                //我的待收货 (改)
        $comment             = DB::query("select COUNT(1) as comment from __PREFIX__order_goods as og left join __PREFIX__order as o on o.order_id = og.order_id where o.user_id = $this->user_id and og.is_send = 1 and og.is_comment = 0 "); //我的待评论订单
        $wait_comment        = $comment[0][comment];
        $count_sundry_status = [$wait_pay, $wait_receive, $wait_comment, $count_return];
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count); // 我的订单数 （改）
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
        $this->assign('count_sundry_status', $count_sundry_status); //各种数量
        return $this->fetch();
    }

    public function logout()
    {
        $disabled_third_login = $_SESSION['openid'] ? true : false; # 是否禁用触发微信自动登录: true:禁用; false:启用;

        session_unset();
        session_destroy();
        setcookie('cn', '', time() - 3600, '/');
        setcookie('user_id', '', time() - 3600, '/');
        cookie('no_login_url', null);

        if ($disabled_third_login) {
            $dtl = M('disabled_third_login')->where('session_id', $this->session_id)->find();
            if (empty($dtl)) {
                M('disabled_third_login')->add(['session_id' => $this->session_id, 'is_disabled' => 1]);
            } else {
                M('disabled_third_login')->where('session_id', $this->session_id)->update(['is_disabled' => 1]);
            }
        }

        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic       = new UsersLogic();
        $data        = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic       = new UsersLogic();
        $data        = $logic->get_coupon($this->user_id, input('type'));
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * 确定订单的使用优惠券
     * @time 2017
     * @author lxl
     */
    public function checkcoupon()
    {
        $cartLogic = new \app\home\logic\CartLogic();
                                                                              // 找出这个用户的优惠券 没过期的  并且 订单金额达到 condition 优惠券指定标准的
        $result = $cartLogic->cartList($this->user, $this->session_id, 1, 1); // 获取购物车商品
        if (I('type') == '') {
            $where = " c2.uid = {$this->user_id} and " . time() . " < c1.use_end_time and c1.condition <= {$result['total_price']['total_fee']} ";
        }
        if (I('type') == '1') {
            $where = " c2.uid = {$this->user_id} and c1.use_end_time < " . time() . " or {$result['total_price']['total_fee']}  < c1.condition ";
        }

        $coupon_list = DB::name('coupon')
            ->alias('c1')
            ->field('c1.name,c1.money,c1.condition,c1.use_end_time, c2.*')
            ->join('coupon_list c2', 'c2.cid = c1.id and c1.type in(0,1,2,3) and order_id = 0', 'LEFT')
            ->where($where)
            ->select();
        $this->assign('coupon_list', $coupon_list); // 优惠券列表
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            //  header("Location: " . U('Mobile/User/index'));
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = I('post.username');
        $password = I('post.password');
        $username = trim($username);
        $password = trim($password);
        //验证码验证
        //$verify_code = I('post.verify_code');
        // $verify      = new Verify();
        // if (!$verify->check($verify_code, 'user_login')) {
        //     $res = ['status' => 0, 'msg' => '验证码错误'];
        //     exit(json_encode($res));
        // }
        $logic = new UsersLogic();
        $res   = $logic->login($username, $password);
        if ($res['status'] == 1) {
            $res['url'] = urldecode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            // setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', $nickname, null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $res['result']['user_id']); //用户登录后 需要对购物车 一些操作

            if ($res['result']['mobile'] == '') {
                $res['url'] = U('Mobile/User/userinfo');
            }
        }

        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if ($this->user_id > 0) {
            header("Location: " . U('Mobile/User/index'));
        }

        $reg_sms_enable  = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $username  = I('post.username', '');
            $password  = I('post.password', '');
            $password2 = I('post.password2', '');
            //是否开启注册验证码机制
            $code  = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            if (check_mobile($username)) {
                $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }
            //是否开启注册邮箱验证码机制
            if (check_email($username)) {
                $check_code = $logic->check_validate_code($code, $username);
                if ($check_code['status'] != 1) {
                    $this->error($check_code['msg']);
                }
            }

            $data = $logic->reg($username, $password, $password2);
            if ($data['status'] != 1) {
                $this->error($data['msg']);
            }

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            // setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new \app\home\logic\CartLogic();
            $cartLogic->login_cart_handle($this->session_id, $data['result']['user_id']); //用户登录后 需要对购物车 一些操作
            if ($data['result']['mobile'] == '') {
                $this->success($data['msg'], U('Mobile/User/userinfo'));
                exit();
            }

            $this->success($data['msg'], U('Mobile/User/index'));
            exit;
        }
        $this->assign('regis_sms_enable', $reg_sms_enable);   // 注册启用短信：
        $this->assign('regis_smtp_enable', $reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out') > 0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }
    //我的订单
    public function my_order()
    {
        return $this->fetch();
    }
    /*
     * 全部订单列表
     */
    public function order_list()
    {
        $where = ' user_id=' . $this->user_id . ' and order_type != 2 and luck_id = 0';
        //条件搜索
        if (I('get.type')) {
            $where .= C(strtoupper(I('get.type')));
        }
        $count      = M('order')->where($where)->count();
        $Page       = new Page($count, 10);
        $show       = $Page->show();
        $order_str  = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();

        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v); // 添加属性  包括按钮显示属性 和 订单状态显示属性
                                                        //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data                         = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }
        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = '';
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
                $goods                                                    = M('goods')->where("goods_id", $vv['goods_id'])->find();
                $exchange_integral                                        = $goods['exchange_integral'];
                $order_list[$key]['goods_list'][$kk]["exchange_integral"] = $exchange_integral;
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
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
     * 订单列表
     */
    public function ajax_order_list()
    {

    }

    /*
     * 订单详情
     */
    public function order_detail()
    {
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

    public function express()
    {
        $order_id    = I('get.order_id/d', 195);
        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
        $delivery    = M('delivery_doc')->where("order_id", $order_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('delivery', $delivery);
        return $this->fetch();
    }

    /*
     * 取消订单
     */
    public function cancel_order()
    {
        $id = I('get.id/d');
        //检查是否有积分，余额支付
        $logic = new UsersLogic();
        $data  = $logic->cancel_order($this->user_id, $id);
        if ($data['status'] < 0) {
            $this->error($data['msg']);
        }

        $this->success($data['msg']);
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list   = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data  = $logic->add_address($this->user_id, 0, I('post.'));
            if ($data['status'] != 1) {
                $this->error($data['msg']);
            } elseif (I('post.source') == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', ['address_id' => $data['result']]));
                exit;
            } elseif (I('post.source') == 'luck_order') {
                header('Location:' . U('/Mobile/LuckDraw/orderDetail', [
                    'address_id' => $data['result'],
                    'order_id'   => I('post.order_id'),
                ]));
                exit;
            }

            $this->success($data['msg'], U('/Mobile/User/address_list'));
            exit();
        }
        $p = M('region')->where(['parent_id' => 0, 'level' => 1])->select();
        $this->assign('province', $p);
        //return $this->fetch('edit_address');
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id      = I('id/d');
        $address = M('user_address')->where(['address_id' => $id, 'user_id' => $this->user_id])->find();
        if (IS_POST) {
            $logic = new UsersLogic();
            $data  = $logic->add_address($this->user_id, $id, I('post.'));
            if ($_POST['source'] == 'cart2') {
                header('Location:' . U('/Mobile/Cart/cart2', ['address_id' => $id]));
                exit;
            } else {
                $this->success($data['msg'], U('/Mobile/User/address_list'));
            }

            exit();
        }
        //获取省份
        $p = M('region')->where(['parent_id' => 0, 'level' => 1])->select();
        $c = M('region')->where(['parent_id' => $address['province'], 'level' => 2])->select();
        $d = M('region')->where(['parent_id' => $address['city'], 'level' => 3])->select();
        if ($address['twon']) {
            $e = M('region')->where(['parent_id' => $address['district'], 'level' => 4])->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id     = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(['user_id' => $this->user_id])->save(['is_default' => 0]);
        $row = M('user_address')->where(['user_id' => $this->user_id, 'address_id' => $id])->save(['is_default' => 1]);
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
            exit;
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row     = M('user_address')->where(['user_id' => $this->user_id, 'address_id' => $id])->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(['is_default' => 1]);
        }
        if (!$row) {
            $this->error('操作失败', U('User/address_list'));
        } else {
            $this->success("操作成功", U('User/address_list'));
        }

    }

    /*
     * 评论晒单
     */
    public function comment()
    {
        $user_id = $this->user_id;
        $status  = I('get.status');
        $logic   = new UsersLogic();
        $result  = $logic->get_comment($user_id, $status); //获取评论列表
        if (!empty($result['result'])) {
    			foreach ($result['result'] as $key => $value) {
            		$result['result'][$key]['img'] = unserialize($result['result'][$key]['img']);
        		}
		}

        $this->assign('comment_list', $result['result']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_comment_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *添加评论
     */
    public function add_comment()
    {
        if (IS_POST) {
            // 晒图片
            $files    = request()->file('comment_img_file');
            $save_url = 'public/upload/comment/' . date('Y', time()) . '/' . date('m-d', time());
            foreach ($files as $file) {
                // 移动到框架应用根目录/public/uploads/ 目录下
                $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 3, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    // 输出 jpg
                    $comment_img[] = '/' . $save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($file->getError());
                }
            }
            if (!empty($comment_img)) {
                $add['img'] = serialize($comment_img);
            }

            $user_info       = session('user');
            $logic           = new UsersLogic();
            $add['ttype']    = I('ttype/d');
            $add['goods_id'] = I('goods_id/d');
            $add['luck_id']  = I('luck_id/d', 0);
            $add['email']    = $user_info['email'];
            $hide_username   = I('hide_username');
            if (empty($hide_username)) {
                $add['username'] = $user_info['nickname'];
            }
            $add['order_id']     = I('order_id/d');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank']   = I('goods_rank');
            $add['is']           = I('goods_rank');
            //$add['content'] = htmlspecialchars(I('post.content'));
            $add['content']    = I('content');
            $add['add_time']   = time();
            $add['ip_address'] = getIP();
            $add['user_id']    = $this->user_id;
            $add['is_show']    = 1;

            //添加评论
            $row = $logic->add_comment($add);
            if ($row['status'] == 1) {
            		if($add['luck_id']==0){
            			$this->success('评论成功', U('/Mobile/Goods/goodsInfo', ['id' => $add['goods_id']]));
            		}else{
            			$this->success('评论成功', U('/Mobile/LuckDraw/detail', ['luck_id' => $add['luck_id']]));
            		}

                exit();
            } else {
                $this->error($row['msg']);
            }
        }
        $rec_id      = I('rec_id/d');
        $ttype      = I('ttype/d');
        $order_goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $this->assign('order_goods', $order_goods);
        $this->assign('ttype', $ttype);
        return $this->fetch();
    }

    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $referurl  = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        if (IS_POST) {

            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false;                 //昵称
            I('post.qq') ? $post['qq']             = I('post.qq') : false;                       //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false;                 //头像地址
            I('post.sex') ? $post['sex']           = I('post.sex') : $post['sex']           = 0; // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;      // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;                 //省份
            I('post.city') ? $post['city']         = I('post.city') : false;                     // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;                 //地区
            I('post.email') ? $post['email']       = I('post.email') : false;                    //邮箱


            $email  = I('post.email');
            $mobile = I('post.mobile');
            $code   = I('post.mobile_code', '');
            $scene  = I('post.scene', 6);

            $referurl = urldecode(I('post.referurl'));

//          if (!empty($email)) {
            //              $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
            //              $c && $this->error("邮箱已被使用");
            //              if ($c)
            //                {
            //                    $this->assign('msg', "邮箱已被使用");
            //                    return $this->fetch();
            //                      exit;
            //                  }
            //          }




            if (!$userLogic->update_info($this->user_id, $post)) {
                $this->assign('msg', "保存失败");
                return $this->fetch();
                exit;
            }

        //     // 绑定手机导入积分
        //  if (!empty($mobile)) {
        //      $upfi_list    = M('user_points_from_import')->where('mobile', $mobile)->where('user_id', 0)->select();
        //      $plate_number = $this->user['plate_number'];
        //      foreach ($upfi_list as $k => $upfi) {
        //          M('user_points_from_import')->where('upfi_id', $upfi['upfi_id'])->update(['user_id' => $this->user_id]);
        //          accountLog($this->user_id, 0, $upfi['points'], '导入积分');
        //          if (strlen($plate_number) < 1) {
        //              $plate_number = $upfi['plate_number'];
        //          } else {
        //              $plate_number .= '|' . $upfi['plate_number'];
        //          }
         //
        //      }
        //      if ($this->user['plate_number'] != $plate_number) {
        //          M('users')->where('user_id', $this->user_id)->update(['plate_number' => $plate_number]);
        //      }
        //  }

            // $this->success('msg', "操作成功");
            if (cookie("no_login_url") != null) {
                $referurl = cookie("no_login_url");
                cookie('no_login_url', null);
                // Header("Location: " . $referurl);
                $this->success("操作成功", $referurl);
                exit();
            } else if ($referurl != '') {
                // Header("Location: " . $referurl);
                $this->success("操作成功", $referurl);
                exit();
            } else {
                $referurl = U("Mobile/User/userinfo");
                // Header("Location: " . $referurl);
                $this->success("操作成功", $referurl);
                exit();
            }

        }
        //  获取省份
//      $province = M('region')->where(['parent_id' => 0, 'level' => 1])->select();
        //  获取订单城市
//      $city = M('region')->where(['parent_id' => $user_info['province'], 'level' => 2])->select();
        //  获取订单地区
//      $area = M('region')->where(['parent_id' => $user_info['city'], 'level' => 3])->select();
//      $this->assign('province', $province);
//      $this->assign('city', $city);
//      $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        $this->assign('referurl', $referurl);
        //从哪个修改用户信息页面进来，
//      $dispaly = I('action');
//      if ($dispaly != '') {
//          return $this->fetch("$dispaly");
//          exit;
//      }
        return $this->fetch();
    }

    /*
     * 修改个人信息
     *
     */
    function chang_info(){
    			$userLogic = new UsersLogic();
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false;                 //昵称
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false;                 //头像地址
            I('post.sex') ? $post['sex']           = I('post.sex') : $post['sex']           = 0; // 性别

            if (!$userLogic->update_info($this->user_id, $post)) {
                $res = ['ret' => 16, 'msg' => '保存失败'];
            		exit(json_encode($res));
            }

			$res = ['ret' => 0, 'msg' => '操作成功'];
            	exit(json_encode($res));
    }
    /*
     * 上传头像
     *
     */
    function updateHeadPic(){
    		$ret = array('ret'=>0, 'msg'=>'', 'data'=>'');

    		if ( ! empty($_FILES['head_pic']['name']) ) {
                $time = time();

                # 检查文件格式
                $ext_name = pathinfo($_FILES['head_pic']['name'], PATHINFO_EXTENSION);
                #if ( ! in_array($ext_name, array('xls','xlsx')) ) {
                #    $this->error('文件格式错误');
                #}

                # 目标文件
                $file_name = $time . '.' . $ext_name;
                $file_path_relatively = '/public/upload/head_pic/' . $file_name;
                $file_path_absolute = ROOT_PATH . 'public/upload/head_pic/' . $file_name;
                $_ret = move_uploaded_file($_FILES['head_pic']['tmp_name'], $file_path_absolute);
                if ( ! $_ret ) {
                    $ret['ret'] = 11;
                    $ret['msg'] = '上传文件失败';
                } else {
                		$ret['data'] = $file_path_relatively;
                }
        } else {
        		$ret['ret'] = 10;
        		$ret['msg'] = '请上传文件';
        }

        return json_encode($ret);
    }
	/*
     * 手机绑定页面
     */
    public function mobile_binding()
    {
        $this->assign('url_page', I("url_page"));
        return $this->fetch();
    }
    /*
     * 手机绑定接口
     */
    function mobileBinding(){
        $userLogic = new UsersLogic();

		$mobile = I('post.mobile') ? $post['mobile']     = I('post.mobile') : false;                   //手机
		$code   = I('post.mobile_code', '');                                                 //验证码
		if (!empty($mobile)) {
            $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
            // var_dump($c);

//              $c && $this->error("手机已被使用");
            if ($c) {
//                  $this->assign('msg', "手机已被使用");
//                  return $this->fetch();
                $result = ['ret' => 12, 'msg' => '手机已被使用', 'data' => null];
        			exit(json_encode($result));
            }
            if (!$code) {
//                  $this->assign('msg', "请输入验证码");
//                  return $this->fetch();
                $result = ['ret' => 13, 'msg' => '请输入验证码', 'data' => null];
        			exit(json_encode($result));
            }
            //$this->error('请输入验证码');
            $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
            if ($check_code['status'] != 1) {
//                  $this->error($check_code['msg']);
//                  $this->assign('msg', $check_code['msg']);
//                  return $this->fetch();
                $result = ['ret' => 14, 'msg' => $check_code['msg'], 'data' => null];
        			exit(json_encode($result));
            }
        }

        $post['mobile_validated'] = 1;
        if (!$userLogic->update_info($this->user_id, $post)) {
            $result = ['ret' => 16, 'msg' => '保存失败'];
        		exit(json_encode($result));
        }

      // 绑定手机导入积分
       $upfi_list    = M('user_points_from_import')->where('mobile', $mobile)->where('user_id', 0)->select();
       $plate_number = $this->user['plate_number'];
       foreach ($upfi_list as $k => $upfi) {
           M('user_points_from_import')->where('upfi_id', $upfi['upfi_id'])->update(['user_id' => $this->user_id]);
           accountLog($this->user_id, 0, $upfi['points'], '导入积分');
           if (strlen($plate_number) < 1) {
               $plate_number = $upfi['plate_number'];
           } else {
               $plate_number .= '|' . $upfi['plate_number'];
           }

       }

       if ($this->user['plate_number'] != $plate_number) {
           M('users')->where('user_id', $this->user_id)->update(['plate_number' => $plate_number]);
        }

        $result = ['ret' => 0, 'msg' => '操作成功', 'data' => null];
        exit(json_encode($result));
    }
    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step      = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0) {
            $step = 2;
        }

        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1) {
            $step = 2;
        }

        if ($user_info['email_validated'] == 1 && session('email_step1') != 1) {
            $step = 1;
        }

        if (IS_POST) {
            $email = I('post.email');
            $code  = I('post.code');
            $info  = session('email_code');
            if (!$info) {
                $this->error('非法操作');
            }

            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id)) {
                        $this->error('邮箱已存在');
                    }

                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', ['step' => 2]));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
     * 手机验证
     */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step      = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0) {
            $step = 2;
        }

        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1) {
            $step = 2;
        }

        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1) {
            $step = 1;
        }

        if (IS_POST) {
            $mobile = I('post.mobile');
            $code   = I('post.code');
            $info   = session('mobile_code');
            if (!$info) {
                $this->error('非法操作');
            }

            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2)) {
                        $this->error('手机已存在');
                    }

                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', ['step' => 2]));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data      = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']); // 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) { //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id    = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的咨询
     */
    public function my_consults()
    {
        return $this->fetch();
    }
    // 往期咨询列表 - ajax
    public function ajax_my_consults()
    {
        $p        = I('p/d', 1);
        $goods_id = I("goods_id/d", 0);

        //$consult_type = I('consult_type','0'); // 0全部咨询  1 商品咨询 2 支付咨询 3 配送 4 售后
        $user_id = 0;
//      $username = '';
        if (session('?user')) {
            $user    = session('user');
            $user_id = $user['user_id'];
//        $username = $user['nickname'];
        }
        $where = ['is_show' => 1, 'parent_id' => 0, 'user_id' => $user_id];
        if ($consult_type > 0) {
            $where['consult_type'] = $consult_type;
        }
        $count = M('GoodsConsult')->where($where)->count();
//      $page = new AjaxPage($count,5);
        //      $show = $page->show();
        $list = M('GoodsConsult')->where($where)
            ->order("id desc")
            ->page($p, 10) //limit($page->firstRow.','.$page->listRows)
            ->select();
        $replyList = M('GoodsConsult')->where("parent_id > 0")->order("id desc")->select();

		if($list){
			foreach ($list as $kk => $vv) {
               $good = M('Goods')->where("goods_id", $vv['goods_id'])->find();
               $list[$kk]['good'] = $good;
            }

		}

        $this->assign('consultCount', $count); // 商品咨询数量
        $this->assign('consultList', $list);   // 商品咨询
                                               //      echo '<pre>';var_dump($replyList,$list);exit;

        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('page', $show);           // 赋值分页输出
        return $this->fetch();
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('message');

            $data              = I('post.');
            $data['user_id']   = $this->user_id;
            $user              = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time']  = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type       = [0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购'];
        $count          = M('feedback')->where("user_id", $this->user_id)->count();
        $Page           = new Page($count, 100);
        $Page->rollPage = 2;
        $message        = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage       = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all'); //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count       = M('recharge')->where("user_id", $this->user_id)->count();
            $Page        = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count       = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page        = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count       = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page        = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    /*
     * 初始化密码
     */
    public function set_password()
    {
        // 检查是否第三方登录用户
        $logic = new UsersLogic();
        $data  = $logic->get_info($this->user_id);
        $user  = $data['result'];

        // 检查是否绑定
        if ($user['mobile'] == '') {
            $result = ['ret' => 10, 'msg' => "请先绑定手机!", 'data' => null];
            exit(json_encode($result));
        }

        // 检查是否已经设置密码
        if (!empty($user['password'])) {
            $result = ['ret' => 11, 'msg' => "已经设置了密码，请勿重复设置!", 'data' => null];
            exit(json_encode($result));
        }

        // 设置密码
        $userLogic = new UsersLogic();
        $data      = $userLogic->password($this->user_id, '', I('post.new_password'), I('post.confirm_password'), false);

        // 检查是否设置成功
        if ($data['status'] == -1) {
            $result = ['ret' => 12, 'msg' => $data['msg'], 'data' => null];
            exit(json_encode($result));
        }

        // 设置成功
        $result = ['ret' => 0, 'msg' => $data['msg'], 'data' => null];
        exit(json_encode($result));
    }

    /*
     * 密码修改
     */
    public function password()
    {
        /**
         * //检查是否第三方登录用户
         * $logic = new UsersLogic();
         * $data = $logic->get_info($this->user_id);
         * $user = $data['result'];
         * if ($user['mobile'] == '' && $user['email'] == '')
         * $this->error('请先到电脑端绑定手机', U('/Mobile/User/index'));
         * if (IS_POST) {
         * $userLogic = new UsersLogic();
         * $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password')); // 获取用户信息
         * if ($data['status'] == -1)
         * $this->error($data['msg']);
         * $this->success($data['msg']);
         * exit;
         * }
         * return $this->fetch();
         *
         */

        // 检查是否第三方登录用户
        $logic = new UsersLogic();
        $data  = $logic->get_info($this->user_id);
        $user  = $data['result'];

        // 检查是否绑定
        if ($user['mobile'] == '') {
            $result = ['ret' => 10, 'msg' => "请先绑定手机!", 'data' => null];
            exit(json_encode($result));
        }

        // 修改密码
        $userLogic = new UsersLogic();
        $data      = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));

        // 检查是否修改成功
        if ($data['status'] == -1) {
            $result = ['ret' => 11, 'msg' => $data['msg'], 'data' => null];
            exit(json_encode($result));
        }

        // 修改成功
        $result = ['ret' => 0, 'msg' => $data['msg'], 'data' => null];
        exit(json_encode($result));
    }

    public function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
//            header("Location: " . U('User/Index'));`
        }
        $username = I('username');
        $scene =  I('post.scene', 1);
        $code = I('mobile_code');
        if (IS_POST) {
            if (empty($code)) {
              return $this->error("手机验证码不能为空");
            }
            if (!empty($username)) {
                $logic = new UsersLogic();
                $session_id = session_id();
                if (check_mobile($username)) {
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if ($check_code['status'] != 1) {
                        return $this->error($check_code['msg']);
                    }
                }else{
                  return $this->error("不是手机号码，请检查");
                }
                $field = 'mobile';
                $user = M('users')->where('mobile', $username)->find();
                if ($user) {
                    session('find_password', ['user_id' => $user['user_id'], 'username' => $username,
                        'email'                             => $user['email'], 'mobile'     => $user['mobile'], 'type' => $field]);
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    public function find_pwd()
    {

        if ($this->user_id > 0) {
//            header("Location: " . U('User/Index'));
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password  = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                //$user = get_user_info($check['sender'],1);
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(['password' => encrypt($password)]);
                session('validate_code', null);
                //header("Location:".U('User/set_pwd',array('is_set'=>1)));
                $this->success('新密码已设置行牢记新密码', U('User/index'));
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();

//      if ($this->user_id > 0) {
//          header("Location: " . U('User/index'));
//      }
//      $user = session('find_password');
//      if (empty($user)) {
//          $this->error("请先验证用户名", U('User/forget_pwd'));
//      }
//      $this->assign('user', $user);
//      return $this->fetch();
    }

    public function set_pwd()
    {
//      if ($this->user_id > 0) {
//            header("Location: " . U('User/Index'));
//          $this->redirect('Mobile/User/index');
//      }
//      $check = session('validate_code');
//      if (empty($check)) {
//          header("Location:" . U('User/forget_pwd'));
//      } elseif ($check['is_check'] == 0) {
//          $this->error('验证码还未验证通过', U('User/forget_pwd'));
//      }
//      if (IS_POST) {
//          $password  = I('post.password');
//          $password2 = I('post.password2');
//          if ($password2 != $password) {
//              $this->error('两次密码不一致', U('User/forget_pwd'));
//          }
//          if ($check['is_check'] == 1) {
//              //$user = get_user_info($check['sender'],1);
//              $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
//              M('users')->where("user_id", $user['user_id'])->save(['password' => encrypt($password)]);
//              session('validate_code', null);
//              //header("Location:".U('User/set_pwd',array('is_set'=>1)));
//              $this->success('新密码已设置行牢记新密码', U('User/index'));
//              exit;
//          } else {
//              $this->error('验证码还未验证通过', U('User/forget_pwd'));
//          }
//      }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type   = I('get.type') ? I('get.type') : 'user_login';
        $config = [
            'fontSize' => 40,
            'length'   => 4,
            'useCurve' => true,
            'useNoise' => false,
        ];
        $Verify = new Verify($config);
        $Verify->entry($type);
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    /**
     * 确定收货成功
     */
    public function order_confirm()
    {
        $id   = I('get.id/d', 0);
        $data = confirm_order($id, $this->user_id);
        if (!$data['status']) {
            $this->error($data['msg']);
        } else {
            $model       = new UsersLogic();
            $order_goods = $model->get_order_goods($id);
            $this->assign('order_goods', $order_goods);
            return $this->fetch();
            exit;
        }
    }

    /**
     * 申请退货
     */
    public function return_goods()
    {
        $order_id    = I('order_id/d', 0);
        $order_sn    = I('order_sn', 0);
        $goods_id    = I('goods_id/d', 0);
        $rec_id      = I('rec_id/d', 0);
        $good_number = I('good_number/d', 0); //申请数量
        $spec_key    = I('spec_key');

        $c = M('order')->where(['order_id' => $order_id, 'user_id' => $this->user_id])->count();
        if ($c == 0) {
            $this->error('非法操作');
            exit;
        }

        $return_goods = M('return_goods')
            ->where(['order_id' => $order_id, 'goods_id' => $goods_id, 'spec_key' => $spec_key])
            ->find();

        $order = M('order')->where('order_id', $order_id)->find();
        if (!empty($return_goods)) {
            $this->success('已经提交过退货申请!', U('Mobile/User/return_goods_info', ['id' => $return_goods['id']]));
            exit;
        }
        if (IS_POST) {
            // 晒图片
            if (count($_FILES['return_imgs']['tmp_name']) > 0) {
                $files    = request()->file('return_imgs');
                $save_url = 'public/upload/return_goods/' . date('Y', time()) . '/' . date('m-d', time());
                foreach ($files as $file) {
                    // 移动到框架应用根目录/public/uploads/ 目录下
                    $info = $file->rule('uniqid')->validate(['size' => 1024 * 1024 * 5, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                    if ($info) {
                        // 成功上传后 获取上传信息
                        $return_imgs[] = '/' . $save_url . '/' . $info->getFilename();
                    } else {
                        // 上传失败获取错误信息
                        $this->error($file->getError());
                    }
                }
                if (!empty($return_imgs)) {
                    $data['imgs'] = implode(',', $return_imgs);
                }
            }
            $data['order_id'] = $order_id;
            $data['order_sn'] = $order_sn;
            $data['goods_id'] = $goods_id;
            $data['addtime']  = time();
            $data['user_id']  = $this->user_id;
            $data['type']     = I('type'); // 服务类型  退货 或者 换货

            $data['return_reason'] = I('return_reason');
            $data['reason']        = I('reason');   // 问题描述
            $data['spec_key']      = I('spec_key'); // 商品规格
            $res                   = M('return_goods')->add($data);
            $data['return_id']     = $res; //退换货id

            $order                 = M('order')->where('order_id', $order_id)->find();
            $return_type           = C('RETURN_REASON');
            $data['return_reason'] = $return_type[I('return_reason')];

            // $this->assign('data', $data);
            // $this->assign('order', $order);
            // return $this->fetch('return_goods_info'); //申请成功
            //                                             //            $this->success('申请成功,客服第一时间会帮你处理', U('Mobile/User/order_list'));
            // exit;
            $this->redirect('User/return_goods_info', array('id'=>$res));

        }

        $region_id[]    = tpCache('shop_info.province');
        $region_id[]    = tpCache('shop_info.city');
        $region_id[]    = tpCache('shop_info.district');
        $region_id[]    = 0;
        $return_address = M('region')->where("id in (" . implode(',', $region_id) . ")")->getField('id,name');
        $this->assign('return_address', $return_address);

        $good = M('goods')->where("goods_id", $goods_id)->find();
        $goods = M('order_goods')->where("rec_id", $rec_id)->find();
        $goods['exchange_integral'] = $good['exchange_integral'];

        //查找订单收货地址
        $region      = M('order')->field('consignee,country,province,city,district,twon,address,mobile')->where("order_id = $order_id")->find();
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('region', $region);
        $this->assign('goods', $goods);
        $this->assign('order_id', $order_id);
        $this->assign('order_sn', $order_sn);
        $this->assign('goods_id', $goods_id);
        $this->assign('order', $order);//echo '<pre>';var_dump($goods);exit;
        $this->assign('return_reasons', C('RETURN_REASON'));
        return $this->fetch();
    }

    /**
     * 撤销申请
     */
    public function cancel_return_goods() {
        $id = I('id/d', 0);

    }

    /**
     * 退换货列表
     */
    public function return_goods_list()
    {
        //退换货商品信息
        $count        = M('return_goods')->where("user_id", $this->user_id)->count();
        $pagesize     = C('PAGESIZE');
        $page         = new Page($count, $pagesize);
        $list         = M('return_goods')->where("user_id", $this->user_id)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id'); //获取商品ID
        if (!empty($goods_id_arr)) {
            $goodsList = M('goods')->where("goods_id", "in", implode(',', $goods_id_arr))->getField('goods_id,goods_name');
        }

        $this->assign('goodsList', $goodsList);
        $this->assign('list', $list);
        $this->assign('page', $page->show()); // 赋值分页输出
        if (I('is_ajax')) {
            return $this->fetch('ajax_return_goods_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  退货详情
     */
    public function return_goods_info()
    {
        $id           = I('id/d', 0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        if ($return_goods['imgs']) {
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);
        }

        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();
        $order = M('order')->where('order_id', $return_goods['order_id'])->find();

        $this->assign('goods', $goods);
        $this->assign('return_goods', $return_goods);
        $return_type = C('RETURN_REASON');
        $this->assign('return_reason', $return_type[$return_goods['return_reason']]);
//      echo '<pre>';var_dump($return_goods,$return_type);exit;
        $this->assign('order', $order);
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id    = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment  = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        C('TOKEN_ON', true);
        if (IS_POST) {
            $this->verifyHandle('withdrawals');
            $data                = I('post.');
            $data['user_id']     = $this->user_id;
            $data['create_time'] = time();
            $distribut_min       = tpCache('basic.min'); // 最少提现额度
            if ($data['money'] < $distribut_min) {
                $this->error('每次最少提现额度' . $distribut_min);
                exit;
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                exit;
            }
            $withdrawal = M('withdrawals')->where(['user_id' => $this->user_id, 'status' => 0])->sum('money');
            if ($this->user['user_money'] < ($withdrawal + $data['money'])) {
                $this->error('您有提现申请待处理，本次提现余额不足');
            }
            if (M('withdrawals')->add($data)) {
                $this->success("已提交申请");
                exit;
            } else {
                $this->error('提交失败,联系客服!');
                exit;
            }
        }

        $withdrawals_where['user_id'] = $this->user_id;
        $count                        = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize                     = C('PAGESIZE');
        $page                         = new Page($count, $pagesize);
        $list                         = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show()); // 赋值分页输出
        $this->assign('list', $list);         // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
            exit;
        }
        $order_count         = M('order')->where("user_id", $this->user_id)->count();                            // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id", $this->user_id)->count();                    // 我的商品收藏
        $comment_count       = M('comment')->where("user_id", $this->user_id)->count();                          //  我的评论数
        $coupon_count        = M('coupon_list')->where("uid", $this->user_id)->count();                          // 我的优惠券数量
        $level_name          = M('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        $this->assign('level_name', $level_name);
        $this->assign('order_count', $order_count);
        $this->assign('goods_collect_count', $goods_collect_count);
        $this->assign('comment_count', $comment_count);
        $this->assign('coupon_count', $coupon_count);
        $this->assign('user_money', $this->user['user_money']); //用户余额
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count                        = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize                     = C('PAGESIZE');
        $page                         = new Page($count, $pagesize);
        $list                         = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show()); // 赋值分页输出
        $this->assign('list', $list);         // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     * 删除已取消的订单
     */
    public function order_del()
    {
        $user_id  = $this->user_id;
        $order_id = I('get.order_id/d');
        $order    = M('order')->where(['order_id' => $order_id, 'user_id' => $user_id])->find();
        if (empty($order)) {
            return $this->error('订单不存在');
            exit;
        }
        $res    = M('order')->where("order_id=$order_id and order_status=3")->delete();
        $result = M('order_goods')->where("order_id=$order_id")->delete();
        if ($res && $result) {
            return $this->success('成功', "mobile/User/order_list");
            exit;
        } else {
            return $this->error('删除失败');
            exit;
        }
    }

    /**
     * 我的关注
     * $author lxl
     * $time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     * 待收货列表
     * $author lxl
     * $time   2017/1
     */
    public function wait_receive()
    {
        $where = ' user_id=' . $this->user_id;
        //条件搜索
        if (I('type') == 'WAITRECEIVE') {
            $where .= C(strtoupper(I('type')));
        }
        $count      = M('order')->where($where)->count();
        $pagesize   = C('PAGESIZE');
        $Page       = new Page($count, $pagesize);
        $show       = $Page->show();
        $order_str  = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        //获取订单商品
        $model = new UsersLogic();
        foreach ($order_list as $k => $v) {
            $order_list[$k] = set_btn_order_status($v); // 添加属性  包括按钮显示属性 和 订单状态显示属性
                                                        //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
            $data                         = $model->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];
        }

        //统计订单商品数量
        foreach ($order_list as $key => $value) {
            $count_goods_num = '';
            foreach ($value['goods_list'] as $kk => $vv) {
                $count_goods_num += $vv['goods_num'];
            }
            $order_list[$key]['count_goods_num'] = $count_goods_num;
            //订单物流单号
            $invoice_no                   = M('DeliveryDoc')->where("order_id", $value['order_id'])->getField('invoice_no', true);
            $order_list[$key][invoice_no] = implode(' , ', $invoice_no);
        }
        $this->assign('page', $show);
        $this->assign('order_list', $order_list);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_wait_receive');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @time 2016/09/01
     * @author dyr
     */
    public function message_notice()
    {
        return $this->fetch('user/message_notice');
    }

    /**
     * ajax用户消息通知请求
     * @time 2016/09/01
     * @author dyr
     */
    public function ajax_message_notice()
    {
        $type          = I('type', 0);
        $user_logic    = new UsersLogic();
        $message_model = new Message();
        if ($type == 1) {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
            $user_logic->setSysMessageForRead();
        } else if ($type == 2) {
            //活动消息：后续开发
            $user_sys_message = [];
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $this->assign('messages', $user_sys_message);
        return $this->fetch('user/ajax_message_notice');

    }
    //开发期过渡页面
    public function development()
    {
        $type = I('type', 0);
        $this->assign('type', $type);
        return $this->fetch();
    }
    /**
     * 设置消息通知
     */
    public function set_notice()
    {
        //暂无数据
        return $this->fetch();
    }

    /**
     * 删除手机绑定时上个页面
     * @return boolean
     */
    public function remove_before_binging_url(){
      session('before_binging_url',null);
    }

    public function is_show_mobile_bindding(){
      // 直接点击用户面板的手机绑定菜单时：
      $url_page = I("url_page");
      if(!empty($url_page) && $url_page == 'direct'){
        return 0;
      }
      header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
      $mobile_binding_show_num = session('mobile_binding_show_num');
      if(empty($mobile_binding_show_num)){
        session('mobile_binding_show_num',0);
      }

      $num = (int)session('mobile_binding_show_num');
      session('mobile_binding_show_num',$num + 1);

  		return session('mobile_binding_show_num');
    }
}
