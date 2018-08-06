/*
表单验证功能（需调用formvalidation插件）
 */
$(function(){
    // 验证码输入自动转为大写
    $(document).on('change keyup','.input-codeimg',function(){
        $(this).val($(this).val().toUpperCase());
    });
    // 上传文件
    $(document).on('change keyup','.input-group-file input[type="file"]',function(){
        if(!$(this).parents('.input-group-btn').find('.file-input').length){// 如果没有加载file-input插件
            // 输入框文件路径更新
            var $text=$(this).parents('.input-group-file').find('input[type="text"]'),
                value='';
            if(M['is_lteie9']){
                value=$(this).val();
            }else{
                var $input_group_file=$(this).parents('.input-group-file');
                if($(this)[0].files.length>10){
                    // 显示报错文字
                    if(!$input_group_file.next('small.form-control-label').length) $input_group_file.after('<small class="form-control-label"></small>');
                    $input_group_file.next('small.form-control-label').text('一次只能提交最多10张图片！');
                    $input_group_file.parents('.form-group').removeClass('has-success').addClass('has-danger');
                }else{
                    $.each($(this)[0].files,function(i,file){
                        value +=value?','+file.name:file.name;
                    });
                    $input_group_file.next('small.form-control-label').html('');
                    $input_group_file.parents('.form-group').removeClass('has-danger');
                }
            }
            if(value) $text.val(value);
        }
    });
    // 验证码点击刷新
    $(document).on('click',"#getcode",function(){
        var data_src=$(this).attr("data-src");
        if(!data_src){
            data_src=$(this).prop("src")+'&random=';
            $(this).attr({'data-src':data_src});
        }
        if(data_src.indexOf('&random=')<0) data_src+='&random=';
        $(this).attr({src:data_src+Math.floor(Math.random()*9999+1)});
    });
});
// 表单验证通用
$.fn.validation=function(){
    var $self=$(this),
        self_validation=$(this).formValidation({
        locale:M['validation_locale'],
        framework:'bootstrap4'
    });
    // 表单所处弹窗隐藏时重置验证
    $(this).parents('.modal').on('hide.bs.modal',function() {
        $self.data('formValidation').resetForm();
    });
    function success(fun,afterajax_ok){
        self_validation.on('success.form.fv', function(e) {
            e.preventDefault();
            var ajax_ok=typeof afterajax_ok != "undefined" ?afterajax_ok:true;
            if(ajax_ok){
                formDataAjax(e,fun);
            }else{
                $self.data('formValidation').resetForm();
                if (typeof fun==="function") window.form_data_ajax=fun(e,$self);
                setTimeout(function(){
                    if(typeof form_data_ajax =='undefined') $self.data('formValidation').defaultSubmit();
                },100)
            }
        })
    }
    function formDataAjax(e,fun){
        window.form_data_ajax=false;
        var $form    = $(e.target);
        if(M['is_lteie9']){
            var formData = $form.serializeArray(e.target),
                contentType='application/x-www-form-urlencoded',
                processData=true;
        }else{
            var formData = new FormData(e.target),
                params   = $form.serializeArray(),
                contentType=false,
                processData=false;
            // $.each(params, function(i, val) {
            //     formData.append(val.name, val.value);
            // });
        }
        $.ajax({
            url: $form.attr('action'),
            data: formData,
            cache: false,
            contentType: contentType,
            processData: processData,
            type: $form.attr('method')||'POST',
            dataType:'json',
            success: function(result) {
                $form.data('formValidation').resetForm();
                if (typeof fun==="function") return fun(result,$form);
            }
        });
    }
    // 表单提交前端处理
    success(function(e,form){
        // 多选值组合
        var checkbox_val={};
        form.find('input[type="checkbox"][name]').each(function(index, el) {
            var name=$(this).attr('name'),
                val=$(this).val();
            if(typeof checkbox_val[name] =='undefined') checkbox_val[name]='';
            if($(this).prop('checked')) checkbox_val[name]+=checkbox_val[name]!=''?('#@met@#'+val):val;
        });
        $.each(checkbox_val, function(index, val) {
            if(!form.find('[name="'+index+'"][type="hidden"]').length) form.append('<input type="hidden" name="'+index+'"/>');
            var length=form.find('[name="'+index+'"][type="hidden"]').length-1;
            form.find('[name="'+index+'"][type="hidden"]').eq(length).val(val);
        });
    },false);
    return {success:success,formDataAjax:formDataAjax};
}
// formValidation多语言选择
M['validation_locale']='';
if("undefined" != typeof M){
    M['validation_locale']=M['synchronous']+'_';
    switch(M['synchronous']){
        case 'sq':M['validation_locale']+='AL';break;
        case 'ar':M['validation_locale']+='MA';break;
        // case 'az':M['validation_locale']+='az';break;
        // case 'ga':M['validation_locale']+='ie';break;
        // case 'et':M['validation_locale']+='ee';break;
        case 'be':M['validation_locale']+='BE';break;
        case 'bg':M['validation_locale']+='BG';break;
        case 'pl':M['validation_locale']+='PL';break;
        case 'fa':M['validation_locale']+='IR';break;
        // case 'af':M['validation_locale']+='za';break;
        case 'da':M['validation_locale']+='DK';break;
        case 'de':M['validation_locale']+='DE';break;
        case 'ru':M['validation_locale']+='RU';break;
        case 'fr':M['validation_locale']+='FR';break;
        // case 'tl':M['validation_locale']+='ph';break;
        case 'fi':M['validation_locale']+='FI';break;
        // case 'ht':M['validation_locale']+='ht';break;
        // case 'ko':M['validation_locale']+='kr';break;
        case 'nl':M['validation_locale']+='NL';break;
        // case 'gl':M['validation_locale']+='es';break;
        case 'ca':M['validation_locale']+='ES';break;
        case 'cs':M['validation_locale']+='CZ';break;
        // case 'hr':M['validation_locale']+='hr';break;
        // case 'la':M['validation_locale']+='IT';break;
        // case 'lv':M['validation_locale']+='lv';break;
        // case 'lt':M['validation_locale']+='lt';break;
        case 'ro':M['validation_locale']+='RO';break;
        // case 'mt':M['validation_locale']+='mt';break;
        // case 'ms':M['validation_locale']+='ID';break;
        // case 'mk':M['validation_locale']+='mk';break;
        case 'no':M['validation_locale']+='NO';break;
        case 'pt':M['validation_locale']+='PT';break;
        case 'ja':M['validation_locale']+='JP';break;
        case 'sv':M['validation_locale']+='SE';break;
        case 'sr':M['validation_locale']+='RS';break;
        case 'sk':M['validation_locale']+='SK';break;
        // case 'sl':M['validation_locale']+='si';break;
        // case 'sw':M['validation_locale']+='tz';break;
        case 'th':M['validation_locale']+='TH';break;
        // case 'cy':M['validation_locale']+='wls';break;
        // case 'uk':M['validation_locale']+='ua';break;
        // case 'iw':M['validation_locale']+='';break;
        case 'el':M['validation_locale']+='GR';break;
        case 'eu':M['validation_locale']+='ES';break;
        case 'es':M['validation_locale']+='ES';break;
        case 'hu':M['validation_locale']+='HU';break;
        case 'it':M['validation_locale']+='IT';break;
        // case 'yi':M['validation_locale']+='de';break;
        // case 'ur':M['validation_locale']+='pk';break;
        case 'id':M['validation_locale']+='ID';break;
        case 'en':M['validation_locale']+='US';break;
        case 'vi':M['validation_locale']+='VN';break;
        case 'zh':M['validation_locale']='zh_TW';break;
        case 'cn':M['validation_locale']='zh_CN';break;
    }
}else{
    M['validation_locale']='zh_CN';
}
// 表单验证初始化
$('form').addClass('met-form-validation');
if(typeof validate =='undefined'){
    window.validate=[];
    $(".met-form-validation").each(function(index, el) {
        validate[index]=$(this).validation();
    });
}