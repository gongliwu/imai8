/**
 * addcart 将商品加入购物车
 * @goods_id  商品id
 * @num   商品数量
 * @form_id  商品详情页所在的 form表单
 * @to_catr 加入购物车后再跳转到 购物车页面 默认不跳转 1 为跳转
 * layer弹窗插件请参考http://layer.layui.com/mobile/
 */
function AjaxAddCart(goods_id,num,to_catr)
{
    //如果有商品规格 说明是商品详情页提交
    if($("#buy_goods_form").length > 0){
        $.ajax({
            type : "POST",
            url:"/index.php?m=Mobile&c=Cart&a=ajaxAddCart&is_buy=1",
            data : $('#buy_goods_form').serialize(),// 你的formid 搜索表单 序列化提交
            dataType:'json',
            success: function(data){console.log(data)
				// 加入购物车后再跳转到 购物车页面
			    if(data.status < 0)
				{
					layer.msg(data.msg,{time: 2000});
					return false;
				}
			   if(to_catr == 1)  //直接购买
			   {
				   location.href = "/index.php?m=Mobile&c=Cart&a=cart2";
			   }else{
			    // var cart_num = parseInt($('#tp_cart_info').html())+parseInt($('#goods_num').val());
				    var cart_num = data.result;
				    if ($('#tp_cart_info')) {
				    		$('#tp_cart_info').show().html(cart_num);
				    };

				    layer.msg("已加入购物车",{time:2000});
			    }
            }
        });
    }else{ //否则可能是商品列表页 、收藏页商品点击加入购物车
        $.ajax({
            type : "POST",
            url:"/index.php?m=Home&c=Cart&a=ajaxAddCart",
            data :{goods_id:goods_id,goods_num:num} ,
			dataType:'json',
            success: function(data){

				   if(data.status == -1)
				   {
					    //layer.open({content: data.msg,time: 2});
						location.href = "/index.php?m=Mobile&c=Goods&a=goodsInfo&id="+goods_id;
				   }
				   else
				   {
					    if(data.status < 0)
						{
							layer.msg(data.msg,{time:2000});
							return false;
						}
					    cart_num = data.result;
					    //parseInt($('#tp_cart_info').html())+parseInt(num);
					    $('#tp_cart_info').show().html(cart_num);
				    		layer.msg( data.msg,{time: 2000});
						return false;
				   }
            }
        });
    }
}
//更新购物车
function updateCart(){
	 var cart_cn = getCookie('cn');
	  if(cart_cn == ''){
		$.ajax({
			type : "GET",
			url:"/index.php?m=Home&c=Cart&a=header_cart_list",//+tab,
			success: function(data){
				cart_cn = getCookie('cn');
				// $('#cart_quantity').html(cart_cn);
			}
		});
	  }
	  if (cart_cn>0) {
	  	$('#cart_quantity').show().html(cart_cn);
	  }else{
	  	$('#cart_quantity').hide().html(cart_cn);
	  }

}
// 点击收藏商品
function collect_goods(goods_id,is_all){
  console.log(goods_id);
	$.ajax({
		type : "GET",
		dataType: "json",
		url:"/index.php?m=Mobile&c=goods&a=collect_goods&goods_id="+goods_id,//+tab,
		success: function(data){
      if (!is_all) {
          alert(data.msg);
      }

		}
	});
}
