<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/18
 * Time: 14:20
 */

namespace application\index\controller;



use think\Db;
use think\Exception;
use WxPayNotify;


class Order extends WxPayNotify
{


    /**
     * 统一下单并获取JsApiParameters和共享收货地址
     * @return array [JsApiParameters,$editAddress]
     * @throws \WxPayException
     */
    public function unifiedOrder(){
            $tools = new \JsApiPay();
            $post_param = input('post.');
            $time_start = date("YmdHis");
            $time_expire = date("YmdHis", time() + 600);
            $openid = cookie('openid');
            $trade_no = 'asdf';
            $body = $post_param['body'];
            $total_fee = $post_param['Total_fee'];
            $goods_id = $post_param['goods_id'];
            $goods_num = $post_param['goods_num'];

            //②、统一下单
            $input = new \WxPayUnifiedOrder();
            $input->SetBody($body);                                           //商品描述
            $input->SetAttach("test");
            $input->SetOut_trade_no("sdkphp".date("YmdHis"));                 //商户订单号
            $input->SetTotal_fee($total_fee);                                                //金额 单位分
            $input->SetTime_start($time_start);
            $input->SetTime_expire($time_expire);
            $input->SetGoods_tag("test");
            $input->SetNotify_url("http://paysdk.weixin.qq.com/notify.php");          //通知地址
            $input->SetTrade_type("JSAPI");                                           //交易类型
            $input->SetOpenid($openid);                                                      //用户openid
            $config = new \WxPayConfig();
            $order = \WxPayApi::unifiedOrder($config, $input);
            $jsApiParameters = $tools->GetJsApiParameters($order);
            Db::name('order')->insert(['openid'=>$openid,'trade_no'=>$trade_no,'total_fee'=>$total_fee,'goods_id'=>$goods_id,'goods_num'=>$goods_num,'order_body'=>$body,'order_status'=>0,'time_start'=>$time_start,'time_expire'=>$time_expire]);

            return json($jsApiParameters);
    }

    public function NotifyProcess($objData, $config, &$msg)
    {
        $data = $objData->GetValues();
        //TODO 1、进行参数校验
        if(!array_key_exists("return_code", $data)
            ||(array_key_exists("return_code", $data) && $data['return_code'] != "SUCCESS")) {
            //TODO失败,不是支付成功的通知
            //如果有需要可以做失败时候的一些清理处理，并且做一些监控
            $msg = "异常异常";
            return false;
        }
        if(!array_key_exists("transaction_id", $data)){
            $msg = "输入参数不正确";
            return false;
        }

        //TODO 2、进行签名验证
        try {
            $checkResult = $objData->CheckSign($config);
            if($checkResult == false){
                //签名错误
                return false;
            }
        } catch(Exception $e) {
            return false;
        }

        //TODO 3、处理业务逻辑
        $notfiyOutput = array();


        //查询订单，判断订单真实性
        if(!$this->Queryorder($data["transaction_id"])){
            $msg = "订单查询失败";
            return false;
        }
        $out_trade_no = $data['out_trade_no'];
        Db::name('order')->where('trade_no',$out_trade_no)->update(["order_status"=>1]);
        $order_info = Db::name('order')->where('trade_no',$out_trade_no)->field('openid, goods_id,goods_num')->select();
        $opend_id = $order_info['openid'];
        $goods_id = $order_info['goods_id'];
        $goods_num = $order_info['goods_num'];
        $is_vip = Db::name('vip_user')->where('openid',$opend_id)->value('openid');   #判断该用户是不是vip用户
        if ($is_vip){
            Db::name('vip_user')->where('openid',$opend_id)->update(['goods_num'=>['exp','goods_num+'.$goods_num]]);
        }
        else{
            Db::name('vip_user')->insert(['openid'=>$opend_id,'goods_id'=>$goods_id,'goods_num'=>$goods_num]);
        }
        return true;
    }

}
