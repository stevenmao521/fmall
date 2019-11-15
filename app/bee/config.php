<?php
return [
    #微信小程序配置
    'wechat' => [
        'wx_appid'=>'wx4fa2926f0aaf664d',
        'wx_appsec'=>'c7f91f24b9e6cf87871703706757a14a',
        #微信登录凭证校验接口
        'wx_loginurl'=>'https://api.weixin.qq.com/sns/jscode2session'
    ],
    #微信支付配置
    'wxpay' => [
        'mch_id'=>'1557728541',
        #统一下单接口
        'pay_url'=>'https://api.mch.weixin.qq.com/pay/unifiedorder',
        'api_sec'=>'upLbeJav5CE8jEjh2oixGZrMiHHpaM4S',
        'notify_url'=>'http://fmall.yuntim.cn/extend/wxpay/callback.php',
        'after_url'=>'http://fmall.yuntim.cn/extend/wxpay/callafter.php'
    ]
];