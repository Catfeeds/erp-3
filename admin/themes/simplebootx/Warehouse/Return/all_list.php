<admintpl file="header" />
</head>
<body>
<div class="wrap">
    <ul class="nav nav-tabs">
        <li class="active"><a href="{:U('Index/index')}">采购订单</a></li>
        <!--<li><a href="{:U('Index/create')}">建立采购</a></li>-->
    </ul>
    <fieldset>
    <table class="table table-hover table-bordered table-list">
        <thead>
        <tr>
            <th width="50">ID</th>
            <th>采购总数量</th>
            <th>收到总数量</th>
            <th>供应商</th>
            <th>采购总价</th>
            <th>采购人员</th>
            <th>快递号</th>
            <th>备注</th>
            <th>建立日期</th>
            <th>更新日期</th>
            <th>状态</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <foreach name="proList" item="item">
            <tr>
                <td width="50">{$item.id_purchase}</td>
                <td><span class="totalPurchase{$item.id_purchase}">{$item.total}</span>
                </td>
                <td><span class="notReceivedNumber{$item.id_purchase}">{$item.total_received}</span></td>
                <td>{$item.supplier_name}</td>
                <td>{$item.price}</td>
                <td>{$item.user_login}</td>
                <td>{$item.track_number}</td>
                <td>{$item.remark}</td>
                <td>{$item.created_at}</td>
                <td>{$item.updated_at}</td>
                <td><php>echo $item['status']==1?'已完成':'未完成';</php></td>
                <td width="130">
                    <!--<php>if($item['not_received_number']>0){</php>
                    <input type="text" style="width: 130px;" name="not_received_number" required value="" placeholder="请输入收到数量"/>
                    <input type="button" orderid="{$item.id}"   class="btn updateNumber" value="更新">
                    <php>}</php>-->
                    <!--<a href="javascript:void(0);" class="setChildSkuQty" orderid="{$item.id}">采购明细</a>|-->
                    <a href="{:U('index/edit',array('id'=>$item['id_purchase']))}">编辑</a>
                </td>
            </tr>
            <tr class="hide showPurchase{$item.id}">
                <td colspan="30">
                    <form action="javascript:void(0);" method="post" class="form-horizontal" enctype="multipart/form-data">
                        <input type="hidden" name="order_id" value="{$item.id}">
                    <table class="table table-hover table-bordered table-list">
                        <thead><tr style="font-weight: bold;background:#f5f5f5;"><td>属性</td><td>采购数量</td><td>未收到数量</td><td>采购价格</td><td>收货数量</td></tr></thead>
                        <tbody>
                        <php>
                            if($item['product_option_id']){
                            $getAttrQty = unserialize($item['product_option_id']);
                            if(is_array($getAttrQty)){
                            foreach($getAttrQty as $qtyData){
                            $price = $qtyData['price']?$qtyData['price']:'';
                            $notReceive = isset($qtyData['not_receive'])?$qtyData['not_receive']:$qtyData['qty'];
                            echo '<tr><td>'.$qtyData['attr_name'].'</td><td>'.$qtyData['qty'].'</td>
                            <td><span class="rowQty" qty="'.$notReceive.'">'.$notReceive.'</span></td>
                            <td>'.$price.'</td>
                            <td><input type="text" class="receiveQty" value=""   name="receive_qty['.$qtyData['option_id'].']" required placeholder="请输入收到数量"> </td>
                            </tr>';
                            }
                            }
                            }
                        </php>
                        <tr style="font-weight: bold;background:#f5f5f5;">
                            <td colspan="30"><button  orderid="{$item.id}" type="button" class="btn btn-primary submitStockForm">提交</button></td>
                        </tr>
                        </tbody>
                    </table>
                    </form>
                </td>
            </tr>
        </foreach>
        </tbody>
    </table>

    <div class="pagination">{$page}</div>
    </fieldset>
</div>
<script src="__PUBLIC__/js/common.js"></script>
<script type="text/javascript">
    $('.updateNumber').click(function(){
        var getOrderId = $(this).attr('orderid');
        var getQty = $(this).parent().find('input[name=not_received_number]').val();
        var totalPurchase = $('.totalPurchase'+getOrderId).text();
        getQty  = parseInt(getQty);
        if (getQty > parseInt(totalPurchase)) {
            alert('输入超过了总采购数量,请重新输入。');
            return false;
        }
        if(getQty){
            $.post("{:U('Purchase/setReceivedNumber')}",{'order_id':getOrderId,'qty':getQty},function(data){
                var getData = $.parseJSON(data);
                if(getData.status){alert('更新成功');$('.notReceivedNumber'+getOrderId).html(getData.not_received);}else{alert(getData.message);}
            });
        }else{
            alert('库存只能为整数。');
        }
    });
    $('.setChildSkuQty').click(function(){
        var curObj = $(this);
        var getTagName = $(this).attr('orderid');
        var getAction  = $(this).text();
        if(getAction=='采购明细'){
            curObj.text('隐藏');console.debug(curObj);
            $('.showPurchase'+getTagName).show();
        }else{
            $(this).html('采购明细');$('.showPurchase'+getTagName).hide();
        }
        $('.setChildSkuQty').each(function(i){
            var getClassN = $(this).attr('orderid');
            if(getClassN!=getTagName){
                $('.showPurchase'+getClassN).hide();
                $(this).html('采购明细');
            }
        });
    });
    $('.submitStockForm').click(function(){
        var getOrderId = $(this).attr('orderid');var postFlag = true;
        var receiveQty = [];
        $('.showPurchase'+getOrderId+' .receiveQty').each(function(){
            var getPurQty = parseInt($(this).parent().parent().find('.rowQty').attr('qty'));
            var getInputQty= parseInt($(this).val());
            if(getInputQty>getPurQty){
                postFlag = false;$(this).focus();
            }
        });

        if(postFlag){
            var getSerialize  =  $('.showPurchase'+getOrderId+' form').serializeArray();
            $.post("{:U('Purchase/setReceivedNumber')}",getSerialize,function(data){
                var getJsonData =  $.parseJSON(data);
                if(getJsonData.status){
                    //if(getJsonData.total)$('.totalQtySpan'+getProductId).html(getJsonData.total);
                    /*$('.showPurchase'+getOrderId+' .receiveQty').each(function(){
                        var getPurQty = parseInt($(this).parent().parent().find('.rowQty').attr('qty'));
                        var getInputQty= parseInt($(this).val());
                        if(getInputQty!='NaN'){
                            var curQty = getPurQty-getInputQty;
                            $(this).parent().parent().find('.rowQty').attr('qty',curQty).text(curQty);
                            var getOrderTotal = $('.notReceivedNumber'+getOrderId).text();
                        }
                    });*/
                    alert('设置库存成功');
                    window.location.reload();
                }
            });
        }else{
            alert('输入有误，大于采购数量。');
            return false;
        }
    });
</script>
</body>
</html>